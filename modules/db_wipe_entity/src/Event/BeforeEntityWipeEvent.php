<?php

namespace Drupal\db_wipe_entity\Event;

use Drupal\Core\Entity\Query\QueryInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched before entity wipe operation.
 */
class BeforeEntityWipeEvent extends Event {

  protected bool $preventWipe = FALSE;

  /**
   * Constructs a BeforeEntityWipeEvent object.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The entity query.
   * @param int $count
   *   Total count of entities to be deleted.
   * @param string|null $bundle
   *   The bundle name.
   * @param bool $dryRun
   *   Whether this is a dry run.
   */
  public function __construct(
    protected readonly string $entityTypeId,
    protected readonly QueryInterface $query,
    protected readonly int $count,
    protected readonly ?string $bundle = NULL,
    protected readonly bool $dryRun = FALSE,
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
   * Gets the query object.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query.
   */
  public function getQuery(): QueryInterface {
    return $this->query;
  }

  /**
   * Gets the total count of entities to be deleted.
   *
   * @return int
   *   The count of entities.
   */
  public function getCount(): int {
    return $this->count;
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
   * Checks if this is a dry run.
   *
   * @return bool
   *   TRUE if dry run, FALSE otherwise.
   */
  public function isDryRun(): bool {
    return $this->dryRun;
  }

  /**
   * Prevents the wipe operation.
   */
  public function preventWipe(): void {
    $this->preventWipe = TRUE;
  }

  /**
   * Checks if wipe should be prevented.
   *
   * @return bool
   *   TRUE if prevented, FALSE otherwise.
   */
  public function isWipePrevented(): bool {
    return $this->preventWipe;
  }

}
