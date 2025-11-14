<?php

namespace Drupal\db_wipe_entity\Event;

/**
 * Defines events for the db_wipe_entity module.
 */
final class EntityWipeEvents {

  /**
   * Event dispatched before entity wipe operation starts.
   */
  const BEFORE_WIPE = 'db_wipe_entity.before_wipe';

  /**
   * Event dispatched after entity wipe operation completes.
   */
  const AFTER_WIPE = 'db_wipe_entity.after_wipe';

  /**
   * Event dispatched during batch processing.
   */
  const BATCH_PROCESS = 'db_wipe_entity.batch_process';

}
