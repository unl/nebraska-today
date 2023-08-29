CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Deferred Issues

INTRODUCTION
------------

The OneSite API module provides an application programming interface for
interacting with Nebraska Today data. No authentication is required.

The API is documented with OpenAPI 3 in /doc/onesite_api.yml.

REQUIREMENTS
------------

The OneSite API module was custom built for Nebraska Today. As such, it cannot
be installed on other sites.

This module require the following patch for Drupal core:
https://www.drupal.org/project/drupal/issues/2993976

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/node/895232/ for further information.

CONFIGURATION
-------------

The module has no menu or modifiable settings. There is no configuration. 

DEFERRED ISSUES
---------------

This module uses a forked version of the Array-to-XML library.
Pull request: https://github.com/spatie/array-to-xml/pull/140
Forked version: https://github.com/macburgee1/array-to-xml/tree/custom-key-support

This pull request seeks to add support for custom keys. When the pull request
is merged, the vendored version in /lib should be udated.

This module also contains a vendored copy of the Swagger UI library on a
temporary basis. This allows the API documentation to viewed with Swagger UI
at /sites/all/modules/custom/onesite_api/lib/swagger-ui/dist/.
