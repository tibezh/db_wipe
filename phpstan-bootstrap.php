<?php

/**
 * @file
 * PHPStan bootstrap file for Drupal modules.
 */

// Define Drupal root if not already defined.
if (!defined('DRUPAL_ROOT')) {
  // Try to find Drupal root.
  $drupal_root = FALSE;

  // Common paths where Drupal might be installed.
  $possible_roots = [
    __DIR__ . '/../../..',
    __DIR__ . '/../../../..',
    '/var/www/html/web',
    $_ENV['DRUPAL_ROOT'] ?? '',
  ];

  foreach ($possible_roots as $root) {
    if ($root && file_exists($root . '/core/includes/bootstrap.inc')) {
      $drupal_root = $root;
      break;
    }
  }

  if ($drupal_root) {
    define('DRUPAL_ROOT', $drupal_root);
  }
}

// Bootstrap Drupal if possible.
if (defined('DRUPAL_ROOT') && file_exists(DRUPAL_ROOT . '/core/includes/bootstrap.inc')) {
  require_once DRUPAL_ROOT . '/core/includes/bootstrap.inc';
}