#Application Overview

**See also:** [Plugins](./plugins.md) | [Modules](./modules.md) | [CLI commands](./cli.md) | [Examples Extension](https://github.com/EurActiv/VSAC-Examples)

###Structure

The application has the following structure:

    /path/to/application.phar
    |-- cli/                     # command line commands
    |   |-- console.php          # a command line command
    |   `-- [...]                # any more commands
    |-- config/                  # application configuration
    |   |-- _framework.php       # default application-wide settings
    |   |-- plugin-one.php       # settings for "plugin-one"
    |   |-- plugin-two.php       # settings for "plugin-two"
    |   `-- [...]
    |-- docs/                    # documentation, markdown formatted
    |-- framework/               # controllers for the application backend
    |   |-- index.php            # the directory listing
    |   `-- [...].php
    |-- modules/                 # shared methods that apply accross plugins
    |   |-- module-one-drivers/  # drivers for modules that need them
    |   |   |-- driver-one.php
    |   |   `-- driver-two.php
    |   |-- inc-module-one.php   # the module code
    |   |-- inc-module-two.php
    |   `-- inc-[...].php
    |-- plugins/                 # plugins (or microservices) that are public over http(s)
    |   |-- plugin-one/          # an individual plugin
    |   |   |-- _info.ini        # registers plugin with application
    |   |   |-- functions.php    # bootstraps the plugin
    |   |   |-- index.php        # the default controller
    |   |   |-- controller.php   # controllers can be any .php file
    |   |   `-- [...]            # and any additional controllers/assets/...
    |   |-- plugin-two/
    |   |   `-- [...]
    |   `-- [...]
    |-- application.php          # the application bootstrap file
    |-- cli.php                  # an example/default cli front controller
    |-- index.php                # an example/default web front controller
    `-- htaccess.txt             # an example/default .htaccess file


###Extending the application

**Important:** All PHP files should be in the `VSAC` namespace.

VSAC uses PHP's include path logic to govern the inclusion of extensions. As such, any file in the application except for `/application.php` can be overridden by duplicating the application directory structure in another location and then prepending the new location to the `include_path` in your front controller. This is also how you should implement vendor-specific configurations, modules and plugins.

For example, your vendor-specific directory might have the structure:

    /path/to/vendor/stuff/
    |-- config/
    |   `-- plugin-one.php         # configuration options for plugin one
    |-- modules/
    |   |-- module-one-drivers/
    |   |   `-- driver-three.php # a driver for module one
    |   |-- inc-module-two.php   # completely override module two
    |   `-- inc-module-three.php # a vendor-specfic module
    `-- plugins/
        |-- plugin-one/
        |   `-- controller.php   # override a single controller in another plugin
        `-- plugin-three/
            `-- [...]            # a vendor-specific plugin

To use your new vendor-specific structure, modify your front controllers (normally located at `/path/to/application/docroot/index.php` for the web controller, and `/path/to/application/cli.php` for the CLI controller) so that they read:

    <?php
    set_include_path(
        "/path/to/app/data/__application_phar__"
        . PATH_SEPARATOR .
        "phar:///path/to/app/application.phar"
    );
    require_once "application.php";
    VSAC\set_data_directory('/path/to/app/data');

    // this is the line you add, must be after set_data_directory() and 
    // before bootstrap_web() or bootstrap_cli().
    VSAC\add_include_path('/path/to/vendor/stuff');

    // This line would be VSAC\bootstrap_cli() in the cli front controller
    VSAC\bootstrap_web($debug = false);
    VSAC\front_controller_dispatch();


You can use this method to add any number of extensions.  Unless explicitly overridden, the `add_include_path()` function **prepends** paths to the include path, so that the _last_ path to be referenced in the front controller will be the _first_ to be found by when loading files.


###A note on PHAR archives

VSAC is normally distrubuted as a PHAR archive. It also supports using PHAR archives as vendor extensions. To use a phar archive as an application extension, add the following line to the include paths section:

    VSAC\add_include_path('phar:///path/to/vendor/stuff.phar');

For performance reasons, the PHAR archive will be extracted to the application data directory. In the example above, the extracted copy would be stored in `/path/to/app/data/__stuff_phar__`. The include path is then transparently re-written by the application to the extracted archive instead of the original. Normally, the app is pretty good about keeping the extracted copy in line with the original phar, but it could conceivably miss an update.  So it may be a good idea to delete the extracted archive after updating the source archive.

To facilitate distributing extensions, VSAC provides the cli command `create-phar` to create a distributatable PHAR file. In this example, you would do the following to create a PHAR of a vendor extension:

    user@machine:/path/to/application $ php cli.php

    ###########################################################################
    ## VSAM CLI Console                                                      ##
    ###########################################################################
    Available commands:
     console      create-phar  install
    Please select (type or use up/down arrows): create-phar
    Make a phar of: [/path/to/application/]: /path/to/vendor/stuff
    The phar will be saved to '/path/to/vendor/stuff.phar'. That OK? [Y/n]: 
    Phar saved.
    user@machine:/path/to/application $


