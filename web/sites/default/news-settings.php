<?php

/*
 * Location of the site configuration files.
 */
$settings['config_sync_directory'] = '../config/sync';

/**
 * Trusted host configuration.
 */
$settings['trusted_host_patterns'] = [
  '^unl\.edu$',
  '^.+\.unl\.edu$',
  '^.+\.nebraska\.edu$',
  '^.+\.unomaha\.edu$',
  '^.+\.unk\.edu$',
  '^.+\.unmc\.edu$',
];

/*
 * Private file path:
 *
 * A local file system path where private files will be stored.
 */
$settings['file_private_path'] = dirname(debug_backtrace()[0]['file']) . '/files/private';

/*
 * Set environment-specific configuration.
 */
$environment = getenv('UNLCMSENV');

if ($environment == 'production') {
  $config['config_split.config_split.production']['status'] = TRUE;
  $config['config_split.config_split.stage']['status'] = FALSE;
  $config['config_split.config_split.development']['status'] = FALSE;
  $settings['file_temp_path'] = '/var/www/unl.edu/tmp/nebraska-today';
}
elseif ($environment == 'stage') {
  $config['config_split.config_split.production']['status'] = FALSE;
  $config['config_split.config_split.stage']['status'] = TRUE;
  $config['config_split.config_split.development']['status'] = FALSE;
  $settings['file_temp_path'] = '/var/www/unl.edu/tmp/nebraska-today';
}
// If not production or stage, then assumed to be development.
else {
  $config['config_split.config_split.production']['status'] = FALSE;
  $config['config_split.config_split.stage']['status'] = FALSE;
  $config['config_split.config_split.development']['status'] = TRUE;

  /*
   * Assertions.
   *
   * The Drupal project primarily uses runtime assertions to enforce the
   * expectations of the API by failing when incorrect calls are made by code
   * under development.
   *
   * @see http://php.net/assert
   * @see https://www.drupal.org/node/2492225
   *
   * If you are using PHP 7.0 it is strongly recommended that you set
   * zend.assertions=1 in the PHP.ini file (It cannot be changed from .htaccess
   * or runtime) on development machines and to 0 in production.
   *
   * @see https://wiki.php.net/rfc/expectations
   */
  assert_options(ASSERT_ACTIVE, TRUE);
  \Drupal\Component\Assertion\Handle::register();

  // This will prevent Drupal from setting read-only permissions on sites/default.
  $settings['skip_permissions_hardening'] = TRUE;

  // This will ensure the site can only be accessed through the intended host
  // names. Additional host patterns can be added for custom configurations.
  $settings['trusted_host_patterns'] = ['.*'];

  // Enable development.services.yml.
  // Provides cache.backend.null.
  // Disables Twig caching and enables Twig autoload.
  // Adds debug cacheability headers.
  $settings['container_yamls'][] = DRUPAL_ROOT . '/sites/default/development.services.yml';

  // Disable CSS and JS preprocessing.
  $config['system.performance']['css']['preprocess'] = FALSE;
  $config['system.performance']['js']['preprocess'] = FALSE;

  // Disable render, dynamic page, and page cache.
  $settings['cache']['bins']['render'] = 'cache.backend.null';
  //$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
  //$settings['cache']['bins']['page'] = 'cache.backend.null';

  // For local development, the temp directory should be set
  // in settings.local.php.
}
