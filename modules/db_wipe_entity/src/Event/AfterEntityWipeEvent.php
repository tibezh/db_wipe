<?php

namespace Drupal\db_wipe_entity\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after entity wipe operation.
 */
class AfterEntityWipeEvent extends Event {

  /**
   * Constructs an AfterEntityWipeEvent object.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param int $deletedCount
   *   The count of deleted entities.
   * @param string|null $bundle
   *   The bundle name.
   * @param bool $success
   *   Whether the operation was successful.
   */
  public function __construct(
    protected readonly string $entityTypeId,
    protected readonly int $deletedCount,
    protected readonly ?string $bundle = NULL,
    protected readonly bool $success = TRUE,
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
   * Gets the count of deleted entities.
   *
   * @return int
   *   The deleted count.
   */
  public function getDeletedCount(): int {
    return $this->deletedCount;
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

  /**
   * Checks if operation was successful.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function isSuccess(): bool {
    return $this->success;
  }

}
