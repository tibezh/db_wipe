<?php

namespace Drupal\db_wipe_db\Drush\Commands;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\db_wipe_db\Service\DatabaseWipeService;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for truncating database tables.
 */
final class DatabaseWipeCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * Constructs a DatabaseWipeCommands object.
   *
   * @param \Drupal\db_wipe_db\Service\DatabaseWipeService $databaseWipeService
   *   The database wipe service.
   */
  public function __construct(
    protected readonly DatabaseWipeService $databaseWipeService,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('db_wipe_db.database_wipe'),
    );
  }

  /**
   * List all safe tables that can be truncated.
   */
  #[CLI\Command(name: 'db:wipe-list-tables', aliases: ['dbwlt'])]
  #[CLI\Usage(name: 'db:wipe-list-tables', description: 'List all safe tables that can be truncated')]
  public function listTables(): void {
    $safe_tables = $this->databaseWipeService->getSafeTables();

    if (empty($safe_tables)) {
      $this->io()->warning($this->t('No safe tables found.'));
      return;
    }

    $this->io()->title($this->t('Safe tables available for truncation:'));

    $rows = [];
    foreach ($safe_tables as $table) {
      $row_count = $this->databaseWipeService->getRowCount($table);
      $rows[] = [
        'table' => $table,
        'rows' => number_format($row_count),
      ];
    }

    $this->io()->table(['Table', 'Rows'], $rows);
  }

  /**
   * Truncate a database table.
   *
   * WARNING: This permanently deletes all data from the table.
   * Only safe tables (watchdog, sessions, cache_*, etc.) can be truncated.
   */
  #[CLI\Command(name: 'db:wipe-table', aliases: ['dbwt'])]
  #[CLI\Argument(name: 'table', description: 'Table name to truncate')]
  #[CLI\Option(name: 'dry-run', description: 'Simulate truncation without actually truncating')]
  #[CLI\Usage(name: 'db:wipe-table watchdog', description: 'Truncate watchdog table')]
  #[CLI\Usage(name: 'db:wipe-table cache_bootstrap --dry-run', description: 'Preview truncation of cache_bootstrap')]
  #[CLI\Usage(name: 'db:wipe-table sessions --yes', description: 'Truncate sessions without confirmation')]
  #[CLI\Complete(method_name_or_callable: 'completeTables')]
  public function wipeTable(
    string $table,
    array $options = [
      'dry-run' => FALSE,
    ]
  ): void {
    // Validate table is safe.
    if (!$this->databaseWipeService->isTableSafe($table)) {
      $this->io()->error($this->t('Table "@table" is not in the safe tables list.', ['@table' => $table]));
      $this->io()->note($this->t('Run "drush db:wipe-list-tables" to see available tables.'));
      return;
    }

    // Validate table exists.
    if (!$this->databaseWipeService->tableExists($table)) {
      $this->io()->error($this->t('Table "@table" does not exist in the database.', ['@table' => $table]));
      return;
    }

    $row_count = $this->databaseWipeService->getRowCount($table);

    if (!$row_count) {
      $this->io()->success($this->t('Table "@table" is already empty.', ['@table' => $table]));
      return;
    }

    if (!$options['dry-run'] && !$this->input()->getOption('yes')) {
      $this->displayWarning($table, $row_count);

      // Require user to type table name for confirmation.
      $confirmation = $this->io()->ask($this->t('Type the table name "@table" to confirm', ['@table' => $table]));

      if ($confirmation !== $table) {
        throw new UserAbortException($this->t('Table name did not match. Operation cancelled.'));
      }
    }

    $result = $this->databaseWipeService->truncateTable($table, $options['dry-run']);

    if (!$result['success']) {
      $this->io()->error((string) $result['message']);
      return;
    }

    if ($result['prevented']) {
      $this->io()->warning((string) $result['message']);
      return;
    }

    if ($options['dry-run']) {
      $this->io()->success((string) $result['message']);
    }
    else {
      $this->io()->success((string) $result['message']);
    }
  }

  /**
   * Autocomplete callback for table names.
   */
  public function completeTables(): array {
    return $this->databaseWipeService->getSafeTables();
  }

  /**
   * Displays critical warning for table truncation.
   *
   * @param string $table
   *   The table name.
   * @param int $rowCount
   *   Number of rows in the table.
   */
  protected function displayWarning(string $table, int $rowCount): void {
    $this->io()->warning('═══════════════════════════════════════════════════════════');
    $this->io()->warning('                    ⚠️  CRITICAL WARNING  ⚠️');
    $this->io()->warning('═══════════════════════════════════════════════════════════');
    $this->io()->warning('');
    $this->io()->warning((string) $this->t('You are about to TRUNCATE table: @table', ['@table' => $table]));
    $this->io()->warning((string) $this->t('This will permanently delete @count rows', ['@count' => number_format($rowCount)]));
    $this->io()->warning('');
    $this->io()->warning((string) $this->t('⚠️  THIS ACTION CANNOT BE UNDONE!'));
    $this->io()->warning((string) $this->t('⚠️  ALL DATA IN THIS TABLE WILL BE LOST!'));
    $this->io()->warning((string) $this->t('⚠️  MAKE SURE YOU HAVE A BACKUP!'));
    $this->io()->warning('');
    $this->io()->warning('═══════════════════════════════════════════════════════════');
  }

}
