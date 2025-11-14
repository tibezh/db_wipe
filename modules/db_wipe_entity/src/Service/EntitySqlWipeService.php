<?php

namespace Drupal\db_wipe_entity\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service for deleting entities using direct SQL queries.
 *
 * WARNING: This service bypasses the Entity API and all hooks/events.
 * Use only when performance is critical and you understand the risks.
 */
class EntitySqlWipeService {

  use StringTranslationTrait;

  /**
   * Constructs an EntitySqlWipeService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(
    protected readonly Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
    protected readonly LoggerChannelFactoryInterface $loggerFactory
  ) {}

  /**
   * Gets all tables for an entity type.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   *
   * @return array
   *   Array of table names associated with the entity type.
   */
  public function getEntityTables(string $entityTypeId): array {
    $tables = [];
    $entity_type = $this->entityTypeManager->getDefinition($entityTypeId);
    $storage = $this->entityTypeManager->getStorage($entityTypeId);

    if (!($storage instanceof SqlEntityStorageInterface)) {
      throw new \InvalidArgumentException("Entity type '$entityTypeId' does not use SQL storage.");
    }

    // Get base table.
    if ($base_table = $entity_type->getBaseTable()) {
      $tables['base'] = $base_table;
    }

    // Get data table (for translatable entities).
    if ($data_table = $entity_type->getDataTable()) {
      $tables['data'] = $data_table;
    }

    // Get revision table.
    if ($entity_type->isRevisionable()) {
      if ($revision_table = $entity_type->getRevisionTable()) {
        $tables['revision'] = $revision_table;
      }

      // Get revision data table (for translatable revisionable entities).
      if ($revision_data_table = $entity_type->getRevisionDataTable()) {
        $tables['revision_data'] = $revision_data_table;
      }
    }

    // Get field tables.
    $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entityTypeId);
    foreach ($field_definitions as $field_name => $field_storage) {
      // Skip base fields as they're in the main tables.
      if ($field_storage->isBaseField()) {
        continue;
      }

      // Get dedicated field tables.
      $field_table = $entityTypeId . '__' . $field_name;
      if ($this->database->schema()->tableExists($field_table)) {
        $tables['field_' . $field_name] = $field_table;
      }

      // Get revision field tables.
      if ($entity_type->isRevisionable()) {
        $revision_field_table = $entityTypeId . '_revision__' . $field_name;
        if ($this->database->schema()->tableExists($revision_field_table)) {
          $tables['field_revision_' . $field_name] = $revision_field_table;
        }
      }
    }

    return $tables;
  }

  /**
   * Deletes entities using direct SQL queries.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param array $entityIds
   *   Array of entity IDs to delete.
   * @param bool $deleteRevisions
   *   Whether to delete all revisions.
   * @param bool $deleteFields
   *   Whether to delete field data.
   *
   * @return array
   *   Array with deletion results.
   */
  public function deleteEntitiesBySql(
    string $entityTypeId,
    array $entityIds,
    bool $deleteRevisions = TRUE,
    bool $deleteFields = TRUE
  ): array {
    if (empty($entityIds)) {
      return [
        'success' => FALSE,
        'message' => $this->t('No entity IDs provided.'),
        'deleted_count' => 0,
        'tables_affected' => [],
      ];
    }

    $entity_type = $this->entityTypeManager->getDefinition($entityTypeId);
    $id_key = $entity_type->getKey('id');
    $tables = $this->getEntityTables($entityTypeId);
    $logger = $this->loggerFactory->get('db_wipe_entity_sql');

    $deleted_count = 0;
    $tables_affected = [];
    $errors = [];

    // Start transaction.
    $transaction = $this->database->startTransaction();

    try {
      // Delete from field tables first (to avoid foreign key constraints).
      if ($deleteFields) {
        foreach ($tables as $table_type => $table_name) {
          if (str_starts_with($table_type, 'field_')) {
            $field_id_key = 'entity_id';
            $count = $this->database->delete($table_name)
              ->condition($field_id_key, $entityIds, 'IN')
              ->execute();

            if ($count > 0) {
              $tables_affected[$table_name] = $count;
            }
          }
        }
      }

      // Delete from revision tables if applicable.
      if ($deleteRevisions && $entity_type->isRevisionable()) {
        $revision_id_key = $entity_type->getKey('revision');

        // Get all revision IDs for the entities.
        if (!empty($tables['revision'])) {
          $revision_ids = $this->database->select($tables['revision'], 'r')
            ->fields('r', [$revision_id_key])
            ->condition($id_key, $entityIds, 'IN')
            ->execute()
            ->fetchCol();

          // Delete from revision data table.
          if (!empty($tables['revision_data'])) {
            $count = $this->database->delete($tables['revision_data'])
              ->condition($revision_id_key, $revision_ids, 'IN')
              ->execute();

            if ($count > 0) {
              $tables_affected[$tables['revision_data']] = $count;
            }
          }

          // Delete from revision table.
          $count = $this->database->delete($tables['revision'])
            ->condition($revision_id_key, $revision_ids, 'IN')
            ->execute();

          if ($count > 0) {
            $tables_affected[$tables['revision']] = $count;
          }
        }
      }

      // Delete from data table if exists.
      if (!empty($tables['data'])) {
        $count = $this->database->delete($tables['data'])
          ->condition($id_key, $entityIds, 'IN')
          ->execute();

        if ($count > 0) {
          $tables_affected[$tables['data']] = $count;
        }
      }

      // Delete from base table.
      if (!empty($tables['base'])) {
        $deleted_count = $this->database->delete($tables['base'])
          ->condition($id_key, $entityIds, 'IN')
          ->execute();

        if ($deleted_count > 0) {
          $tables_affected[$tables['base']] = $deleted_count;
        }
      }

      // Log the operation.
      $logger->notice('Deleted @count @type entities via SQL. Tables affected: @tables', [
        '@count' => $deleted_count,
        '@type' => $entityTypeId,
        '@tables' => implode(', ', array_keys($tables_affected)),
      ]);

      return [
        'success' => TRUE,
        'message' => $this->t('Deleted @count entities via SQL.', ['@count' => $deleted_count]),
        'deleted_count' => $deleted_count,
        'tables_affected' => $tables_affected,
      ];
    }
    catch (\Exception $e) {
      // Rollback transaction.
      $transaction->rollBack();

      $error_message = $this->t('SQL deletion failed: @error', ['@error' => $e->getMessage()]);
      $logger->error('SQL deletion failed for @type: @error', [
        '@type' => $entityTypeId,
        '@error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'message' => $error_message,
        'deleted_count' => 0,
        'tables_affected' => [],
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Deletes all entities of a type using SQL.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param string|null $bundle
   *   Optional bundle to filter by.
   * @param array $excludeIds
   *   Entity IDs to exclude from deletion.
   *
   * @return array
   *   Array with deletion results.
   */
  public function deleteAllEntitiesBySql(
    string $entityTypeId,
    ?string $bundle = NULL,
    array $excludeIds = []
  ): array {
    $entity_type = $this->entityTypeManager->getDefinition($entityTypeId);
    $id_key = $entity_type->getKey('id');
    $bundle_key = $entity_type->getKey('bundle');
    $tables = $this->getEntityTables($entityTypeId);
    $logger = $this->loggerFactory->get('db_wipe_entity_sql');

    // Build query to get IDs to delete.
    $query = $this->database->select($tables['base'] ?? $tables['data'], 't')
      ->fields('t', [$id_key]);

    if ($bundle && $bundle_key) {
      $query->condition($bundle_key, $bundle);
    }

    if (!empty($excludeIds)) {
      $query->condition($id_key, $excludeIds, 'NOT IN');
    }

    $entity_ids = $query->execute()->fetchCol();

    if (empty($entity_ids)) {
      return [
        'success' => TRUE,
        'message' => $this->t('No entities found to delete.'),
        'deleted_count' => 0,
        'tables_affected' => [],
      ];
    }

    // Delete in batches to avoid memory issues.
    $batch_size = 1000;
    $total_deleted = 0;
    $all_tables_affected = [];

    foreach (array_chunk($entity_ids, $batch_size) as $batch) {
      $result = $this->deleteEntitiesBySql($entityTypeId, $batch);

      if (!$result['success']) {
        return $result;
      }

      $total_deleted += $result['deleted_count'];

      // Merge tables affected.
      foreach ($result['tables_affected'] as $table => $count) {
        $all_tables_affected[$table] = ($all_tables_affected[$table] ?? 0) + $count;
      }
    }

    return [
      'success' => TRUE,
      'message' => $this->t('Deleted @count @type entities via SQL.', [
        '@count' => $total_deleted,
        '@type' => $entityTypeId,
      ]),
      'deleted_count' => $total_deleted,
      'tables_affected' => $all_tables_affected,
    ];
  }

  /**
   * Gets row counts for all entity tables.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   *
   * @return array
   *   Array of table names with row counts.
   */
  public function getEntityTableCounts(string $entityTypeId): array {
    $tables = $this->getEntityTables($entityTypeId);
    $counts = [];

    foreach ($tables as $table_type => $table_name) {
      try {
        $count = $this->database->select($table_name)
          ->countQuery()
          ->execute()
          ->fetchField();

        $counts[$table_name] = [
          'type' => $table_type,
          'name' => $table_name,
          'count' => (int) $count,
        ];
      }
      catch (\Exception $e) {
        $counts[$table_name] = [
          'type' => $table_type,
          'name' => $table_name,
          'count' => 0,
          'error' => $e->getMessage(),
        ];
      }
    }

    return $counts;
  }

  /**
   * Truncates all tables for an entity type.
   *
   * WARNING: This will delete ALL data for the entity type!
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param bool $disableForeignKeyChecks
   *   Whether to disable foreign key checks.
   *
   * @return array
   *   Array with truncation results.
   */
  public function truncateEntityTables(
    string $entityTypeId,
    bool $disableForeignKeyChecks = TRUE
  ): array {
    $tables = $this->getEntityTables($entityTypeId);
    $logger = $this->loggerFactory->get('db_wipe_entity_sql');
    $truncated = [];
    $errors = [];

    // Start transaction.
    $transaction = $this->database->startTransaction();

    try {
      // Disable foreign key checks if requested (MySQL/MariaDB).
      if ($disableForeignKeyChecks) {
        $this->database->query('SET FOREIGN_KEY_CHECKS = 0');
      }

      // Truncate tables in reverse order (fields first, base last).
      $ordered_tables = array_reverse($tables);

      foreach ($ordered_tables as $table_type => $table_name) {
        try {
          $this->database->truncate($table_name)->execute();
          $truncated[$table_name] = TRUE;
        }
        catch (\Exception $e) {
          $errors[$table_name] = $e->getMessage();
        }
      }

      // Re-enable foreign key checks.
      if ($disableForeignKeyChecks) {
        $this->database->query('SET FOREIGN_KEY_CHECKS = 1');
      }

      if (empty($errors)) {
        $logger->notice('Truncated all tables for entity type @type: @tables', [
          '@type' => $entityTypeId,
          '@tables' => implode(', ', array_keys($truncated)),
        ]);

        return [
          'success' => TRUE,
          'message' => $this->t('Successfully truncated @count tables for @type.', [
            '@count' => count($truncated),
            '@type' => $entityTypeId,
          ]),
          'truncated' => $truncated,
          'errors' => [],
        ];
      }
      else {
        // Rollback if there were errors.
        $transaction->rollBack();

        $logger->error('Failed to truncate some tables for @type: @errors', [
          '@type' => $entityTypeId,
          '@errors' => json_encode($errors),
        ]);

        return [
          'success' => FALSE,
          'message' => $this->t('Failed to truncate some tables.'),
          'truncated' => $truncated,
          'errors' => $errors,
        ];
      }
    }
    catch (\Exception $e) {
      $transaction->rollBack();

      $logger->error('Failed to truncate tables for @type: @error', [
        '@type' => $entityTypeId,
        '@error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'message' => $this->t('Truncation failed: @error', ['@error' => $e->getMessage()]),
        'truncated' => [],
        'errors' => ['general' => $e->getMessage()],
      ];
    }
  }

  /**
   * Validates if entity type supports SQL operations.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   *
   * @return bool
   *   TRUE if supported, FALSE otherwise.
   */
  public function isEntityTypeSqlCompatible(string $entityTypeId): bool {
    try {
      $storage = $this->entityTypeManager->getStorage($entityTypeId);
      return $storage instanceof SqlEntityStorageInterface;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

}