/*
 * Load global settings.
 */
if (file_exists($app_root . '/sites/default/news-settings.php')) {
  include $app_root . '/sites/default/news-settings.php';
}

/*
 * Load local settings.
 */
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
