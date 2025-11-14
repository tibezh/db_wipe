<?php

namespace Drupal\db_wipe\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Wipe plugin annotation object.
 *
 * @Annotation
 */
class WipePlugin extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A brief description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The category this plugin belongs to.
   *
   * @var string
   */
  public string $category = 'general';

  /**
   * The weight of this plugin (for sorting).
   *
   * @var int
   */
  public int $weight = 0;

}
