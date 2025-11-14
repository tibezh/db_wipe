<?php

namespace Drupal\db_wipe_entity\Plugin\Wipe;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\db_wipe\Plugin\WipePluginBase;
use Drupal\db_wipe_entity\Service\EntityWipeService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides entity wipe functionality using Drupal's entity API.
 *
 * @WipePlugin(
 *   id = "entity_wipe",
 *   label = @Translation("Entity Wipe"),
 *   description = @Translation("Deletes entities using Drupal's entity API with full hooks and events."),
 *   category = "entity",
 *   weight = 0
 * )
 */
class EntityWipePlugin extends WipePluginBase {

  /**
   * Constructs an EntityWipePlugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\db_wipe_entity\Service\EntityWipeService $entityWipeService
   *   The entity wipe service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityWipeService $entityWipeService,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('db_wipe_entity.entity_wipe'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function supports($target): bool {
    // Check if target is a valid content entity type.
    if (!$this->entityTypeManager->hasDefinition($target)) {
      return FALSE;
    }

    $definition = $this->entityTypeManager->getDefinition($target);
    return $definition instanceof ContentEntityTypeInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($target, array $options = []): array {
    $errors = parent::validate($target, $options);

    // Additional validation can be added here.
    if (!empty($options['bundle'])) {
      $entity_type = $this->entityTypeManager->getDefinition($target);
      $bundle_entity_type = $entity_type->getBundleEntityType();

      if (!$bundle_entity_type) {
        $errors[] = $this->t('Entity type @type does not support bundles.', [
          '@type' => $target,
        ]);
      }
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function preview($target, array $options = []): array {
    $bundle = $options['bundle'] ?? NULL;
    $exclude_ids = $options['exclude_ids'] ?? NULL;
    $include_ids = $options['include_ids'] ?? NULL;

    $query = $this->entityWipeService->buildQuery(
      $target,
      $bundle,
      $exclude_ids,
      $include_ids
    );

    $count = $this->entityWipeService->countEntities($query);
    $ids = $this->entityWipeService->getEntityIds($query);

    return [
      'count' => $count,
      'items' => array_slice($ids, 0, 10),
      'details' => [
        'entity_type' => $target,
        'bundle' => $bundle,
        'method' => 'entity_api',
        'batch_processing' => !($options['no_batch'] ?? FALSE),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute($target, array $options = []): array {
    $bundle = $options['bundle'] ?? NULL;
    $exclude_ids = $options['exclude_ids'] ?? NULL;
    $include_ids = $options['include_ids'] ?? NULL;
    $dry_run = $options['dry_run'] ?? FALSE;
    $no_batch = $options['no_batch'] ?? FALSE;

    $query = $this->entityWipeService->buildQuery(
      $target,
      $bundle,
      $exclude_ids,
      $include_ids
    );

    if ($dry_run) {
      $preview = $this->preview($target, $options);
      return [
        'success' => TRUE,
        'count' => $preview['count'],
        'message' => $this->t('[DRY RUN] Would delete @count entities', [
          '@count' => $preview['count'],
        ]),
        'prevented' => FALSE,
      ];
    }

    if ($no_batch) {
      $ids = $this->entityWipeService->getEntityIds($query);
      $deleted = $this->entityWipeService->deleteEntitiesImmediate($target, $ids);

      return [
        'success' => TRUE,
        'count' => $deleted,
        'message' => $this->t('Deleted @count entities immediately.', [
          '@count' => $deleted,
        ]),
        'prevented' => FALSE,
      ];
    }

    $result = $this->entityWipeService->executeWipe(
      $target,
      $query,
      $bundle,
      $dry_run,
      $exclude_ids,
      $include_ids
    );

    return [
      'success' => !$result['prevented'],
      'count' => $result['count'] ?? 0,
      'message' => $result['prevented']
        ? $this->t('Wipe operation was prevented.')
        : $this->t('Batch deletion initiated for @count entities.', ['@count' => $result['count']]),
      'prevented' => $result['prevented'],
    ];
  }

}
