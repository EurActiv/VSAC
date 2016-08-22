#Writing modules

**Read First:** [Application Overview](./overview.md)

**See Also:** [Examples Extension](https://github.com/EurActiv/VSAC-Examples)

**Important:** All PHP files should be in the `VSAC` namespace.

Modules are a way to share code across multiple plugins. They can be loaded at any time and handle common tasks. A module consists of:

  * The core module file, located in `modules/inc-{$module_name}.php`.
  * An optional set of drivers for different backends, located in
    `/modules/{$module_name}-drivers/{$driver_name}.php`.

For example, your vendor-specific directory might have the structure:

    /path/to/vendor/stuff/
    `-- modules/
        |-- my-module-drivers/   # drivers for "my-module"
        |   `-- my-driver.php    # a driver for "my-module"
        |-- inc-my-module.php    # the module "my-module"
        `-- inc-http.php         # a vendor-specfic modification to the core http module

Modules must implement the following functions:

    /**
     * The {$module_name}_config_items functions is used to tell the application
     * which configuration items need to be set for the module to function properly.
     * It should return an array of items. Each item should be a numeric array,
     * where the offsets correspond to:
     *
     *   - 0: The name of the configuration item, as used in the first parameter of
     *        config(), option(), framework_config() or framework_option().
     *   - 1: A default item, for type hinting, as used as the second parameter in
     *        config(), option(), framework_config() or framework_option().
     *   - 2: A text description of the configuration item, for the backend.
     *   - 3: A boolean to indicate that backend/documentation screens should not
     *        expose the setting to non-logged in users. Optional, default false.
     *
     * @return array[array]
     */
    function example_module_config_items()
    {
        return array(
            ['example_module_setting', '', 'A string setting for the example module'],
            // This module uses a driver. The configuration name for the driver should
            // always be named "{$module_name}_driver" so that the driver functions
            // can find it.
            ['example_module_driver', '', 'The driver to use', true]
        );
    }

    /**
     * Check that the environment is properly configured for this module to function
     * correctly. Check for things like PHP classes.
     *
     * @return string|bool TRUE if the system is set up right, an error string if not.
     */
    function example_module_sysconfig()
    {
        // This example module has a driver, and so we just pass through to the
        // driver for this.
        return driver_call('example-module', 'sysconfig');
    }

    /**
     * Run tests that the module functions properly. Will only be called if
     * {$module_name}_sysconfig returns TRUE.
     *
     * @return string|bool TRUE if the tests pass, an error string if not.
     */
    function example_module_test()
    {
        return true;
    }

The best way to get started is to take a look at the examples in the [example extension](https://github.com/EurActiv/VSAC-Examples).
