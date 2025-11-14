<?php

namespace Drupal\db_wipe\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Wipe plugins.
 */
abstract class WipePluginBase extends PluginBase implements WipePluginInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->pluginDefinition['description'];
  }

  public function validate($target, array $options = []): array {
    $errors = [];

    if (!$this->supports($target)) {
      $errors[] = $this->t('Plugin @plugin does not support target: @target', [
        '@plugin' => $this->getLabel(),
        '@target' => $target,
      ]);
    }

    return $errors;
  }

  abstract public function supports($target): bool;

  abstract public function execute($target, array $options = []): array;

  abstract public function preview($target, array $options = []): array;

}
