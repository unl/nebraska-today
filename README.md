# UNL News

A Drupal installation hosted at https://news.unl.edu/ developed by the [Digital Experience Group](https://ucomm.unl.edu/).

## Requirements

See [Drupal System Requirements](https://www.drupal.org/docs/system-requirements)

While it is possible to run Drupal on a variety of web servers, database servers, etc.,
the officially supported configuration at UNL is as follows:

- Linux (any modern, supported distribution)
- PHP 8.1 or greater
- Apache 2.4 or greater
- MariaDB 10.6 or greater

Latest verified working configuration:

- PHP 8.1.10
- Apache 2.4.54
- MariaDB 10.7.3

Composer, PHP's dependency manager, is necessary to install this project.
See [Install Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx).

Note: The instructions below refer to the [global composer installation](https://getcomposer.org/doc/00-intro.md#globally).
It may be necessary to replace `composer` with `php composer.phar` (or similar).

## Pre-Installation

Navigate to the project root and install the project:

```
composer install
```

### Install the UNLedu Web Framework

The [unl_five](https://github.com/unlcms/unl_five) theme requires the [UNLedu Web Framework](https://github.com/unl/wdntemplates).

There are two methods to install the UNLedu Web Framework:

1. Automated
2. Manual

#### Automated

The unl/wdntemplates package is already downloaded to `/vendor/unl/wdntemplates`. Run the following command:

```
composer install-wdn
```

This command will create a symlink of `/vendor/unl/wdntemplates/wdn` at `/web/wdn`.

The wdntemplates package is a Node.js project that uses Grunt. This command will also install
the Node.js project and run the default Grunt task.

To receive upstream updates, navigate to `vendor/unl/wdntemplates` and run `git pull`.

#### Manual

Download the [UNLedu Web Framework sync set](https://wdn.unl.edu/downloads/wdn_includes.zip) to `/web/wdn`.

### Install ImageMagick
-  If you do not have ImageMagick software installed on your device, you can do so using the following commands. This software is necessary to process/add images.
```
brew install imagemagick
brew install ghostscript
```

For more infromation regaridng installation go to https://imagemagick.org/script/download.php

## Install Drupal

```
cp web/sites/default/default.settings.local.php web/sites/default/settings.local.php
```

Edit `web/sites/default/settings.local.php` and set the LDAP password.

Navigate to _http://example.unl.edu/nebraska-today/web/_ (or set up a virtual host, _news-local.unl.edu_ is the recommended name) in your browser. (See [Installing Drupal](https://www.drupal.org/docs/installing-drupal))

When asked to select an Installation Profile, select _Use existing configuration._

## Common Settings for All Sites

Settings that apply to all sites can be included in one of two places:

1. `web/sites/default/news-settings.php` which is committed to the repo.
2. `web/sites/default/settings.local.php` which is not committed and is appropriate for sensitive info or environment specific overrides to things set in (1).

## Upgrading Drupal Core (or a module)

Run this on a development site and commit composer.json, composer.lock, and any changes to `config/sync`.
The process is the same for a module, just change the project in the first composer command.
```
composer update "drupal/core-*" --with-all-dependencies
drush updatedb
drush cache:rebuild
drush config:export
```

Run on a deployment after updating code base:
```
composer install
drush updatedb
drush cache:rebuild
```

## Configuration Management

This project uses Configuration Management (abbreviated _CMI_) to store the present/base/main configuration of the site.

After making changes, use `drush config:export` to export config to `config/sync` and commit.

Import current config state with `drush config:import`.

## Config Split

This project uses Config Split to manage configuration among production, stage, and development. Certain modules,
such as Twig Xdebug and Config Inspector are only enabled on development.

In the development config split, a number of settings are enabled, disabled, or modified: Caching is disabled;
Twig caching is disabled and Twig autoloading is enabled; debug cacheability headers are enabled;
CSS and JS aggregation is disabled; and file permission hardening is disabled.  See
/web/sites/default/news-settings.php for more details. These settings can be overridden in settings.local.php.
