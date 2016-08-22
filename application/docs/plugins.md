#Writing Plugins

**Read First:** [Application Overview](./overview.md)

**See Also:** [Examples Extension](#)

**Important:** All PHP files should be in the `VSAC` namespace.

A plugin in this application is essentially a micro-service that is exposed to the outside world. At a minimum, it should consist of:

  * A `functions.php` file that contains the plugin's function definitions
  * An `_info.ini` file to register the plugin with the application
  * At least one controller, one of which should be a documentation page such as this one
  * Optinally, a default controller at `index.php`

Plugins must live inside of an extension, as documented in the overview page. An example structure may look like:

    /path/to/extension/
    |-- config/
    |   `-- plugin-name.php      # configuration options for the plugin
    `-- plugins/
        `-- plugin-name/
            |-- _info.ini        # plugin registration details
            |-- docs.php         # controller to display plugin documentation
            |-- index.php        # the default controller
            `-- script.js        # a consumer javascript file

Plugins must implement the following functions in their `functions.php` file:

    /**
     * The {$plugin_name}_config_items function is what lets the backend know what
     * configuration settings the plugin requires. It works exactly the same as the
     * equivalent function in the modules.
     *
     * @see example_module_config_items() for more details.
     *
     * @return array()
     */
    function example_plugin_config_items()
    {
        return array(
            ['example_plugin_setting', '', 'A string setting for the example plugin'],
        );
    }

    /**
     * The {$plugin_name}_bootstrap function bootstraps the plugin.  It is mostly
     * used for requesting modules, but you can do anything here. It runs very early
     * in every request to the plugin, so try to keep it slim.
     *
     * @return void;
     */
    function example_plugin_bootstrap()
    {
        use_module('filesystem');
        use_module('kval');
        use_module('cal');
    }


The best way to get started is to take a look at the examples in the [example extension](#).
