<?php

namespace Drupal\db_wipe_entity\Drush\Commands;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drush\Attributes as CLI;

/**
 * Drush commands for wiping entities from database.
 */
final class EntityWipeCommands extends WipeCommandsBase {

  /**
   * The current entity type ID being processed.
   */
  protected string $entityTypeId;

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId(): string {
    return $this->entityTypeId;
  }

  /**
   * Wipe entities from database.
   *
   * Automatically discovers all content entity types in the system.
   * User ID 1 is automatically protected from deletion.
   */
  #[CLI\Command(name: 'db:wipe-entities', aliases: ['dbw'])]
  #[CLI\Argument(name: 'entity_type', description: 'Entity type to delete (autocompletes available types)')]
  #[CLI\Option(name: 'bundle', description: 'Bundle/type to filter (e.g., article, page)')]
  #[CLI\Option(name: 'exclude-ids', description: 'Comma-separated IDs to exclude from deletion')]
  #[CLI\Option(name: 'include-ids', description: 'Comma-separated IDs to include (only delete these)')]
  #[CLI\Option(name: 'dry-run', description: 'Simulate deletion without actually deleting')]
  #[CLI\Option(name: 'no-batch', description: 'Delete immediately without batch processing (use for small datasets)')]
  #[CLI\Usage(name: 'db:wipe-entities node --bundle=article', description: 'Delete all article nodes')]
  #[CLI\Usage(name: 'db:wipe-entities node --exclude-ids=1,2,3', description: 'Delete all nodes except IDs 1, 2, 3')]
  #[CLI\Usage(name: 'db:wipe-entities taxonomy_term --bundle=tags --yes', description: 'Delete all tags without confirmation')]
  #[CLI\Usage(name: 'db:wipe-entities user', description: 'Delete all users (User ID 1 is automatically protected)')]
  #[CLI\Usage(name: 'db:wipe-entities node --dry-run', description: 'Preview nodes to be deleted')]
  #[CLI\Usage(name: 'db:wipe-entities node --no-batch', description: 'Delete nodes immediately without batch processing')]
  #[CLI\Complete(method_name_or_callable: 'completeEntityTypes')]
  public function wipeEntities(
    string $entity_type,
    array $options = [
      'bundle' => NULL,
      'exclude-ids' => NULL,
      'include-ids' => NULL,
      'dry-run' => FALSE,
      'no-batch' => FALSE,
    ]
  ): void {
    // Validate entity type exists and is a content entity.
    if (!$this->entityTypeManager->hasDefinition($entity_type)) {
      $this->logger()->error("Entity type '{$entity_type}' does not exist.");
      $this->showAvailableEntityTypes();
      return;
    }

    $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type);
    if (!$entity_type_definition instanceof ContentEntityTypeInterface) {
      $this->logger()->error("Entity type '{$entity_type}' is not a content entity type.");
      $this->showAvailableEntityTypes();
      return;
    }

    $this->entityTypeId = $entity_type;

    $exclude_ids = $options['exclude-ids'] ? array_map('trim', explode(',', $options['exclude-ids'])) : NULL;
    $include_ids = $options['include-ids'] ? array_map('trim', explode(',', $options['include-ids'])) : NULL;

    $label_parts = [$entity_type];
    if ($options['bundle']) {
      $label_parts[] = '(' . $options['bundle'] . ')';
    }
    if ($exclude_ids) {
      $label_parts[] = 'excluding IDs: ' . implode(', ', $exclude_ids);
    }
    if ($include_ids) {
      $label_parts[] = 'only IDs: ' . implode(', ', $include_ids);
    }

    $this->executeWipe(
      implode(' ', $label_parts),
      $options['bundle'],
      $exclude_ids,
      $include_ids,
      $this->input()->getOption('yes'),
      $options['dry-run'],
      $options['no-batch']
    );
  }

  /**
   * Autocomplete callback for entity types.
   */
  public function completeEntityTypes(): array {
    $entity_types = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $definition) {
      if ($definition instanceof ContentEntityTypeInterface) {
        $entity_types[] = $entity_type_id;
      }
    }
    return $entity_types;
  }

  /**
   * Shows available entity types.
   */
  protected function showAvailableEntityTypes(): void {
    $entity_types = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $definition) {
      if ($definition instanceof ContentEntityTypeInterface) {
        $entity_types[] = sprintf('%s (%s)', $entity_type_id, $definition->getLabel());
      }
    }

    $this->logger()->notice('Available content entity types:');
    $this->io()->listing($entity_types);
  }

}
