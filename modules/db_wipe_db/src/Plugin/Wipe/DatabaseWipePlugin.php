<?php

namespace Drupal\db_wipe_db\Plugin\Wipe;

use Drupal\db_wipe\Plugin\WipePluginBase;
use Drupal\db_wipe_db\Service\DatabaseWipeService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides database table truncation functionality.
 *
 * @WipePlugin(
 *   id = "database_wipe",
 *   label = @Translation("Database Truncate"),
 *   description = @Translation("Truncates database tables directly using TRUNCATE command. Very fast but bypasses entity API."),
 *   category = "database",
 *   weight = 10
 * )
 */
class DatabaseWipePlugin extends WipePluginBase {

  /**
   * Constructs a DatabaseWipePlugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\db_wipe_db\Service\DatabaseWipeService $databaseWipeService
   *   The database wipe service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected DatabaseWipeService $databaseWipeService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('db_wipe_db.database_wipe')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function supports($target): bool {
    // Target should be a table name (string).
    if (!is_string($target)) {
      return FALSE;
    }

    // Check if table is safe and exists.
    return $this->databaseWipeService->isTableSafe($target)
      && $this->databaseWipeService->tableExists($target);
  }

  /**
   * {@inheritdoc}
   */
  public function validate($target, array $options = []): array {
    $errors = parent::validate($target, $options);

    if (!$this->databaseWipeService->isTableSafe($target)) {
      $errors[] = $this->t('Table @table is not in the safe tables list.', [
        '@table' => $target,
      ]);
    }

    if (!$this->databaseWipeService->tableExists($target)) {
      $errors[] = $this->t('Table @table does not exist.', [
        '@table' => $target,
      ]);
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function preview($target, array $options = []): array {
    $row_count = $this->databaseWipeService->getRowCount($target);

    return [
      'count' => $row_count,
      'items' => [],
      'details' => [
        'table' => $target,
        'method' => 'truncate',
        'fast_operation' => TRUE,
        'bypasses_entity_api' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute($target, array $options = []): array {
    $dry_run = $options['dry_run'] ?? FALSE;

    $result = $this->databaseWipeService->truncateTable($target, $dry_run);

    return [
      'success' => $result['success'],
      'count' => $result['row_count'],
      'message' => $result['message'],
      'prevented' => $result['prevented'],
    ];
  }

}
