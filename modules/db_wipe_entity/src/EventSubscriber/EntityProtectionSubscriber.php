<?php

namespace Drupal\db_wipe_entity\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\db_wipe_entity\Event\BeforeEntityWipeEvent;
use Drupal\db_wipe_entity\Event\EntityWipeEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to protect entities from deletion based on configuration.
 */
class EntityProtectionSubscriber implements EventSubscriberInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Constructs an EntityProtectionSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    AccountInterface $current_user
  ) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityWipeEvents::BEFORE_WIPE => ['protectEntities', 100],
    ];
  }

  /**
   * Protects entities from deletion based on configuration.
   *
   * @param \Drupal\db_wipe_entity\Event\BeforeEntityWipeEvent $event
   *   The before wipe event.
   */
  public function protectEntities(BeforeEntityWipeEvent $event): void {
    $config = $this->configFactory->get('db_wipe_entity.settings');
    $protection_config = $config->get('protection');
    $logging_config = $config->get('logging');

    // Check if protection is enabled.
    if (empty($protection_config['enabled'])) {
      return;
    }

    $entity_type_id = $event->getEntityTypeId();
    $query = $event->getQuery();
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $id_key = $entity_type->getKey('id');
    $logger = $this->loggerFactory->get('db_wipe_entity');

    // Check if entire entity type is protected.
    $protected_types = $protection_config['protected_entity_types'] ?? [];
    if (in_array($entity_type_id, $protected_types, TRUE)) {
      $event->preventWipe();
      if ($logging_config['log_protected_attempts']) {
        $logger->warning('Attempted to wipe protected entity type @type.', [
          '@type' => $entity_type_id,
        ]);
      }
      return;
    }

    // Apply user-specific protection.
    if ($entity_type_id === 'user') {
      $this->protectUsers($query, $protection_config, $logging_config);
    }

    // Apply custom protection rules.
    $this->applyCustomProtectionRules(
      $query,
      $entity_type_id,
      $event->getBundle(),
      $protection_config['custom_protection_rules'] ?? []
    );
  }

  /**
   * Applies user-specific protection rules.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The entity query.
   * @param array $protection_config
   *   The protection configuration.
   * @param array $logging_config
   *   The logging configuration.
   */
  protected function protectUsers($query, array $protection_config, array $logging_config): void {
    $protected_ids = [];
    $logger = $this->loggerFactory->get('db_wipe_entity');

    // Always protect User ID 1 if configured.
    if (!empty($protection_config['protect_uid_one'])) {
      $protected_ids[] = 1;
    }

    // Add additional protected user IDs.
    if (!empty($protection_config['protected_users'])) {
      $protected_ids = array_merge($protected_ids, $protection_config['protected_users']);
    }

    // Remove duplicates and ensure we have IDs to protect.
    $protected_ids = array_unique($protected_ids);
    if (!empty($protected_ids)) {
      $query->condition('uid', $protected_ids, 'NOT IN');

      if ($logging_config['log_protected_attempts']) {
        $logger->info('Protected user IDs from deletion: @ids', [
          '@ids' => implode(', ', $protected_ids),
        ]);
      }
    }

    // Protect users with specific roles.
    if (!empty($protection_config['protected_roles'])) {
      $user_storage = $this->entityTypeManager->getStorage('user');
      $role_users = $user_storage->getQuery()
        ->condition('roles', $protection_config['protected_roles'], 'IN')
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($role_users)) {
        $query->condition('uid', $role_users, 'NOT IN');

        if ($logging_config['log_protected_attempts']) {
          $logger->info('Protected @count users with roles: @roles', [
            '@count' => count($role_users),
            '@roles' => implode(', ', $protection_config['protected_roles']),
          ]);
        }
      }
    }
  }

  /**
   * Applies custom protection rules to the query.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The entity query.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $bundle
   *   The bundle.
   * @param array $rules
   *   The custom protection rules.
   */
  protected function applyCustomProtectionRules($query, string $entity_type_id, ?string $bundle, array $rules): void {
    foreach ($rules as $rule) {
      // Skip if rule doesn't apply to this entity type.
      if ($rule['entity_type'] !== $entity_type_id) {
        continue;
      }

      // Skip if rule specifies a bundle and it doesn't match.
      if (!empty($rule['bundle']) && $rule['bundle'] !== $bundle) {
        continue;
      }

      // Apply the protection rule.
      if (!empty($rule['field']) && isset($rule['value'])) {
        $operator = $rule['operator'] ?? '!=';

        // For protection, we typically want to exclude (NOT equal).
        if ($operator === '=') {
          $operator = '!=';
        }

        $query->condition($rule['field'], $rule['value'], $operator);
      }
    }
  }

}