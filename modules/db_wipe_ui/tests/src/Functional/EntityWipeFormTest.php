<?php

namespace Drupal\Tests\db_wipe_ui\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Tests for the Entity Wipe form.
 *
 * @group db_wipe_ui
 */
class EntityWipeFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'field',
    'text',
    'db_wipe',
    'db_wipe_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Admin user with wipe permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * User without wipe permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $normalUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create content types.
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    // Create admin user with permission.
    $this->adminUser = $this->drupalCreateUser([
      'administer entity wipe',
      'access administration pages',
    ]);

    // Create normal user without permission.
    $this->normalUser = $this->drupalCreateUser([
      'access administration pages',
    ]);
  }

  /**
   * Tests form access with permission.
   */
  public function testFormAccessWithPermission() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Entity Wipe');
  }

  /**
   * Tests form access without permission.
   */
  public function testFormAccessWithoutPermission() {
    $this->drupalLogin($this->normalUser);
    $this->drupalGet('/admin/config/development/entity-wipe');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests anonymous access denied.
   */
  public function testAnonymousAccessDenied() {
    $this->drupalGet('/admin/config/development/entity-wipe');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests form rendering and elements.
   */
  public function testFormRendering() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Check warning message.
    $this->assertSession()->pageTextContains('WARNING: This tool permanently deletes entities');

    // Check entity type field (auto-discovers all content entities).
    $this->assertSession()->fieldExists('entity_type');
    $this->assertSession()->optionExists('entity_type', 'node');
    $this->assertSession()->optionExists('entity_type', 'user');

    // Check dry run checkbox.
    $this->assertSession()->fieldExists('dry_run');
    $this->assertSession()->checkboxChecked('dry_run');

    // Check batch processing checkbox.
    $this->assertSession()->fieldExists('use_batch');
    $this->assertSession()->checkboxChecked('use_batch');

    // Check submit button.
    $this->assertSession()->buttonExists('Preview Deletion');
  }

  /**
   * Tests user protection message display.
   */
  public function testUserProtectionMessage() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Select user entity type via AJAX.
    $this->submitForm(['entity_type' => 'user'], 'entity_type');

    // Check for protection message.
    $this->assertSession()->pageTextContains('User ID 1 (admin) is automatically protected');
  }

  /**
   * Tests dry run preview functionality.
   */
  public function testDryRunPreview() {
    // Create test nodes.
    for ($i = 1; $i <= 5; $i++) {
      Node::create([
        'type' => 'article',
        'title' => "Test Article $i",
      ])->save();
    }

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Submit form with dry run enabled.
    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'dry_run' => TRUE,
    ], 'Preview Deletion');

    // Check for dry run message.
    $this->assertSession()->pageTextContains('[DRY RUN] Would delete 5 node entities');
    $this->assertSession()->pageTextContains('Preview of entity IDs');
  }

  /**
   * Tests validation error when no entities found.
   */
  public function testValidationNoEntitiesFound() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Submit with non-existent bundle.
    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'nonexistent',
      'dry_run' => TRUE,
    ], 'Preview Deletion');

    $this->assertSession()->pageTextContains('No entities found matching the criteria');
  }

  /**
   * Tests exclude IDs functionality.
   */
  public function testExcludeIds() {
    // Create test nodes.
    $node_ids = [];
    for ($i = 1; $i <= 5; $i++) {
      $node = Node::create([
        'type' => 'article',
        'title' => "Test Article $i",
      ]);
      $node->save();
      $node_ids[] = $node->id();
    }

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Exclude first two nodes.
    $exclude = implode(',', array_slice($node_ids, 0, 2));

    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'exclude_ids' => $exclude,
      'dry_run' => TRUE,
    ], 'Preview Deletion');

    // Should only show 3 nodes.
    $this->assertSession()->pageTextContains('[DRY RUN] Would delete 3 node entities');
  }

  /**
   * Tests include IDs functionality.
   */
  public function testIncludeIds() {
    // Create test nodes.
    $node_ids = [];
    for ($i = 1; $i <= 5; $i++) {
      $node = Node::create([
        'type' => 'article',
        'title' => "Test Article $i",
      ]);
      $node->save();
      $node_ids[] = $node->id();
    }

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Include only first two nodes.
    $include = implode(',', array_slice($node_ids, 0, 2));

    $this->submitForm([
      'entity_type' => 'node',
      'include_ids' => $include,
      'dry_run' => TRUE,
    ], 'Preview Deletion');

    // Should only show 2 nodes.
    $this->assertSession()->pageTextContains('[DRY RUN] Would delete 2 node entities');
  }

  /**
   * Tests validation error when using both exclude and include.
   */
  public function testExcludeAndIncludeValidationError() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    $this->submitForm([
      'entity_type' => 'node',
      'exclude_ids' => '1,2,3',
      'include_ids' => '4,5,6',
      'dry_run' => TRUE,
    ], 'Preview Deletion');

    $this->assertSession()->pageTextContains('You cannot use both "Exclude IDs" and "Include Only These IDs" at the same time');
  }

  /**
   * Tests user ID 1 protection.
   */
  public function testUserIdOneProtection() {
    // Create additional test users.
    for ($i = 1; $i <= 3; $i++) {
      User::create([
        'name' => "testuser$i",
        'mail' => "testuser$i@example.com",
        'status' => 1,
      ])->save();
    }

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Try to delete all users (should exclude ID 1).
    $this->submitForm([
      'entity_type' => 'user',
      'dry_run' => TRUE,
    ], 'Preview Deletion');

    // Should not include user 1 in count.
    // We have: admin (1), adminUser (created in setUp), normalUser, testuser1, testuser2, testuser3
    // User 1 should be excluded, so we should see 5 users.
    $this->assertSession()->pageTextContains('[DRY RUN] Would delete');
    $this->assertSession()->pageTextNotContains('entity IDs (showing first 100): 1');
  }

  /**
   * Tests redirect to confirmation form when dry run is disabled.
   */
  public function testRedirectToConfirmation() {
    // Create test node.
    Node::create(['type' => 'article', 'title' => 'Test Article'])->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Submit with dry run disabled.
    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'dry_run' => FALSE,
    ], 'Preview Deletion');

    // Should redirect to confirmation page.
    $this->assertSession()->addressEquals('/admin/config/development/entity-wipe/confirm');
  }

  /**
   * Tests bundle options change with entity type.
   */
  public function testBundleOptionsChange() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Check that bundle field appears for nodes.
    $this->submitForm(['entity_type' => 'node'], 'entity_type');
    $this->assertSession()->fieldExists('bundle');
    $this->assertSession()->optionExists('bundle', 'article');
    $this->assertSession()->optionExists('bundle', 'page');
  }

  /**
   * Tests batch processing option.
   */
  public function testBatchProcessingOption() {
    Node::create(['type' => 'article', 'title' => 'Test Article'])->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Test with batch processing enabled (default).
    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'dry_run' => FALSE,
      'use_batch' => TRUE,
    ], 'Preview Deletion');

    // Should redirect to confirmation.
    $this->assertSession()->addressEquals('/admin/config/development/entity-wipe/confirm');
    $this->assertSession()->pageTextContains('Batch Processing');
  }

  /**
   * Tests immediate deletion option (no batch).
   */
  public function testImmediateDeletionOption() {
    Node::create(['type' => 'article', 'title' => 'Test Article'])->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Test with batch processing disabled.
    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'dry_run' => FALSE,
      'use_batch' => FALSE,
    ], 'Preview Deletion');

    // Should redirect to confirmation.
    $this->assertSession()->addressEquals('/admin/config/development/entity-wipe/confirm');
    $this->assertSession()->pageTextContains('Immediate Deletion');
  }

}
