<?php

namespace Drupal\db_wipe_entity\Drush\Commands;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\db_wipe_entity\Service\EntityWipeService;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for entity wipe commands.
 */
abstract class WipeCommandsBase extends DrushCommands {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * Constructs a WipeCommandsBase object.
   *
   * @param \Drupal\db_wipe_entity\Service\EntityWipeService $entityWipeService
   *   The entity wipe service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected readonly EntityWipeService $entityWipeService,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('db_wipe_entity.entity_wipe'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Gets the current entity type ID being processed.
   *
   * @return string
   *   The entity type ID.
   */
  abstract public function getEntityTypeId(): string;

  /**
   * Executes entity wipe operation with Drush UI.
   *
   * @param string $label
   *   Label describing the entities being deleted.
   * @param string|null $bundle
   *   The bundle to filter by.
   * @param array|null $excludeIds
   *   Entity IDs to exclude from deletion.
   * @param array|null $includeIds
   *   Entity IDs to include (only delete these).
   * @param bool $skipConfirmation
   *   Whether to skip the confirmation prompt.
   * @param bool $dryRun
   *   Whether this is a dry run (preview only).
   * @param bool $noBatch
   *   Whether to skip batch processing and delete immediately.
   */
  protected function executeWipe(
    string $label,
    ?string $bundle = NULL,
    ?array $excludeIds = NULL,
    ?array $includeIds = NULL,
    bool $skipConfirmation = FALSE,
    bool $dryRun = FALSE,
    bool $noBatch = FALSE
  ): void {
    $entity_type_id = $this->getEntityTypeId();

    $query = $this->entityWipeService->buildQuery(
      $entity_type_id,
      $bundle,
      $excludeIds,
      $includeIds
    );

    $total = $this->entityWipeService->countEntities($query);

    if (!$total) {
      $this->io()->success($this->t('No entities to delete.'));
      return;
    }

    if (!$dryRun && !$skipConfirmation) {
      $this->displayWarning($total, $label, $entity_type_id);

      if (!$this->io()->confirm($this->t('Type "yes" to confirm permanent deletion of @count @label', [
        '@count' => $total,
        '@label' => $label,
      ]))) {
        throw new UserAbortException();
      }
    }

    if ($dryRun) {
      $ids = $this->entityWipeService->getEntityIds($query);
      $this->io()->success($this->t('[DRY RUN] Would delete @count entities: @label', [
        '@count' => $total,
        '@label' => $label,
      ]));
      $this->io()->text($this->t('Entity IDs: @ids', ['@ids' => implode(', ', $ids)]));
      return;
    }

    if ($noBatch) {
      // Delete immediately without batch processing
      $ids = $this->entityWipeService->getEntityIds($query);
      $deleted = $this->entityWipeService->deleteEntitiesImmediate($entity_type_id, $ids);
      $this->io()->success($this->t('Deleted @count entities immediately.', ['@count' => $deleted]));
      return;
    }

    $result = $this->entityWipeService->executeWipe($entity_type_id, $query, $bundle, $dryRun, $excludeIds, $includeIds);

    if ($result['prevented']) {
      $this->io()->warning($this->t('Wipe operation was prevented by event subscriber.'));
      return;
    }

    drush_backend_batch_process();
  }

  /**
   * Displays critical warning for entity deletion.
   *
   * @param int $total
   *   Total number of entities to be deleted.
   * @param string $label
   *   Label describing the entities.
   * @param string $entityTypeId
   *   The entity type ID.
   */
  protected function displayWarning(int $total, string $label, string $entityTypeId): void {
    $this->io()->warning('═══════════════════════════════════════════════════════════');
    $this->io()->warning('                    ⚠️  CRITICAL WARNING  ⚠️');
    $this->io()->warning('═══════════════════════════════════════════════════════════');
    $this->io()->warning('');
    $this->io()->warning((string) $this->t('You are about to PERMANENTLY DELETE @count entities:', ['@count' => $total]));
    $this->io()->warning((string) $this->t('Type: @label', ['@label' => $label]));
    $this->io()->warning('');
    $this->io()->warning((string) $this->t('⚠️  THIS ACTION CANNOT BE UNDONE!'));
    $this->io()->warning((string) $this->t('⚠️  DELETED DATA CANNOT BE RECOVERED!'));
    $this->io()->warning((string) $this->t('⚠️  MAKE SURE YOU HAVE A BACKUP!'));
    $this->io()->warning('');
    $this->io()->warning('═══════════════════════════════════════════════════════════');

    if ($entityTypeId === 'user') {
      $this->io()->error((string) $this->t('⚠️  WARNING: Deleting users may affect system access!'));
      $this->io()->success((string) $this->t('🛡️  User ID 1 (admin) is automatically protected and will NOT be deleted.'));
    }
  }

}
