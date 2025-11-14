<?php

namespace Drupal\Tests\db_wipe\Kernel;

use Drupal\db_wipe_entity\Event\AfterEntityWipeEvent;
use Drupal\db_wipe_entity\Event\BeforeEntityWipeEvent;
use Drupal\db_wipe_entity\Event\EntityWipeBatchEvent;
use Drupal\db_wipe_entity\Event\EntityWipeEvents;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests for entity wipe events.
 *
 * @group db_wipe
 */
class EntityWipeEventsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'db_wipe',
    'db_wipe_entity',
    'db_wipe_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
  }

  /**
   * Tests BeforeEntityWipeEvent properties.
   */
  public function testBeforeEntityWipeEventProperties(): void {
    Node::create(['type' => 'article', 'title' => 'Test Article'])->save();

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE);

    $event = new BeforeEntityWipeEvent('node', $query, 10, 'article', FALSE);

    $this->assertEquals('node', $event->getEntityTypeId());
    $this->assertEquals(10, $event->getCount());
    $this->assertEquals('article', $event->getBundle());
    $this->assertFalse($event->isDryRun());
    $this->assertFalse($event->isWipePrevented());
  }

  /**
   * Tests BeforeEntityWipeEvent prevent wipe functionality.
   */
  public function testBeforeEntityWipeEventPreventWipe(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE);

    $event = new BeforeEntityWipeEvent('node', $query, 5, 'article', FALSE);

    $this->assertFalse($event->isWipePrevented());

    $event->preventWipe();

    $this->assertTrue($event->isWipePrevented());
  }

  /**
   * Tests BeforeEntityWipeEvent with dry run.
   */
  public function testBeforeEntityWipeEventDryRun(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE);

    $event = new BeforeEntityWipeEvent('node', $query, 5, 'article', TRUE);

    $this->assertTrue($event->isDryRun());
  }

  /**
   * Tests BeforeEntityWipeEvent without bundle.
   */
  public function testBeforeEntityWipeEventWithoutBundle(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE);

    $event = new BeforeEntityWipeEvent('node', $query, 5, NULL, FALSE);

    $this->assertNull($event->getBundle());
  }

  /**
   * Tests AfterEntityWipeEvent properties.
   */
  public function testAfterEntityWipeEventProperties(): void {
    $event = new AfterEntityWipeEvent('node', 15, 'article', TRUE);

    $this->assertEquals('node', $event->getEntityTypeId());
    $this->assertEquals(15, $event->getDeletedCount());
    $this->assertEquals('article', $event->getBundle());
    $this->assertTrue($event->isSuccess());
  }

  /**
   * Tests AfterEntityWipeEvent with failure.
   */
  public function testAfterEntityWipeEventFailure(): void {
    $event = new AfterEntityWipeEvent('node', 5, 'article', FALSE);

    $this->assertFalse($event->isSuccess());
  }

  /**
   * Tests AfterEntityWipeEvent without bundle.
   */
  public function testAfterEntityWipeEventWithoutBundle(): void {
    $event = new AfterEntityWipeEvent('node', 10, NULL, TRUE);

    $this->assertNull($event->getBundle());
  }

  /**
   * Tests EntityWipeBatchEvent properties.
   */
  public function testEntityWipeBatchEventProperties(): void {
    $deleted_ids = [1, 2, 3, 4, 5];
    $event = new EntityWipeBatchEvent('node', $deleted_ids, 5, 'article');

    $this->assertEquals('node', $event->getEntityTypeId());
    $this->assertEquals($deleted_ids, $event->getDeletedIds());
    $this->assertEquals(5, $event->getLastId());
    $this->assertEquals('article', $event->getBundle());
  }

  /**
   * Tests EntityWipeBatchEvent without bundle.
   */
  public function testEntityWipeBatchEventWithoutBundle(): void {
    $event = new EntityWipeBatchEvent('user', [1, 2], 2, NULL);

    $this->assertNull($event->getBundle());
  }

  /**
   * Tests event dispatcher integration.
   */
  public function testEventDispatcherIntegration(): void {
    $state = $this->container->get('state');
    $state->set('db_wipe_test.events', []);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE);

    $before_event = new BeforeEntityWipeEvent('node', $query, 3, 'article', FALSE);
    $this->container->get('event_dispatcher')->dispatch($before_event, EntityWipeEvents::BEFORE_WIPE);

    $batch_event = new EntityWipeBatchEvent('node', [1, 2, 3], 3, 'article');
    $this->container->get('event_dispatcher')->dispatch($batch_event, EntityWipeEvents::BATCH_PROCESS);

    $after_event = new AfterEntityWipeEvent('node', 3, 'article', TRUE);
    $this->container->get('event_dispatcher')->dispatch($after_event, EntityWipeEvents::AFTER_WIPE);

    $events = $state->get('db_wipe_test.events', []);
    $this->assertCount(3, $events, 'Three events were dispatched.');

    $this->assertEquals('before_wipe', $events[0]['event']);
    $this->assertEquals('batch_process', $events[1]['event']);
    $this->assertEquals('after_wipe', $events[2]['event']);
  }

}
