<?php

namespace Drupal\db_wipe_entity\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched during batch processing.
 */
class EntityWipeBatchEvent extends Event {

  /**
   * Constructs an EntityWipeBatchEvent object.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param array $deletedIds
   *   Array of deleted entity IDs in this batch.
   * @param int $lastId
   *   The last processed entity ID.
   * @param string|null $bundle
   *   The bundle name.
   */
  public function __construct(
    protected readonly string $entityTypeId,
    protected readonly array $deletedIds,
    protected readonly int $lastId,
    protected readonly ?string $bundle = NULL,
  ) {}

  /**
   * Gets the entity type ID.
   *
   * @return string
   *   The entity type ID.
   */
  public function getEntityTypeId(): string {
    return $this->entityTypeId;
  }

  /**
   * Gets array of deleted entity IDs in this batch.
   *
   * @return array
   *   Array of entity IDs.
   */
  public function getDeletedIds(): array {
    return $this->deletedIds;
  }

  /**
   * Gets the last processed entity ID.
   *
   * @return int
   *   The last entity ID.
   */
  public function getLastId(): int {
    return $this->lastId;
  }

  /**
   * Gets the bundle name.
   *
   * @return string|null
   *   The bundle name or NULL.
   */
  public function getBundle(): ?string {
    return $this->bundle;
  }

}
