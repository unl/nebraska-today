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

## Installation

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

## Install ImageMagick
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

Edit `web/sites/all/settings.php` and set the LDAP password.

Navigate to http://example.unl.edu/nebraska-today/web/ (or set up a virtual host, news-local.unl.edu is the recommended name) in your browser. (See Installing Drupal)

When asked to select an Installation Profile, select Use existing configuration.
