<?php

namespace Drupal\db_wipe\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Interface for Wipe plugins.
 */
interface WipePluginInterface extends PluginInspectionInterface {

  /**
   * Gets the plugin label.
   */
  public function getLabel(): string;

  /**
   * Gets the plugin description.
   */
  public function getDescription(): string;

  /**
   * Checks if plugin can handle this target.
   */
  public function supports($target): bool;

  /**
   * Executes the wipe operation.
   */
  public function execute($target, array $options = []): array;

  /**
   * Validates before execution.
   */
  public function validate($target, array $options = []): array;

  /**
   * Gets preview of what will be wiped.
   */
  public function preview($target, array $options = []): array;

}
