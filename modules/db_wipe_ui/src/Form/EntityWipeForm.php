<?php

namespace Drupal\db_wipe_ui\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\db_wipe_entity\Service\EntityWipeService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for selecting entities to wipe.
 */
class EntityWipeForm extends FormBase {

  /**
   * Constructs an EntityWipeForm object.
   *
   * @param \Drupal\db_wipe_entity\Service\EntityWipeService $entityWipeService
   *   The entity wipe service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityWipeService $entityWipeService,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('db_wipe_entity.entity_wipe'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'db_wipe_ui_entity_wipe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'db_wipe_ui/db_wipe_ui';
    $form['#attributes']['class'][] = 'db-wipe-ui-form';

    $form['warning'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--warning']],
    ];

    $form['warning']['message'] = [
      '#markup' => '<strong>' . $this->t('⚠️ WARNING: This tool permanently deletes entities from the database. Deletion cannot be undone. Always create a database backup before using this tool.') . '</strong>',
    ];

    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity Type'),
      '#options' => $this->getEntityTypeOptions(),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateBundleOptions',
        'wrapper' => 'bundle-wrapper',
      ],
    ];

    $form['bundle_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'bundle-wrapper'],
    ];

    $selected_entity_type = $form_state->getValue('entity_type') ?? 'node';
    $bundle_options = $this->getBundleOptions($selected_entity_type);

    if ($selected_entity_type === 'user') {
      $form['bundle_wrapper']['user_protection'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--status']],
      ];
      $form['bundle_wrapper']['user_protection']['message'] = [
        '#markup' => '<strong>🛡️ ' . $this->t('User ID 1 (admin) is automatically protected and will NOT be deleted.') . '</strong>',
      ];
    }

    if (!empty($bundle_options)) {
      $form['bundle_wrapper']['bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Bundle/Type'),
        '#options' => ['' => $this->t('- All -')] + $bundle_options,
        '#description' => $this->t('Optional: Filter by specific bundle type.'),
      ];
    }

    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filters'),
      '#open' => FALSE,
    ];

    $form['filters']['exclude_ids'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Exclude IDs'),
      '#description' => $this->t('Comma-separated entity IDs to exclude from deletion. Example: 1,2,3'),
    ];

    $form['filters']['include_ids'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Include Only These IDs'),
      '#description' => $this->t('Comma-separated entity IDs to delete (only these will be deleted). Example: 100,101,102'),
    ];

    $form['dry_run'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Dry Run (Preview Only)'),
      '#description' => $this->t('Check this to preview what will be deleted without actually deleting. <strong>HIGHLY RECOMMENDED</strong> before actual deletion.'),
      '#default_value' => TRUE,
    ];

    $form['use_batch'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use Batch Processing'),
      '#description' => $this->t('Process deletion in batches (recommended for large datasets). Uncheck to delete immediately (only for small datasets).'),
      '#default_value' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['preview'] = [
      '#type' => 'submit',
      '#value' => $this->t('Preview Deletion'),
      '#submit' => ['::submitPreview'],
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * AJAX callback to update bundle options.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The bundle wrapper form element.
   */
  public function updateBundleOptions(array &$form, FormStateInterface $form_state): array {
    return $form['bundle_wrapper'];
  }

  /**
   * Gets entity type options for the select field.
   *
   * @return array
   *   Array of entity type options.
   */
  protected function getEntityTypeOptions(): array {
    $options = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $definition) {
      // Only include content entity types
      if ($definition->entityClassImplements('Drupal\Core\Entity\ContentEntityInterface')) {
        $options[$entity_type_id] = $definition->getLabel();
      }
    }

    // Sort by label
    asort($options);

    return $options;
  }

  /**
   * Gets bundle options for entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   Array of bundle options.
   */
  protected function getBundleOptions(string $entity_type_id): array {
    $bundles = [];

    try {
      $bundle_entity_type = $this->entityTypeManager
        ->getDefinition($entity_type_id)
        ->getBundleEntityType();

      if ($bundle_entity_type) {
        $bundle_entities = $this->entityTypeManager
          ->getStorage($bundle_entity_type)
          ->loadMultiple();

        foreach ($bundle_entities as $bundle) {
          $bundles[$bundle->id()] = $bundle->label();
        }
      }
    }
    catch (\Exception $e) {
      // Entity type doesn't have bundles.
    }

    return $bundles;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $entity_type = $form_state->getValue('entity_type');
    $bundle = $form_state->getValue('bundle') ?: NULL;
    $exclude_ids = $this->parseIds($form_state->getValue('exclude_ids'));
    $include_ids = $this->parseIds($form_state->getValue('include_ids'));

    if ($exclude_ids && $include_ids) {
      $form_state->setErrorByName('filters', $this->t('You cannot use both "Exclude IDs" and "Include Only These IDs" at the same time.'));
      return;
    }

    $query = $this->entityWipeService->buildQuery(
      $entity_type,
      $bundle,
      $exclude_ids,
      $include_ids
    );

    $count = $this->entityWipeService->countEntities($query);

    if ($count === 0) {
      $form_state->setErrorByName('entity_type', $this->t('No entities found matching the criteria.'));
    }

    $form_state->set('entity_count', $count);
    $form_state->set('entity_ids', $this->entityWipeService->getEntityIds($query));
  }

  /**
   * Submit handler for preview.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitPreview(array &$form, FormStateInterface $form_state): void {
    $entity_type = $form_state->getValue('entity_type');
    $bundle = $form_state->getValue('bundle') ?: NULL;
    $exclude_ids = $this->parseIds($form_state->getValue('exclude_ids'));
    $include_ids = $this->parseIds($form_state->getValue('include_ids'));
    $dry_run = (bool) $form_state->getValue('dry_run');

    if ($dry_run) {
      $count = $form_state->get('entity_count');
      $ids = $form_state->get('entity_ids');

      $this->messenger()->addWarning(
        $this->t('[DRY RUN] Would delete @count @type entities.', [
          '@count' => $count,
          '@type' => $entity_type,
        ])
      );

      $preview_ids = array_slice($ids, 0, 100);
      $this->messenger()->addStatus(
        $this->t('Preview of entity IDs (showing first 100): @ids', [
          '@ids' => implode(', ', $preview_ids),
        ])
      );

      if ($count > 100) {
        $this->messenger()->addStatus($this->t('... and @more more entities.', ['@more' => $count - 100]));
      }
    }
    else {
      $tempstore = \Drupal::service('tempstore.private')->get('db_wipe_ui');
      $tempstore->set('entity_type', $entity_type);
      $tempstore->set('bundle', $bundle);
      $tempstore->set('exclude_ids', $exclude_ids);
      $tempstore->set('include_ids', $include_ids);
      $tempstore->set('entity_count', $form_state->get('entity_count'));
      $tempstore->set('use_batch', (bool) $form_state->getValue('use_batch'));

      $form_state->setRedirect('db_wipe_ui.confirm');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handled by submitPreview.
  }

  /**
   * Parses comma-separated IDs.
   *
   * @param string|null $ids
   *   Comma-separated string of IDs.
   *
   * @return array|null
   *   Array of IDs or NULL.
   */
  protected function parseIds(?string $ids): ?array {
    if (empty($ids)) {
      return NULL;
    }

    return array_filter(array_map('trim', explode(',', $ids)));
  }

}
