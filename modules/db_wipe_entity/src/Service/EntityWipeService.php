<?php

namespace Drupal\db_wipe_entity\Service;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\db_wipe_entity\Event\AfterEntityWipeEvent;
use Drupal\db_wipe_entity\Event\BeforeEntityWipeEvent;
use Drupal\db_wipe_entity\Event\EntityWipeBatchEvent;
use Drupal\db_wipe_entity\Event\EntityWipeEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service for deleting entities using entity API.
 */
class EntityWipeService {

  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EventDispatcherInterface $eventDispatcher,
    protected readonly MessengerInterface $messenger,
  ) {}

  /**
   * Builds entity query with filters.
   */
  public function buildQuery(
    string $entityTypeId,
    ?string $bundle = NULL,
    ?array $excludeIds = NULL,
    ?array $includeIds = NULL,
    bool $dispatchEvent = TRUE
  ): QueryInterface {
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $entity_type = $storage->getEntityType();
    $query = $storage->getQuery()->accessCheck(FALSE);

    if ($bundle && $bundle_key = $entity_type->getKey('bundle')) {
      $query->condition($bundle_key, $bundle);
    }

    // Apply ID filters.
    $id_key = $entity_type->getKey('id');

    if ($includeIds) {
      $query->condition($id_key, $includeIds, 'IN');
    }
    elseif ($excludeIds) {
      $query->condition($id_key, $excludeIds, 'NOT IN');
    }

    // Dispatch event to allow subscribers to modify query (e.g., protection).
    if ($dispatchEvent) {
      $event = new BeforeEntityWipeEvent($entityTypeId, $query, 0, $bundle, FALSE);
      $this->eventDispatcher->dispatch($event, EntityWipeEvents::BEFORE_WIPE);
    }

    return $query;
  }

  /**
   * Counts entities in query.
   */
  public function countEntities(QueryInterface $query): int {
    $count = clone $query;
    return (int) $count->count()->execute();
  }

  /**
   * Gets entity IDs from query.
   */
  public function getEntityIds(QueryInterface $query): array {
    return array_values($query->execute());
  }

  /**
   * Executes deletion with batch processing.
   */
  public function executeWipe(
    string $entityTypeId,
    QueryInterface $query,
    ?string $bundle = NULL,
    bool $dryRun = FALSE,
    ?array $excludeIds = NULL,
    ?array $includeIds = NULL
  ): array {
    $total = $this->countEntities($query);

    if (!$total) {
      return ['prevented' => FALSE, 'count' => 0];
    }

    // Dispatch event with actual count (query already modified by buildQuery event).
    $event = new BeforeEntityWipeEvent($entityTypeId, $query, $total, $bundle, $dryRun);
    $this->eventDispatcher->dispatch($event, EntityWipeEvents::BEFORE_WIPE);

    if ($event->isWipePrevented()) {
      return ['prevented' => TRUE, 'count' => $total];
    }

    if ($dryRun) {
      return ['prevented' => FALSE, 'count' => $total, 'dry_run' => TRUE];
    }

    $batch_builder = new BatchBuilder();
    $batch_builder
      ->setTitle($this->t('Deleting @type entities', ['@type' => $entityTypeId]))
      ->setInitMessage($this->t('Starting deletion process.'))
      ->setProgressMessage($this->t('Deleting @current of @total.'))
      ->setErrorMessage($this->t('An error occurred during deletion.'));

    $batch_builder->addOperation(
      [static::class, 'batchDeleteCallback'],
      [$entityTypeId, $bundle, $excludeIds, $includeIds]
    );
    $batch_builder->setFinishCallback(
      [static::class, 'batchFinish'],
      [$entityTypeId, $bundle, $total]
    );

    batch_set($batch_builder->toArray());

    return ['prevented' => FALSE, 'count' => $total];
  }

  /**
   * Batch callback for entity deletion.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param string|null $bundle
   *   The bundle to filter by.
   * @param array|null $excludeIds
   *   Entity IDs to exclude from deletion.
   * @param array|null $includeIds
   *   Entity IDs to include (only delete these).
   * @param array $context
   *   The batch context.
   */
  public static function batchDeleteCallback(
    string $entityTypeId,
    ?string $bundle,
    ?array $excludeIds,
    ?array $includeIds,
    array &$context
  ): void {
    if (!isset($context['sandbox']['last_id'])) {
      $context['sandbox']['last_id'] = 0;
      $context['sandbox']['deleted_count'] = 0;
    }

    $entity_type_manager = \Drupal::entityTypeManager();
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $wipe_service = \Drupal::service('db_wipe_entity.entity_wipe');

    $entity_storage = $entity_type_manager->getStorage($entityTypeId);
    $id_key = $entity_storage->getEntityType()->getKey('id');

    // Rebuild query with current filters.
    $query = $wipe_service->buildQuery($entityTypeId, $bundle, $excludeIds, $includeIds);
    $query
      ->condition($id_key, $context['sandbox']['last_id'], '>')
      ->sort($id_key)
      ->range(0, 50);

    $results = $query->execute();

    if (empty($results)) {
      $context['finished'] = 1;
      return;
    }
    $context['finished'] = 0;

    $ids = array_values($results);
    $entities = $entity_storage->loadMultiple($ids);
    $entity_storage->delete($entities);

    $context['sandbox']['last_id'] = end($ids);
    $context['sandbox']['deleted_count'] += count($ids);
    $context['message'] = t('Deleted @type entities up to ID: @id.', [
      '@type' => $entityTypeId,
      '@id' => $context['sandbox']['last_id'],
    ]);

    $event = new EntityWipeBatchEvent($entityTypeId, $ids, $context['sandbox']['last_id'], $bundle);
    $event_dispatcher->dispatch($event, EntityWipeEvents::BATCH_PROCESS);
  }

  /**
   * Batch finish callback.
   *
   * @param bool $success
   *   Whether the batch was successful.
   * @param array $results
   *   The batch results.
   * @param array $operations
   *   The batch operations.
   * @param string $entityTypeId
   *   The entity type ID.
   * @param string|null $bundle
   *   The bundle.
   * @param int $expectedCount
   *   The expected count of entities.
   */
  public static function batchFinish(
    bool $success,
    array $results,
    array $operations,
    string $entityTypeId,
    ?string $bundle = NULL,
    int $expectedCount = 0
  ): void {
    $messenger = \Drupal::messenger();
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $deleted_count = $results['sandbox']['deleted_count'] ?? 0;

    if ($success) {
      $messenger->addStatus(t('All entities deleted successfully.'));
    }
    else {
      $messenger->addError(t('An error occurred while deleting entities.'));
    }

    $event = new AfterEntityWipeEvent($entityTypeId, $deleted_count, $bundle, $success);
    $event_dispatcher->dispatch($event, EntityWipeEvents::AFTER_WIPE);
  }

  /**
   * Deletes entities immediately without batch processing.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param array $entityIds
   *   Array of entity IDs to delete.
   *
   * @return int
   *   The number of entities deleted.
   */
  public function deleteEntitiesImmediate(
    string $entityTypeId,
    array $entityIds
  ): int {
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $entities = $storage->loadMultiple($entityIds);
    $storage->delete($entities);

    return count($entities);
  }

  /**
   * Gets entity storage for a given entity type.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The entity storage.
   */
  public function getEntityStorage(string $entityTypeId): EntityStorageInterface {
    return $this->entityTypeManager->getStorage($entityTypeId);
  }

}
