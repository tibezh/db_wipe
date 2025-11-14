<?php

namespace Drupal\db_wipe_entity\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure entity protection settings for DB Wipe Entity.
 */
class EntityProtectionSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs an EntityProtectionSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'db_wipe_entity_protection_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['db_wipe_entity.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('db_wipe_entity.settings');
    $deletion_mode = $config->get('deletion_mode');
    $protection = $config->get('protection');
    $logging = $config->get('logging');
    $batch = $config->get('batch');

    // Deletion mode settings.
    $form['deletion_mode'] = [
      '#type' => 'details',
      '#title' => $this->t('Deletion Mode Settings'),
      '#description' => $this->t('Configure how entities are deleted from the database.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['deletion_mode']['warning'] = [
      '#markup' => '<div class="messages messages--warning">' .
        $this->t('⚠️ <strong>WARNING:</strong> SQL Direct mode bypasses all Entity API hooks and events. Use with extreme caution!') .
        '</div>',
    ];

    $form['deletion_mode']['default_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default deletion method'),
      '#options' => [
        'entity_api' => $this->t('Entity API (Safe) - Uses Drupal Entity API, fires all hooks/events'),
        'sql_direct' => $this->t('SQL Direct (Fast) - Direct SQL queries, bypasses all hooks/events'),
        'auto' => $this->t('Auto - Automatically choose based on entity count'),
      ],
      '#default_value' => $deletion_mode['default_method'] ?? 'entity_api',
      '#description' => $this->t('Choose the default method for deleting entities.'),
    ];

    $form['deletion_mode']['allow_sql_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow SQL Direct mode'),
      '#description' => $this->t('Enable the ability to use direct SQL queries for deletion.'),
      '#default_value' => $deletion_mode['allow_sql_mode'] ?? TRUE,
    ];

    $form['deletion_mode']['sql_mode_requires_permission'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require special permission for SQL mode'),
      '#description' => $this->t('Users must have "use sql wipe mode" permission to use SQL Direct.'),
      '#default_value' => $deletion_mode['sql_mode_requires_permission'] ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="deletion_mode[allow_sql_mode]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['deletion_mode']['auto_mode_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Auto mode threshold'),
      '#description' => $this->t('In Auto mode, use SQL Direct when entity count exceeds this number.'),
      '#default_value' => $deletion_mode['auto_mode_threshold'] ?? 1000,
      '#min' => 100,
      '#max' => 100000,
      '#states' => [
        'visible' => [
          ':input[name="deletion_mode[default_method]"]' => ['value' => 'auto'],
        ],
      ],
    ];

    $form['deletion_mode']['warn_before_sql'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show warning before SQL operations'),
      '#description' => $this->t('Display a warning message before executing SQL Direct operations.'),
      '#default_value' => $deletion_mode['warn_before_sql'] ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="deletion_mode[allow_sql_mode]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['deletion_mode']['sql_batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('SQL batch size'),
      '#description' => $this->t('Number of entities to delete per SQL query batch.'),
      '#default_value' => $deletion_mode['sql_batch_size'] ?? 1000,
      '#min' => 100,
      '#max' => 10000,
      '#states' => [
        'visible' => [
          ':input[name="deletion_mode[allow_sql_mode]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['deletion_mode']['disable_foreign_keys_on_truncate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable foreign key checks during truncate'),
      '#description' => $this->t('Temporarily disable foreign key constraints when truncating entity tables. MySQL/MariaDB only.'),
      '#default_value' => $deletion_mode['disable_foreign_keys_on_truncate'] ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="deletion_mode[allow_sql_mode]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Protection settings.
    $form['protection'] = [
      '#type' => 'details',
      '#title' => $this->t('Entity Protection Settings'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['protection']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable entity protection'),
      '#description' => $this->t('When enabled, certain entities will be protected from deletion based on the rules below.'),
      '#default_value' => $protection['enabled'] ?? TRUE,
    ];

    // User protection settings.
    $form['protection']['user_protection'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('User Protection'),
      '#states' => [
        'visible' => [
          ':input[name="protection[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['protection']['user_protection']['protect_uid_one'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always protect User ID 1 (admin)'),
      '#description' => $this->t('Strongly recommended. User ID 1 has special privileges and should never be deleted.'),
      '#default_value' => $protection['protect_uid_one'] ?? TRUE,
    ];

    $form['protection']['user_protection']['protected_users'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional protected user IDs'),
      '#description' => $this->t('Enter user IDs to protect, one per line or comma-separated.'),
      '#default_value' => implode(', ', $protection['protected_users'] ?? [1]),
      '#states' => [
        'visible' => [
          ':input[name="protection[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Get available roles.
    $roles = user_role_names(TRUE);
    $form['protection']['user_protection']['protected_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Protected user roles'),
      '#description' => $this->t('Users with these roles will be protected from deletion.'),
      '#options' => $roles,
      '#default_value' => $protection['protected_roles'] ?? ['administrator'],
      '#states' => [
        'visible' => [
          ':input[name="protection[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Entity type protection.
    $entity_types = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->getGroup() === 'content') {
        $entity_types[$entity_type_id] = $entity_type->getLabel();
      }
    }

    $form['protection']['protected_entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Protected entity types'),
      '#description' => $this->t('These entity types cannot be wiped at all.'),
      '#options' => $entity_types,
      '#default_value' => $protection['protected_entity_types'] ?? [],
      '#states' => [
        'visible' => [
          ':input[name="protection[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Logging settings.
    $form['logging'] = [
      '#type' => 'details',
      '#title' => $this->t('Logging Settings'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['logging']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable logging'),
      '#description' => $this->t('Log database wipe operations.'),
      '#default_value' => $logging['enabled'] ?? TRUE,
    ];

    $form['logging']['log_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Log level'),
      '#options' => [
        'emergency' => $this->t('Emergency'),
        'alert' => $this->t('Alert'),
        'critical' => $this->t('Critical'),
        'error' => $this->t('Error'),
        'warning' => $this->t('Warning'),
        'notice' => $this->t('Notice'),
        'info' => $this->t('Info'),
        'debug' => $this->t('Debug'),
      ],
      '#default_value' => $logging['log_level'] ?? 'notice',
      '#states' => [
        'visible' => [
          ':input[name="logging[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['logging']['log_protected_attempts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log attempts to delete protected entities'),
      '#description' => $this->t('Records when protected entities block deletion attempts.'),
      '#default_value' => $logging['log_protected_attempts'] ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="logging[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Batch processing settings.
    $form['batch'] = [
      '#type' => 'details',
      '#title' => $this->t('Batch Processing Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['batch']['batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Batch size'),
      '#description' => $this->t('Number of entities to process per batch operation.'),
      '#default_value' => $batch['batch_size'] ?? 50,
      '#min' => 1,
      '#max' => 500,
    ];

    $form['batch']['memory_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Memory limit'),
      '#description' => $this->t('PHP memory limit for batch operations (e.g., 256M, 512M, 1G).'),
      '#default_value' => $batch['memory_limit'] ?? '256M',
      '#size' => 10,
    ];

    // Custom protection rules.
    $form['custom_rules'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom Protection Rules'),
      '#description' => $this->t('Advanced: Define custom field-based protection rules.'),
      '#open' => FALSE,
    ];

    $form['custom_rules']['info'] = [
      '#markup' => '<p>' . $this->t('Custom rules allow you to protect entities based on field values. This feature is for advanced users.') . '</p>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate memory limit format.
    $memory_limit = $form_state->getValue(['batch', 'memory_limit']);
    if (!preg_match('/^[0-9]+[KMG]?$/i', $memory_limit)) {
      $form_state->setErrorByName('batch][memory_limit', $this->t('Invalid memory limit format. Use formats like 256M, 512M, or 1G.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('db_wipe_entity.settings');

    // Process protected users.
    $protected_users_raw = $form_state->getValue(['protection', 'user_protection', 'protected_users']);
    $protected_users = [];
    if (!empty($protected_users_raw)) {
      // Split by comma or newline.
      $users = preg_split('/[,\n]+/', $protected_users_raw);
      foreach ($users as $uid) {
        $uid = trim($uid);
        if (is_numeric($uid) && $uid > 0) {
          $protected_users[] = (int) $uid;
        }
      }
    }

    // Process protected roles.
    $protected_roles = array_filter($form_state->getValue(['protection', 'user_protection', 'protected_roles']));
    $protected_roles = array_values($protected_roles);

    // Process protected entity types.
    $protected_entity_types = array_filter($form_state->getValue(['protection', 'protected_entity_types']));
    $protected_entity_types = array_values($protected_entity_types);

    // Save configuration.
    $config
      // Save deletion mode settings.
      ->set('deletion_mode.default_method', $form_state->getValue(['deletion_mode', 'default_method']))
      ->set('deletion_mode.allow_sql_mode', $form_state->getValue(['deletion_mode', 'allow_sql_mode']))
      ->set('deletion_mode.sql_mode_requires_permission', $form_state->getValue(['deletion_mode', 'sql_mode_requires_permission']))
      ->set('deletion_mode.auto_mode_threshold', $form_state->getValue(['deletion_mode', 'auto_mode_threshold']))
      ->set('deletion_mode.warn_before_sql', $form_state->getValue(['deletion_mode', 'warn_before_sql']))
      ->set('deletion_mode.sql_batch_size', $form_state->getValue(['deletion_mode', 'sql_batch_size']))
      ->set('deletion_mode.disable_foreign_keys_on_truncate', $form_state->getValue(['deletion_mode', 'disable_foreign_keys_on_truncate']))
      // Save protection settings.
      ->set('protection.enabled', $form_state->getValue(['protection', 'enabled']))
      ->set('protection.protect_uid_one', $form_state->getValue(['protection', 'user_protection', 'protect_uid_one']))
      ->set('protection.protected_users', array_unique($protected_users))
      ->set('protection.protected_roles', $protected_roles)
      ->set('protection.protected_entity_types', $protected_entity_types)
      ->set('logging.enabled', $form_state->getValue(['logging', 'enabled']))
      ->set('logging.log_level', $form_state->getValue(['logging', 'log_level']))
      ->set('logging.log_protected_attempts', $form_state->getValue(['logging', 'log_protected_attempts']))
      ->set('batch.batch_size', $form_state->getValue(['batch', 'batch_size']))
      ->set('batch.memory_limit', $form_state->getValue(['batch', 'memory_limit']))
      ->save();

    parent::submitForm($form, $form_state);
  }

}