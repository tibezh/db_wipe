<?php

namespace Drupal\db_wipe_ui\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\db_wipe_entity\Service\EntityWipeService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for entity wipe operation.
 */
class EntityWipeConfirmForm extends ConfirmFormBase {

  /**
   * Constructs an EntityWipeConfirmForm object.
   *
   * @param \Drupal\db_wipe_entity\Service\EntityWipeService $entityWipeService
   *   The entity wipe service.
   */
  public function __construct(
    protected EntityWipeService $entityWipeService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('db_wipe_entity.entity_wipe')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'db_wipe_ui_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    $count = $this->getTempstore()->get('entity_count');
    $entity_type = $this->getTempstore()->get('entity_type');

    return $this->t('Are you absolutely sure you want to PERMANENTLY DELETE @count @type entities?', [
      '@count' => $count,
      '@type' => $entity_type,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('db_wipe_ui.entity_wipe_form');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Yes, DELETE permanently');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel (Go back)');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $tempstore = $this->getTempstore();
    $entity_type = $tempstore->get('entity_type');
    $bundle = $tempstore->get('bundle');
    $count = $tempstore->get('entity_count');

    if (!$entity_type) {
      $this->messenger()->addError($this->t('Session expired. Please start again.'));
      return $this->redirect('db_wipe_ui.entity_wipe_form');
    }

    $form = parent::buildForm($form, $form_state);

    $form['#attached']['library'][] = 'db_wipe_ui/db_wipe_ui';
    $form['#attributes']['class'][] = 'confirmation-form';
    $form['#attributes']['class'][] = 'db-wipe-ui-form';

    $form['critical_warning'] = [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => ['class' => ['messages', 'messages--error']],
    ];

    $warning_lines = [
      '═══════════════════════════════════════════════════════════',
      '                    ⚠️  CRITICAL WARNING  ⚠️',
      '═══════════════════════════════════════════════════════════',
      '',
      $this->t('You are about to PERMANENTLY DELETE @count entities!', ['@count' => $count]),
      $this->t('Entity Type: @type', ['@type' => $entity_type]),
    ];

    if ($bundle) {
      $warning_lines[] = $this->t('Bundle: @bundle', ['@bundle' => $bundle]);
    }

    $warning_lines[] = '';
    $warning_lines[] = '⚠️  THIS ACTION CANNOT BE UNDONE!';
    $warning_lines[] = '⚠️  DELETED DATA CANNOT BE RECOVERED!';
    $warning_lines[] = '⚠️  MAKE SURE YOU HAVE A DATABASE BACKUP!';
    $warning_lines[] = '';
    $warning_lines[] = '═══════════════════════════════════════════════════════════';

    if ($entity_type === 'user') {
      $warning_lines[] = '';
      $warning_lines[] = '⚠️⚠️⚠️  DELETING USERS MAY AFFECT SYSTEM ACCESS! ⚠️⚠️⚠️';
      $warning_lines[] = '🛡️  User ID 1 (admin) is automatically protected.';
      $warning_lines[] = '';
    }

    $form['critical_warning']['message'] = [
      '#markup' => '<pre><strong>' . implode("\n", $warning_lines) . '</strong></pre>',
    ];

    $form['summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Deletion Summary'),
      '#open' => TRUE,
      '#weight' => -50,
    ];

    $summary_items = [
      $this->t('Entity Type: @type', ['@type' => $entity_type]),
      $this->t('Total Count: @count', ['@count' => $count]),
    ];

    if ($bundle) {
      $summary_items[] = $this->t('Bundle: @bundle', ['@bundle' => $bundle]);
    }

    $exclude_ids = $tempstore->get('exclude_ids');
    if ($exclude_ids) {
      $summary_items[] = $this->t('Excluding IDs: @ids', ['@ids' => implode(', ', $exclude_ids)]);
    }

    $include_ids = $tempstore->get('include_ids');
    if ($include_ids) {
      $summary_items[] = $this->t('Only IDs: @ids', ['@ids' => implode(', ', $include_ids)]);
    }

    $use_batch = $tempstore->get('use_batch') ?? TRUE;
    $summary_items[] = $this->t('Processing Mode: @mode', [
      '@mode' => $use_batch ? $this->t('Batch Processing') : $this->t('Immediate Deletion')
    ]);

    $form['summary']['list'] = [
      '#theme' => 'item_list',
      '#items' => $summary_items,
    ];

    $form['confirmation_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Type "DELETE" to confirm'),
      '#required' => TRUE,
      '#description' => $this->t('You must type the word DELETE (in capital letters) to proceed.'),
      '#weight' => -10,
    ];

    $form['actions']['submit']['#attributes']['class'][] = 'button--danger';
    $form['actions']['cancel']['#attributes']['class'][] = 'button';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $confirmation = $form_state->getValue('confirmation_text');

    if ($confirmation !== 'DELETE') {
      $form_state->setErrorByName('confirmation_text', $this->t('You must type DELETE exactly to confirm deletion.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $tempstore = $this->getTempstore();

    $entity_type = $tempstore->get('entity_type');
    $bundle = $tempstore->get('bundle');
    $exclude_ids = $tempstore->get('exclude_ids');
    $include_ids = $tempstore->get('include_ids');
    $use_batch = $tempstore->get('use_batch') ?? TRUE;

    $query = $this->entityWipeService->buildQuery(
      $entity_type,
      $bundle,
      $exclude_ids,
      $include_ids
    );

    if ($use_batch) {
      $result = $this->entityWipeService->executeWipe($entity_type, $query, $bundle, FALSE, $exclude_ids, $include_ids);

      if ($result['prevented']) {
        $this->messenger()->addWarning($this->t('Deletion was prevented by an event subscriber.'));
      }
      else {
        $this->messenger()->addStatus($this->t('Deleting @count entities. Please wait for batch processing to complete.', [
          '@count' => $result['count'],
        ]));
      }
    }
    else {
      // Delete immediately without batch
      $ids = $this->entityWipeService->getEntityIds($query);
      $deleted = $this->entityWipeService->deleteEntitiesImmediate($entity_type, $ids);
      $this->messenger()->addStatus($this->t('Deleted @count entities immediately.', ['@count' => $deleted]));
    }

    $tempstore->delete('entity_type');
    $tempstore->delete('bundle');
    $tempstore->delete('exclude_ids');
    $tempstore->delete('include_ids');
    $tempstore->delete('entity_count');
    $tempstore->delete('use_batch');

    $form_state->setRedirect('db_wipe_ui.entity_wipe_form');
  }

  /**
   * Gets the tempstore for this form.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStore
   *   The tempstore.
   */
  protected function getTempstore() {
    return \Drupal::service('tempstore.private')->get('db_wipe_ui');
  }

}
