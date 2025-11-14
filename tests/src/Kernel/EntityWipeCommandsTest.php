<?php

namespace Drupal\Tests\db_wipe\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests for entity wipe commands.
 *
 * @group db_wipe
 */
class EntityWipeCommandsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'taxonomy',
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
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'taxonomy']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
  }

  /**
   * Tests basic entity creation and query building.
   */
  public function testEntityCreation(): void {
    $node1 = Node::create(['type' => 'article', 'title' => 'Test Article 1']);
    $node1->save();

    $node2 = Node::create(['type' => 'page', 'title' => 'Test Page 1']);
    $node2->save();

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE);
    $count = $query->count()->execute();

    $this->assertEquals(2, $count, 'Two nodes were created.');
  }

  /**
   * Tests bundle filtering.
   */
  public function testBundleFiltering(): void {
    for ($i = 1; $i <= 3; $i++) {
      Node::create(['type' => 'article', 'title' => "Article $i"])->save();
    }

    for ($i = 1; $i <= 2; $i++) {
      Node::create(['type' => 'page', 'title' => "Page $i"])->save();
    }

    $storage = $this->container->get('entity_type.manager')->getStorage('node');

    $article_query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'article');
    $article_count = $article_query->count()->execute();

    $page_query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'page');
    $page_count = $page_query->count()->execute();

    $this->assertEquals(3, $article_count, 'Three articles created.');
    $this->assertEquals(2, $page_count, 'Two pages created.');
  }

  /**
   * Tests exclude IDs filtering.
   */
  public function testExcludeIdsFiltering(): void {
    $nodes = [];
    for ($i = 1; $i <= 5; $i++) {
      $node = Node::create(['type' => 'article', 'title' => "Article $i"]);
      $node->save();
      $nodes[] = $node->id();
    }

    $exclude_ids = [$nodes[0], $nodes[2]];

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('nid', $exclude_ids, 'NOT IN');

    $results = $query->execute();

    $this->assertCount(3, $results, 'Three nodes remain after excluding two IDs.');
    $this->assertNotContains($nodes[0], $results);
    $this->assertNotContains($nodes[2], $results);
  }

  /**
   * Tests include IDs filtering.
   */
  public function testIncludeIdsFiltering(): void {
    $nodes = [];
    for ($i = 1; $i <= 5; $i++) {
      $node = Node::create(['type' => 'article', 'title' => "Article $i"]);
      $node->save();
      $nodes[] = $node->id();
    }

    $include_ids = [$nodes[1], $nodes[3]];

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('nid', $include_ids, 'IN');

    $results = $query->execute();

    $this->assertCount(2, $results, 'Only two nodes match include IDs.');
    $this->assertContains($nodes[1], $results);
    $this->assertContains($nodes[3], $results);
  }

  /**
   * Tests entity deletion.
   */
  public function testEntityDeletion(): void {
    $nodes = [];
    for ($i = 1; $i <= 3; $i++) {
      $node = Node::create(['type' => 'article', 'title' => "Article $i"]);
      $node->save();
      $nodes[] = $node;
    }

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->delete($nodes);

    $query = $storage->getQuery()->accessCheck(FALSE);
    $count = $query->count()->execute();

    $this->assertEquals(0, $count, 'All nodes were deleted.');
  }

  /**
   * Tests before wipe event.
   */
  public function testBeforeWipeEvent(): void {
    $state = $this->container->get('state');
    $state->set('db_wipe_test.events', []);

    for ($i = 1; $i <= 3; $i++) {
      Node::create(['type' => 'article', 'title' => "Article $i"])->save();
    }

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'article');
    $count = clone $query;
    $total = $count->count()->execute();

    $event = new \Drupal\db_wipe_entity\Event\BeforeEntityWipeEvent('node', $query, $total, 'article', FALSE);
    $this->container->get('event_dispatcher')->dispatch($event, \Drupal\db_wipe_entity\Event\EntityWipeEvents::BEFORE_WIPE);

    $events = $state->get('db_wipe_test.events', []);
    $this->assertCount(1, $events, 'One event was recorded.');
    $this->assertEquals('before_wipe', $events[0]['event']);
    $this->assertEquals('node', $events[0]['entity_type']);
    $this->assertEquals('article', $events[0]['bundle']);
    $this->assertEquals(3, $events[0]['count']);
  }

  /**
   * Tests after wipe event.
   */
  public function testAfterWipeEvent(): void {
    $state = $this->container->get('state');
    $state->set('db_wipe_test.events', []);

    $event = new \Drupal\db_wipe_entity\Event\AfterEntityWipeEvent('node', 5, 'article', TRUE);
    $this->container->get('event_dispatcher')->dispatch($event, \Drupal\db_wipe_entity\Event\EntityWipeEvents::AFTER_WIPE);

    $events = $state->get('db_wipe_test.events', []);
    $this->assertCount(1, $events, 'One event was recorded.');
    $this->assertEquals('after_wipe', $events[0]['event']);
    $this->assertEquals('node', $events[0]['entity_type']);
    $this->assertEquals('article', $events[0]['bundle']);
    $this->assertEquals(5, $events[0]['deleted_count']);
    $this->assertTrue($events[0]['success']);
  }

  /**
   * Tests batch process event.
   */
  public function testBatchProcessEvent(): void {
    $state = $this->container->get('state');
    $state->set('db_wipe_test.events', []);

    $deleted_ids = [1, 2, 3];
    $event = new \Drupal\db_wipe_entity\Event\EntityWipeBatchEvent('node', $deleted_ids, 3, 'article');
    $this->container->get('event_dispatcher')->dispatch($event, \Drupal\db_wipe_entity\Event\EntityWipeEvents::BATCH_PROCESS);

    $events = $state->get('db_wipe_test.events', []);
    $this->assertCount(1, $events, 'One event was recorded.');
    $this->assertEquals('batch_process', $events[0]['event']);
    $this->assertEquals('node', $events[0]['entity_type']);
    $this->assertEquals('article', $events[0]['bundle']);
    $this->assertEquals([1, 2, 3], $events[0]['deleted_ids']);
    $this->assertEquals(3, $events[0]['last_id']);
  }

  /**
   * Tests prevent wipe functionality.
   */
  public function testPreventWipe(): void {
    $state = $this->container->get('state');
    $state->set('db_wipe_test.events', []);
    $state->set('db_wipe_test.prevent_wipe', TRUE);

    for ($i = 1; $i <= 3; $i++) {
      Node::create(['type' => 'article', 'title' => "Article $i"])->save();
    }

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'article');
    $count = clone $query;
    $total = $count->count()->execute();

    $event = new \Drupal\db_wipe_entity\Event\BeforeEntityWipeEvent('node', $query, $total, 'article', FALSE);
    $this->container->get('event_dispatcher')->dispatch($event, \Drupal\db_wipe_entity\Event\EntityWipeEvents::BEFORE_WIPE);

    $this->assertTrue($event->isWipePrevented(), 'Wipe was prevented by event subscriber.');

    $state->set('db_wipe_test.prevent_wipe', FALSE);
  }

  /**
   * Tests taxonomy term operations.
   */
  public function testTaxonomyTermOperations(): void {
    for ($i = 1; $i <= 5; $i++) {
      Term::create([
        'vid' => 'tags',
        'name' => "Tag $i",
      ])->save();
    }

    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $query = $storage->getQuery()->accessCheck(FALSE);
    $count = $query->count()->execute();

    $this->assertEquals(5, $count, 'Five taxonomy terms were created.');

    $bundle_query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'tags');
    $bundle_count = $bundle_query->count()->execute();

    $this->assertEquals(5, $bundle_count, 'All terms are in tags vocabulary.');
  }

}
