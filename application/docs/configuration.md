#Writing modules

**Read First:** [Application Overview](./overview.md)

**See Also:** [Examples Extension](https://github.com/EurActiv/VSAC-Examples)


Application and plugin configuration is stored in flat PHP files located in the `config` directory of your application.  Loading configuration files obeys the include logic of the rest of the application; that is, the configuration file in the _last_ included extension are the ones that are used. Configuration files do not inherit from previous files in the include chain.

The application configuration is stored in the `_framework.php` config file, and each plugin takes its configuration from the configuration file whose name matches the plugin name. For example, the plugin located in `plugins/my-plugin/` will get its configuration settings from the file `config/my-plugin.php`. Thus, your vendor specific configuration extension would look something like this:

    /path/to/vendor/stuff/
    `-- config/
        |-- _framework.php # application configuration
        |-- my-plugin.php  # configuration for "my-plugin"
        `-- [...].php      # and so on...

The configuration file should define an array name `$config` where the keys are the configuration setting names and the values are the configuration settings. A configuration file would look something like this:

    <?php
    $config = array(
        'my-setting' => 'my-value',
    );

In the backend, you can display plugin configuration settings with the function `backend_config_table()`. It will automatically redact configuration settings that are marked as private (eg, API keys).


The best way to get started is to take a look at the examples in the [example extension](https://github.com/EurActiv/VSAC-Examples).
