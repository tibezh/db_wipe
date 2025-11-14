<?php

namespace Drupal\db_wipe_db\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched before table truncate operation.
 */
class BeforeTableTruncateEvent extends Event {

  /**
   * Whether the truncate operation has been prevented.
   */
  protected bool $prevented = FALSE;

  /**
   * Constructs a BeforeTableTruncateEvent object.
   *
   * @param string $tableName
   *   The table name to truncate.
   * @param array $tables
   *   Array of all tables to be truncated.
   */
  public function __construct(
    protected string $tableName,
    protected array $tables = [],
  ) {}

  /**
   * Gets the table name.
   *
   * @return string
   *   The table name.
   */
  public function getTableName(): string {
    return $this->tableName;
  }

  /**
   * Gets all tables to be truncated.
   *
   * @return array
   *   Array of table names.
   */
  public function getTables(): array {
    return $this->tables;
  }

  /**
   * Prevents the truncate operation.
   */
  public function preventTruncate(): void {
    $this->prevented = TRUE;
  }

  /**
   * Checks if the truncate operation is prevented.
   *
   * @return bool
   *   TRUE if prevented, FALSE otherwise.
   */
  public function isTruncatePrevented(): bool {
    return $this->prevented;
  }

}
