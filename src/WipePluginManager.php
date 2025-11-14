<?php

namespace Drupal\db_wipe;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\db_wipe\Annotation\WipePlugin;

/**
 * Plugin manager for Wipe plugins.
 */
class WipePluginManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Wipe',
      $namespaces,
      $module_handler,
      'Drupal\db_wipe\Plugin\WipePluginInterface',
      WipePlugin::class
    );

    $this->alterInfo('wipe_plugin_info');
    $this->setCacheBackend($cache_backend, 'wipe_plugins');
  }

  /**
   * Gets all available plugins sorted by weight and label.
   */
  public function getAvailablePlugins(): array {
    $definitions = $this->getDefinitions();

    // Sort by weight and then by label.
    uasort($definitions, function ($a, $b) {
      $weight_diff = ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
      if ($weight_diff !== 0) {
        return $weight_diff;
      }
      return strcmp($a['label'], $b['label']);
    });

    return $definitions;
  }

  /**
   * Gets plugins in a specific category.
   */
  public function getPluginsByCategory(string $category): array {
    $definitions = $this->getDefinitions();
    return array_filter($definitions, function ($definition) use ($category) {
      return ($definition['category'] ?? 'general') === $category;
    });
  }

  /**
   * Finds a plugin that supports the target.
   */
  public function getPluginForTarget($target): ?object {
    foreach ($this->getAvailablePlugins() as $plugin_id => $definition) {
      $plugin = $this->createInstance($plugin_id);
      if ($plugin->supports($target)) {
        return $plugin;
      }
    }
    return NULL;
  }

}
