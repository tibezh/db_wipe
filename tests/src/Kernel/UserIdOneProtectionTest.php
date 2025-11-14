<?php

namespace Drupal\Tests\db_wipe\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests for User ID 1 protection event subscriber.
 *
 * @group db_wipe
 */
class UserIdOneProtectionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'db_wipe',
    'db_wipe_entity',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
  }

  /**
   * Tests User ID 1 is excluded from deletion with no filters.
   */
  public function testUserIdOneExcludedNoFilters(): void {
    // Create test users.
    User::create([
      'uid' => 1,
      'name' => 'admin',
      'mail' => 'admin@example.com',
    ])->save();

    for ($i = 2; $i <= 5; $i++) {
      User::create([
        'name' => "user$i",
        'mail' => "user$i@example.com",
      ])->save();
    }

    $wipe_service = $this->container->get('db_wipe_entity.entity_wipe');

    // Build query without any filters - event subscriber should protect ID 1.
    $query = $wipe_service->buildQuery('user');
    $count = $wipe_service->countEntities($query);
    $ids = $wipe_service->getEntityIds($query);

    // Should exclude User ID 1, so only 4 users.
    $this->assertEquals(4, $count, 'User ID 1 should be excluded from query.');
    $this->assertNotContains('1', $ids, 'User ID 1 should not be in results.');
    $this->assertNotContains(1, $ids, 'User ID 1 should not be in results.');
  }

  /**
   * Tests User ID 1 is excluded with include-ids filter.
   */
  public function testUserIdOneExcludedWithIncludeIds(): void {
    User::create([
      'uid' => 1,
      'name' => 'admin',
      'mail' => 'admin@example.com',
    ])->save();

    User::create([
      'uid' => 2,
      'name' => 'user2',
      'mail' => 'user2@example.com',
    ])->save();

    User::create([
      'uid' => 3,
      'name' => 'user3',
      'mail' => 'user3@example.com',
    ])->save();

    $wipe_service = $this->container->get('db_wipe_entity.entity_wipe');

    // Include IDs 1, 2, 3 - but ID 1 should be excluded by event subscriber.
    $query = $wipe_service->buildQuery('user', NULL, NULL, ['1', '2', '3']);
    $ids = $wipe_service->getEntityIds($query);

    // Should only return Users 2 and 3.
    $this->assertCount(2, $ids, 'Should have 2 users after protection.');
    $this->assertNotContains('1', $ids, 'Should not contain User ID 1.');
    $this->assertContains('2', $ids, 'Should contain User ID 2.');
    $this->assertContains('3', $ids, 'Should contain User ID 3.');
  }

  /**
   * Tests User ID 1 is excluded with exclude-ids filter.
   */
  public function testUserIdOneExcludedWithExcludeIds(): void {
    for ($i = 1; $i <= 5; $i++) {
      User::create([
        'uid' => $i,
        'name' => "user$i",
        'mail' => "user$i@example.com",
      ])->save();
    }

    $wipe_service = $this->container->get('db_wipe_entity.entity_wipe');

    // Exclude users 2 and 3 - User 1 should also be excluded by event subscriber.
    $query = $wipe_service->buildQuery('user', NULL, ['2', '3']);
    $ids = $wipe_service->getEntityIds($query);

    // Should exclude 1, 2, and 3, so only 4 and 5.
    $this->assertCount(2, $ids, 'Should have 2 users after exclusions.');
    $this->assertNotContains('1', $ids, 'Should not contain User ID 1.');
    $this->assertNotContains('2', $ids, 'Should not contain User ID 2.');
    $this->assertNotContains('3', $ids, 'Should not contain User ID 3.');
    $this->assertContains('4', $ids, 'Should contain User ID 4.');
    $this->assertContains('5', $ids, 'Should contain User ID 5.');
  }

  /**
   * Tests non-user entities are not affected.
   */
  public function testNonUserEntitiesNotAffected(): void {
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node']);

    // Create a node type.
    $node_type = $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->create([
        'type' => 'article',
        'name' => 'Article',
      ]);
    $node_type->save();

    // Create nodes.
    $node1 = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create([
        'type' => 'article',
        'title' => 'Node 1',
      ]);
    $node1->save();

    $node2 = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create([
        'type' => 'article',
        'title' => 'Node 2',
      ]);
    $node2->save();

    $wipe_service = $this->container->get('db_wipe_entity.entity_wipe');

    // Build query for nodes - should include all nodes.
    $query = $wipe_service->buildQuery('node');
    $count = $wipe_service->countEntities($query);

    // Should include all nodes (protection only applies to users).
    $this->assertEquals(2, $count, 'Node entities should not be affected by User ID 1 protection.');
  }

}
