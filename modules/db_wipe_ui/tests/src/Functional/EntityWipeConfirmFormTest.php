<?php

namespace Drupal\Tests\db_wipe_ui\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests for the Entity Wipe confirmation form.
 *
 * @group db_wipe_ui
 */
class EntityWipeConfirmFormTest extends BrowserTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->adminUser = $this->drupalCreateUser([
      'administer entity wipe',
      'access administration pages',
    ]);
  }

  /**
   * Tests confirmation form rendering.
   */
  public function testConfirmationFormRendering() {
    // Create test nodes.
    for ($i = 1; $i <= 3; $i++) {
      Node::create([
        'type' => 'article',
        'title' => "Test Article $i",
      ])->save();
    }

    $this->drupalLogin($this->adminUser);

    // Navigate to main form.
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Submit to get to confirmation.
    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'dry_run' => FALSE,
    ], 'Preview Deletion');

    // Check confirmation form elements.
    $this->assertSession()->pageTextContains('Are you absolutely sure');
    $this->assertSession()->pageTextContains('CRITICAL WARNING');
    $this->assertSession()->pageTextContains('THIS ACTION CANNOT BE UNDONE');
    $this->assertSession()->pageTextContains('3 node entities');

    // Check confirmation field exists.
    $this->assertSession()->fieldExists('confirmation_text');

    // Check buttons.
    $this->assertSession()->buttonExists('Yes, DELETE permanently');
    $this->assertSession()->linkExists('Cancel (Go back)');
  }

  /**
   * Tests session data display in confirmation.
   */
  public function testSessionDataDisplay() {
    Node::create(['type' => 'article', 'title' => 'Test'])->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Submit with specific filters.
    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'exclude_ids' => '999',
      'dry_run' => FALSE,
    ], 'Preview Deletion');

    // Check summary displays correctly.
    $this->assertSession()->pageTextContains('Entity Type: node');
    $this->assertSession()->pageTextContains('Bundle: article');
    $this->assertSession()->pageTextContains('Excluding IDs: 999');
  }

  /**
   * Tests validation requires DELETE text.
   */
  public function testConfirmationTextRequired() {
    Node::create(['type' => 'article', 'title' => 'Test'])->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'dry_run' => FALSE,
    ], 'Preview Deletion');

    // Try to submit without typing DELETE.
    $this->submitForm([
      'confirmation_text' => '',
    ], 'Yes, DELETE permanently');

    $this->assertSession()->pageTextContains('field is required');
  }

  /**
   * Tests validation requires exact DELETE text.
   */
  public function testConfirmationTextMustBeExact() {
    Node::create(['type' => 'article', 'title' => 'Test'])->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'dry_run' => FALSE,
    ], 'Preview Deletion');

    // Try with wrong text.
    $this->submitForm([
      'confirmation_text' => 'delete',
    ], 'Yes, DELETE permanently');

    $this->assertSession()->pageTextContains('You must type DELETE exactly');

    // Try with correct text.
    $this->submitForm([
      'confirmation_text' => 'DELETE',
    ], 'Yes, DELETE permanently');

    // Should proceed to deletion.
    $this->assertSession()->pageTextContains('Deleting');
  }

  /**
   * Tests user protection message in confirmation.
   */
  public function testUserProtectionInConfirmation() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    $this->submitForm([
      'entity_type' => 'user',
      'dry_run' => FALSE,
    ], 'Preview Deletion');

    // Check for user protection warning.
    $this->assertSession()->pageTextContains('DELETING USERS MAY AFFECT SYSTEM ACCESS');
    $this->assertSession()->pageTextContains('User ID 1 (admin) is automatically protected');
  }

  /**
   * Tests cancel link returns to main form.
   */
  public function testCancelLink() {
    Node::create(['type' => 'article', 'title' => 'Test'])->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'dry_run' => FALSE,
    ], 'Preview Deletion');

    // Click cancel.
    $this->clickLink('Cancel (Go back)');

    // Should return to main form.
    $this->assertSession()->addressEquals('/admin/config/development/entity-wipe');
  }

  /**
   * Tests session expiration handling.
   */
  public function testSessionExpiration() {
    $this->drupalLogin($this->adminUser);

    // Try to access confirmation without session data.
    $this->drupalGet('/admin/config/development/entity-wipe/confirm');

    // Should show error and redirect.
    $this->assertSession()->pageTextContains('Session expired');
  }

  /**
   * Tests successful deletion flow.
   */
  public function testSuccessfulDeletion() {
    // Create test nodes.
    for ($i = 1; $i <= 3; $i++) {
      Node::create([
        'type' => 'article',
        'title' => "Test Article $i",
      ])->save();
    }

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Go through full flow.
    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'dry_run' => FALSE,
    ], 'Preview Deletion');

    $this->submitForm([
      'confirmation_text' => 'DELETE',
    ], 'Yes, DELETE permanently');

    // Should show success message and redirect.
    $this->assertSession()->pageTextContains('Deleting 3 entities');
    $this->assertSession()->addressEquals('/admin/config/development/entity-wipe');

    // Verify nodes were actually deleted (in a real scenario).
    // Note: In browser tests, batch processing might not complete immediately.
  }

  /**
   * Tests deletion summary shows all parameters.
   */
  public function testDeletionSummaryComplete() {
    // Create nodes.
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

    // Submit with all filter options.
    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'exclude_ids' => implode(',', array_slice($node_ids, 0, 2)),
      'dry_run' => FALSE,
    ], 'Preview Deletion');

    // Check all details are shown.
    $this->assertSession()->pageTextContains('Entity Type: node');
    $this->assertSession()->pageTextContains('Bundle: article');
    $this->assertSession()->pageTextContains('Excluding IDs:');
    $this->assertSession()->pageTextContains('Total Count: 3');
  }

  /**
   * Tests include IDs shown in summary.
   */
  public function testIncludeIdsSummary() {
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

    $this->submitForm([
      'entity_type' => 'node',
      'include_ids' => implode(',', array_slice($node_ids, 0, 2)),
      'dry_run' => FALSE,
    ], 'Preview Deletion');

    $this->assertSession()->pageTextContains('Only IDs:');
  }

  /**
   * Tests batch processing mode shown in confirmation.
   */
  public function testBatchProcessingModeInConfirmation() {
    Node::create(['type' => 'article', 'title' => 'Test'])->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Submit with batch processing enabled.
    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'dry_run' => FALSE,
      'use_batch' => TRUE,
    ], 'Preview Deletion');

    // Should show batch processing mode.
    $this->assertSession()->pageTextContains('Processing Mode: Batch Processing');
  }

  /**
   * Tests immediate deletion mode shown in confirmation.
   */
  public function testImmediateDeletionModeInConfirmation() {
    Node::create(['type' => 'article', 'title' => 'Test'])->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Submit with batch processing disabled.
    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'dry_run' => FALSE,
      'use_batch' => FALSE,
    ], 'Preview Deletion');

    // Should show immediate deletion mode.
    $this->assertSession()->pageTextContains('Processing Mode: Immediate Deletion');
  }

  /**
   * Tests immediate deletion executes without batch.
   */
  public function testImmediateDeletionExecution() {
    // Create test nodes.
    for ($i = 1; $i <= 2; $i++) {
      Node::create([
        'type' => 'article',
        'title' => "Test Article $i",
      ])->save();
    }

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/development/entity-wipe');

    // Go through full flow with immediate deletion.
    $this->submitForm([
      'entity_type' => 'node',
      'bundle' => 'article',
      'dry_run' => FALSE,
      'use_batch' => FALSE,
    ], 'Preview Deletion');

    $this->submitForm([
      'confirmation_text' => 'DELETE',
    ], 'Yes, DELETE permanently');

    // Should show immediate deletion message.
    $this->assertSession()->pageTextContains('Deleted 2 entities immediately');
  }

}
