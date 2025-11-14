<?php

namespace Drupal\db_wipe_db\Event;

/**
 * Defines events for the db_wipe_db module.
 */
final class DatabaseWipeEvents {

  /**
   * Event dispatched before table truncate operation starts.
   */
  const BEFORE_TRUNCATE = 'db_wipe_db.before_truncate';

  /**
   * Event dispatched after table truncate operation completes.
   */
  const AFTER_TRUNCATE = 'db_wipe_db.after_truncate';

}
