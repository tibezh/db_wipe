<?php

namespace Drupal\db_wipe_test\EventSubscriber;

use Drupal\Core\State\StateInterface;
use Drupal\db_wipe_entity\Event\AfterEntityWipeEvent;
use Drupal\db_wipe_entity\Event\BeforeEntityWipeEvent;
use Drupal\db_wipe_entity\Event\EntityWipeBatchEvent;
use Drupal\db_wipe_entity\Event\EntityWipeEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for testing db_wipe events.
 */
class TestEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a TestEventSubscriber object.
   */
  public function __construct(
    protected readonly StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityWipeEvents::BEFORE_WIPE => ['onBeforeWipe', 0],
      EntityWipeEvents::AFTER_WIPE => ['onAfterWipe', 0],
      EntityWipeEvents::BATCH_PROCESS => ['onBatchProcess', 0],
    ];
  }

  /**
   * Responds to before wipe event.
   */
  public function onBeforeWipe(BeforeEntityWipeEvent $event): void {
    $events = $this->state->get('db_wipe_test.events', []);
    $events[] = [
      'event' => 'before_wipe',
      'entity_type' => $event->getEntityTypeId(),
      'bundle' => $event->getBundle(),
      'count' => $event->getCount(),
      'dry_run' => $event->isDryRun(),
    ];
    $this->state->set('db_wipe_test.events', $events);

    if ($this->state->get('db_wipe_test.prevent_wipe', FALSE)) {
      $event->preventWipe();
    }
  }

  /**
   * Responds to after wipe event.
   */
  public function onAfterWipe(AfterEntityWipeEvent $event): void {
    $events = $this->state->get('db_wipe_test.events', []);
    $events[] = [
      'event' => 'after_wipe',
      'entity_type' => $event->getEntityTypeId(),
      'bundle' => $event->getBundle(),
      'deleted_count' => $event->getDeletedCount(),
      'success' => $event->isSuccess(),
    ];
    $this->state->set('db_wipe_test.events', $events);
  }

  /**
   * Responds to batch process event.
   */
  public function onBatchProcess(EntityWipeBatchEvent $event): void {
    $events = $this->state->get('db_wipe_test.events', []);
    $events[] = [
      'event' => 'batch_process',
      'entity_type' => $event->getEntityTypeId(),
      'bundle' => $event->getBundle(),
      'deleted_ids' => $event->getDeletedIds(),
      'last_id' => $event->getLastId(),
    ];
    $this->state->set('db_wipe_test.events', $events);
  }

}
