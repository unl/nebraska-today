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
