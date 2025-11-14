<?php

namespace Drupal\db_wipe_db\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\db_wipe_db\Service\DatabaseWipeService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure database wipe settings.
 */
class DatabaseWipeSettingsForm extends ConfigFormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The database wipe service.
   *
   * @var \Drupal\db_wipe_db\Service\DatabaseWipeService
   */
  protected DatabaseWipeService $databaseWipeService;

  /**
   * Constructs a DatabaseWipeSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\db_wipe_db\Service\DatabaseWipeService $database_wipe_service
   *   The database wipe service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    Connection $database,
    DatabaseWipeService $database_wipe_service
  ) {
    parent::__construct($config_factory);
    $this->database = $database;
    $this->databaseWipeService = $database_wipe_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('database'),
      $container->get('db_wipe_db.database_wipe')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'db_wipe_db_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['db_wipe_db.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('db_wipe_db.settings');
    $tables = $config->get('tables');
    $safety = $config->get('safety');
    $logging = $config->get('logging');
    $performance = $config->get('performance');

    // Table management settings.
    $form['tables'] = [
      '#type' => 'details',
      '#title' => $this->t('Table Management'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['tables']['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Table selection mode'),
      '#options' => [
        'whitelist' => $this->t('Whitelist - Only predefined safe tables and prefixes'),
        'custom' => $this->t('Custom - Manually specify allowed tables'),
        'all_with_prefix' => $this->t('Prefix-based - All tables matching safe prefixes'),
      ],
      '#default_value' => $tables['mode'] ?? 'whitelist',
      '#description' => $this->t('Choose how tables are selected for truncation.'),
    ];

    // Get all tables for selection.
    $all_tables = $this->databaseWipeService->getAllTables();
    $table_options = array_combine($all_tables, $all_tables);

    $form['tables']['safe_tables'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Safe tables'),
      '#options' => $table_options,
      '#default_value' => $tables['safe_tables'] ?? [],
      '#description' => $this->t('Tables that are safe to truncate in whitelist mode.'),
      '#states' => [
        'visible' => [
          ':input[name="tables[mode]"]' => ['value' => 'whitelist'],
        ],
      ],
    ];

    $form['tables']['safe_prefixes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Safe table prefixes'),
      '#default_value' => implode("\n", $tables['safe_prefixes'] ?? []),
      '#description' => $this->t('Table prefixes that are safe to truncate (one per line). Example: cache_, search_'),
      '#rows' => 5,
    ];

    $form['tables']['custom_tables'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Custom allowed tables'),
      '#options' => $table_options,
      '#default_value' => $tables['custom_tables'] ?? [],
      '#description' => $this->t('Manually select tables allowed for truncation.'),
      '#states' => [
        'visible' => [
          ':input[name="tables[mode]"]' => ['value' => 'custom'],
        ],
      ],
    ];

    $form['tables']['protected_tables'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Protected tables (never truncate)'),
      '#options' => $table_options,
      '#default_value' => $tables['protected_tables'] ?? [],
      '#description' => $this->t('These tables will NEVER be truncated, regardless of other settings.'),
    ];

    // Safety settings.
    $form['safety'] = [
      '#type' => 'details',
      '#title' => $this->t('Safety Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['safety']['require_confirmation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require confirmation'),
      '#description' => $this->t('Require user confirmation before truncating tables.'),
      '#default_value' => $safety['require_confirmation'] ?? TRUE,
    ];

    $form['safety']['require_backup_confirmation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ask for backup confirmation'),
      '#description' => $this->t('Ask users to confirm they have a backup before truncating.'),
      '#default_value' => $safety['require_backup_confirmation'] ?? TRUE,
    ];

    $form['safety']['max_rows_without_warning'] = [
      '#type' => 'number',
      '#title' => $this->t('Max rows without warning'),
      '#description' => $this->t('Show additional warning when truncating tables with more rows than this.'),
      '#default_value' => $safety['max_rows_without_warning'] ?? 10000,
      '#min' => 100,
      '#max' => 1000000,
    ];

    $form['safety']['allow_system_tables'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow system tables'),
      '#description' => $this->t('⚠️ DANGEROUS: Allow truncating critical system tables.'),
      '#default_value' => $safety['allow_system_tables'] ?? FALSE,
    ];

    // Logging settings.
    $form['logging'] = [
      '#type' => 'details',
      '#title' => $this->t('Logging Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['logging']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable logging'),
      '#description' => $this->t('Log all truncate operations.'),
      '#default_value' => $logging['enabled'] ?? TRUE,
    ];

    $form['logging']['log_row_counts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log row counts'),
      '#description' => $this->t('Log the number of rows deleted from each table.'),
      '#default_value' => $logging['log_row_counts'] ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="logging[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Performance settings.
    $form['performance'] = [
      '#type' => 'details',
      '#title' => $this->t('Performance Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['performance']['use_transaction'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use database transaction'),
      '#description' => $this->t('Wrap truncate operations in a transaction for safety.'),
      '#default_value' => $performance['use_transaction'] ?? TRUE,
    ];

    $form['performance']['batch_operations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use batch operations'),
      '#description' => $this->t('Use batch processing for multiple table operations.'),
      '#default_value' => $performance['batch_operations'] ?? TRUE,
    ];

    $form['performance']['disable_foreign_key_checks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable foreign key checks'),
      '#description' => $this->t('Temporarily disable foreign key constraints during truncation (MySQL/MariaDB).'),
      '#default_value' => $performance['disable_foreign_key_checks'] ?? FALSE,
    ];

    // Statistics section.
    $form['statistics'] = [
      '#type' => 'details',
      '#title' => $this->t('Table Statistics'),
      '#open' => FALSE,
    ];

    $stats = $this->databaseWipeService->getTableStatistics();
    $total_rows = 0;
    $total_size = 0;

    $rows = [];
    foreach ($stats as $table => $info) {
      $rows[] = [
        $table,
        $info['safe'] ? $this->t('✓ Safe') : $this->t('✗ Not Safe'),
        $info['protected'] ? $this->t('🔒 Protected') : '',
        number_format($info['rows']),
        format_size($info['size']),
      ];
      $total_rows += $info['rows'];
      $total_size += $info['size'];
    }

    $form['statistics']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Table'),
        $this->t('Status'),
        $this->t('Protection'),
        $this->t('Rows'),
        $this->t('Size'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No safe tables found.'),
      '#caption' => $this->t('Total: @rows rows, @size', [
        '@rows' => number_format($total_rows),
        '@size' => format_size($total_size),
      ]),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('db_wipe_db.settings');

    // Process prefixes.
    $prefixes_raw = $form_state->getValue(['tables', 'safe_prefixes']);
    $prefixes = array_filter(array_map('trim', explode("\n", $prefixes_raw)));

    // Process table selections.
    $safe_tables = array_keys(array_filter($form_state->getValue(['tables', 'safe_tables']) ?? []));
    $custom_tables = array_keys(array_filter($form_state->getValue(['tables', 'custom_tables']) ?? []));
    $protected_tables = array_keys(array_filter($form_state->getValue(['tables', 'protected_tables']) ?? []));

    // Save configuration.
    $config
      ->set('tables.mode', $form_state->getValue(['tables', 'mode']))
      ->set('tables.safe_tables', $safe_tables)
      ->set('tables.safe_prefixes', $prefixes)
      ->set('tables.custom_tables', $custom_tables)
      ->set('tables.protected_tables', $protected_tables)
      ->set('safety.require_confirmation', $form_state->getValue(['safety', 'require_confirmation']))
      ->set('safety.require_backup_confirmation', $form_state->getValue(['safety', 'require_backup_confirmation']))
      ->set('safety.max_rows_without_warning', $form_state->getValue(['safety', 'max_rows_without_warning']))
      ->set('safety.allow_system_tables', $form_state->getValue(['safety', 'allow_system_tables']))
      ->set('logging.enabled', $form_state->getValue(['logging', 'enabled']))
      ->set('logging.log_row_counts', $form_state->getValue(['logging', 'log_row_counts']))
      ->set('performance.use_transaction', $form_state->getValue(['performance', 'use_transaction']))
      ->set('performance.batch_operations', $form_state->getValue(['performance', 'batch_operations']))
      ->set('performance.disable_foreign_key_checks', $form_state->getValue(['performance', 'disable_foreign_key_checks']))
      ->save();

    parent::submitForm($form, $form_state);
  }

}