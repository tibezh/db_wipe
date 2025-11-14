<?php

namespace Drupal\db_wipe_db\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after table truncate operation.
 */
class AfterTableTruncateEvent extends Event {

  /**
   * Constructs an AfterTableTruncateEvent object.
   *
   * @param string $tableName
   *   The table name that was truncated.
   * @param bool $success
   *   Whether the truncate operation was successful.
   * @param array $tables
   *   Array of all tables that were truncated.
   */
  public function __construct(
    protected string $tableName,
    protected bool $success,
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
   * Checks if the operation was successful.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function isSuccess(): bool {
    return $this->success;
  }

  /**
   * Gets all tables that were truncated.
   *
   * @return array
   *   Array of table names.
   */
  public function getTables(): array {
    return $this->tables;
  }

}
