<?php

namespace Drupal\db_wipe_entity\EventSubscriber;

use Drupal\db_wipe_entity\Event\BeforeEntityWipeEvent;
use Drupal\db_wipe_entity\Event\EntityWipeEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to protect User ID 1 from deletion.
 */
class UserIdOneProtectionSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityWipeEvents::BEFORE_WIPE => ['protectUserIdOne', 100],
    ];
  }

  /**
   * Protects User ID 1 from deletion.
   *
   * @param \Drupal\db_wipe\Event\BeforeEntityWipeEvent $event
   *   The before wipe event.
   */
  public function protectUserIdOne(BeforeEntityWipeEvent $event): void {
    // Only act on user entity type.
    if ($event->getEntityTypeId() !== 'user') {
      return;
    }

    // Add condition to exclude User ID 1.
    $query = $event->getQuery();
    $query->condition('uid', '1', '!=');
  }

}
