<?php

namespace Drupal\db_wipe_db\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\db_wipe_db\Event\AfterTableTruncateEvent;
use Drupal\db_wipe_db\Event\BeforeTableTruncateEvent;
use Drupal\db_wipe_db\Event\DatabaseWipeEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service for truncating database tables.
 */
class DatabaseWipeService {

  use StringTranslationTrait;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructs a DatabaseWipeService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(
    protected readonly Connection $database,
    protected readonly EventDispatcherInterface $eventDispatcher,
    protected readonly MessengerInterface $messenger,
    ConfigFactoryInterface $configFactory,
    LoggerChannelFactoryInterface $loggerFactory
  ) {
    $this->configFactory = $configFactory;
    $this->loggerFactory = $loggerFactory;
  }

  /**
   * Gets configuration.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The configuration.
   */
  protected function getConfig() {
    return $this->configFactory->get('db_wipe_db.settings');
  }

  /**
   * Gets logger.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger.
   */
  protected function getLogger() {
    return $this->loggerFactory->get('db_wipe_db');
  }

  /**
   * Gets all tables in the database.
   *
   * @return array
   *   Array of table names.
   */
  public function getAllTables(): array {
    return $this->database->schema()->findTables('%');
  }

  /**
   * Gets all safe tables based on configuration.
   *
   * @return array
   *   Array of safe table names.
   */
  public function getSafeTables(): array {
    $config = $this->getConfig();
    $mode = $config->get('tables.mode');
    $all_tables = $this->getAllTables();
    $safe_tables = [];

    switch ($mode) {
      case 'whitelist':
        // Use predefined safe tables and prefixes.
        $safe_list = $config->get('tables.safe_tables') ?? [];
        $safe_prefixes = $config->get('tables.safe_prefixes') ?? [];

        foreach ($all_tables as $table) {
          // Check exact match.
          if (in_array($table, $safe_list, TRUE)) {
            $safe_tables[] = $table;
            continue;
          }

          // Check prefixes.
          foreach ($safe_prefixes as $prefix) {
            if (str_starts_with($table, $prefix)) {
              $safe_tables[] = $table;
              break;
            }
          }
        }
        break;

      case 'custom':
        // Use custom tables list.
        $custom_tables = $config->get('tables.custom_tables') ?? [];
        $safe_tables = array_intersect($all_tables, $custom_tables);
        break;

      case 'all_with_prefix':
        // Allow all tables with specific prefixes.
        $safe_prefixes = $config->get('tables.safe_prefixes') ?? [];

        foreach ($all_tables as $table) {
          foreach ($safe_prefixes as $prefix) {
            if (str_starts_with($table, $prefix)) {
              $safe_tables[] = $table;
              break;
            }
          }
        }
        break;
    }

    // Remove excluded and protected tables.
    $excluded = $config->get('tables.excluded_tables') ?? [];
    $protected = $config->get('tables.protected_tables') ?? [];
    $safe_tables = array_diff($safe_tables, $excluded, $protected);

    sort($safe_tables);
    return array_values(array_unique($safe_tables));
  }

  /**
   * Checks if table is safe to truncate.
   *
   * @param string $tableName
   *   The table name.
   *
   * @return bool
   *   TRUE if safe, FALSE otherwise.
   */
  public function isTableSafe(string $tableName): bool {
    return in_array($tableName, $this->getSafeTables(), TRUE);
  }

  /**
   * Checks if table is protected.
   *
   * @param string $tableName
   *   The table name.
   *
   * @return bool
   *   TRUE if protected, FALSE otherwise.
   */
  public function isTableProtected(string $tableName): bool {
    $config = $this->getConfig();
    $protected = $config->get('tables.protected_tables') ?? [];
    return in_array($tableName, $protected, TRUE);
  }

  /**
   * Checks if table exists.
   *
   * @param string $tableName
   *   The table name.
   *
   * @return bool
   *   TRUE if exists, FALSE otherwise.
   */
  public function tableExists(string $tableName): bool {
    return $this->database->schema()->tableExists($tableName);
  }

  /**
   * Gets row count for table.
   *
   * @param string $tableName
   *   The table name.
   *
   * @return int
   *   The row count.
   */
  public function getRowCount(string $tableName): int {
    try {
      $count = $this->database
        ->select($tableName, 't')
        ->countQuery()
        ->execute()
        ->fetchField();

      return (int) $count;
    }
    catch (\Exception $e) {
      return 0;
    }
  }

  /**
   * Gets table size in bytes.
   *
   * @param string $tableName
   *   The table name.
   *
   * @return int
   *   The table size in bytes.
   */
  public function getTableSize(string $tableName): int {
    try {
      // This works for MySQL/MariaDB.
      $result = $this->database->query("
        SELECT data_length + index_length AS size
        FROM information_schema.TABLES
        WHERE table_schema = DATABASE()
        AND table_name = :table
      ", [':table' => $tableName])->fetchField();

      return (int) $result;
    }
    catch (\Exception $e) {
      return 0;
    }
  }

  /**
   * Truncates a table.
   *
   * @param string $tableName
   *   The table name.
   * @param bool $dryRun
   *   Whether this is a dry run.
   * @param bool $force
   *   Force truncation even for protected tables.
   *
   * @return array
   *   Result array with success status and message.
   */
  public function truncateTable(string $tableName, bool $dryRun = FALSE, bool $force = FALSE): array {
    $config = $this->getConfig();
    $logger = $this->getLogger();
    $logging = $config->get('logging');

    // Check if table is protected.
    if (!$force && $this->isTableProtected($tableName)) {
      $message = $this->t('Table @table is protected and cannot be truncated.', ['@table' => $tableName]);
      if ($logging['enabled']) {
        $logger->error('Attempted to truncate protected table: @table', ['@table' => $tableName]);
      }
      return [
        'success' => FALSE,
        'prevented' => TRUE,
        'message' => $message,
        'row_count' => 0,
      ];
    }

    // Validate table is safe.
    if (!$force && !$this->isTableSafe($tableName)) {
      return [
        'success' => FALSE,
        'prevented' => TRUE,
        'message' => $this->t('Table @table is not in the safe list.', ['@table' => $tableName]),
        'row_count' => 0,
      ];
    }

    // Validate table exists.
    if (!$this->tableExists($tableName)) {
      return [
        'success' => FALSE,
        'prevented' => TRUE,
        'message' => $this->t('Table @table does not exist.', ['@table' => $tableName]),
        'row_count' => 0,
      ];
    }

    $row_count = $this->getRowCount($tableName);

    // Log row count if enabled.
    if ($logging['enabled'] && $logging['log_row_counts']) {
      $logger->info('Table @table has @count rows before truncation.', [
        '@table' => $tableName,
        '@count' => $row_count,
      ]);
    }

    // Dispatch before event.
    $event = new BeforeTableTruncateEvent($tableName, [$tableName]);
    $this->eventDispatcher->dispatch($event, DatabaseWipeEvents::BEFORE_TRUNCATE);

    if ($event->isTruncatePrevented()) {
      return [
        'success' => FALSE,
        'prevented' => TRUE,
        'message' => $this->t('Truncate operation prevented by event subscriber.'),
        'row_count' => $row_count,
      ];
    }

    if ($dryRun) {
      return [
        'success' => TRUE,
        'prevented' => FALSE,
        'message' => $this->t('[DRY RUN] Would truncate table @table (@count rows)', [
          '@table' => $tableName,
          '@count' => $row_count,
        ]),
        'row_count' => $row_count,
      ];
    }

    // Perform truncate.
    $performance = $config->get('performance');
    $transaction = NULL;

    try {
      // Start transaction if configured.
      if ($performance['use_transaction']) {
        $transaction = $this->database->startTransaction();
      }

      // Disable foreign key checks if configured (MySQL/MariaDB specific).
      if ($performance['disable_foreign_key_checks']) {
        $this->database->query('SET FOREIGN_KEY_CHECKS = 0');
      }

      // Truncate the table.
      $this->database->truncate($tableName)->execute();

      // Re-enable foreign key checks.
      if ($performance['disable_foreign_key_checks']) {
        $this->database->query('SET FOREIGN_KEY_CHECKS = 1');
      }

      $success = TRUE;
      $message = $this->t('Successfully truncated table @table (@count rows deleted)', [
        '@table' => $tableName,
        '@count' => $row_count,
      ]);

      // Log success.
      if ($logging['enabled']) {
        $logger->notice('Truncated table @table: @count rows deleted.', [
          '@table' => $tableName,
          '@count' => $row_count,
        ]);
      }
    }
    catch (\Exception $e) {
      // Rollback transaction if started.
      if ($transaction) {
        $transaction->rollBack();
      }

      $success = FALSE;
      $message = $this->t('Failed to truncate table @table: @error', [
        '@table' => $tableName,
        '@error' => $e->getMessage(),
      ]);

      // Log error.
      if ($logging['enabled']) {
        $logger->error('Failed to truncate table @table: @error', [
          '@table' => $tableName,
          '@error' => $e->getMessage(),
        ]);
      }
    }

    // Dispatch after event.
    $after_event = new AfterTableTruncateEvent($tableName, $success, [$tableName]);
    $this->eventDispatcher->dispatch($after_event, DatabaseWipeEvents::AFTER_TRUNCATE);

    return [
      'success' => $success,
      'prevented' => FALSE,
      'message' => $message,
      'row_count' => $row_count,
    ];
  }

  /**
   * Truncates multiple tables.
   *
   * @param array $tableNames
   *   Array of table names.
   * @param bool $dryRun
   *   Whether this is a dry run.
   * @param bool $force
   *   Force truncation even for protected tables.
   *
   * @return array
   *   Results array keyed by table name.
   */
  public function truncateTables(array $tableNames, bool $dryRun = FALSE, bool $force = FALSE): array {
    $results = [];
    $config = $this->getConfig();
    $performance = $config->get('performance');

    if ($performance['batch_operations'] && count($tableNames) > 5) {
      // Use batch processing for large operations.
      $batch = [
        'title' => $this->t('Truncating tables'),
        'operations' => [],
        'finished' => [self::class, 'batchFinished'],
      ];

      foreach ($tableNames as $table) {
        $batch['operations'][] = [
          [self::class, 'batchTruncate'],
          [$table, $dryRun, $force],
        ];
      }

      batch_set($batch);
      return ['batch_set' => TRUE];
    }

    // Process directly for small number of tables.
    foreach ($tableNames as $table) {
      $results[$table] = $this->truncateTable($table, $dryRun, $force);
    }

    return $results;
  }

  /**
   * Batch operation callback.
   */
  public static function batchTruncate($table, $dryRun, $force, &$context) {
    $service = \Drupal::service('db_wipe_db.database_wipe');
    $result = $service->truncateTable($table, $dryRun, $force);

    $context['results'][$table] = $result;
    $context['message'] = t('Truncated table: @table', ['@table' => $table]);
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      $messenger = \Drupal::messenger();
      $count = count($results);
      $messenger->addStatus(t('Successfully processed @count tables.', ['@count' => $count]));
    }
    else {
      $messenger = \Drupal::messenger();
      $messenger->addError(t('An error occurred while truncating tables.'));
    }
  }

  /**
   * Gets statistics for all safe tables.
   *
   * @return array
   *   Array of table statistics.
   */
  public function getTableStatistics(): array {
    $stats = [];
    $safe_tables = $this->getSafeTables();

    foreach ($safe_tables as $table) {
      $stats[$table] = [
        'name' => $table,
        'rows' => $this->getRowCount($table),
        'size' => $this->getTableSize($table),
        'safe' => TRUE,
        'protected' => $this->isTableProtected($table),
      ];
    }

    return $stats;
  }

}