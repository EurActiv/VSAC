<?php

/**
 * a microframework for the asset management scripts
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- pre-bootstrapping, on include                                          --//
//----------------------------------------------------------------------------//


set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    use_module('error');
    $trace = debug_backtrace();
    error_handle_error($errno, $errstr, $errfile, $errline, $trace);
    return true;
});

set_exception_handler(function ($ex) {
    use_module('error');
    error_handle_error(
        $$ex->getCode(),
        $ex->getMessage(),
        $ex->getFile(),
        $ex->getLine(),
        $ex->getTrace()
    );
});

//----------------------------------------------------------------------------//
//-- bootstrapping                                                          --//
//----------------------------------------------------------------------------//

/**
 * Bootstrap the application in a web server environment.
 *
 * @param bool $debug display all errors
 *
 * @return void
 */
function bootstrap_web($debug = false)
{
    if ($debug) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }
    mb_internal_encoding('UTF-8');
    add_include_path(__DIR__);
    use_module('filesystem');
    use_module('router');
    use_module('request');
    use_module('response');
    use_module('front-controller');
}

/**
 * Bootstrap the application in a cli environment.
 *
 * @return void
 */
function bootstrap_cli()
{
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    mb_internal_encoding('UTF-8');
    add_include_path(__DIR__);
    use_module('filesystem');
    use_module('cli');

}

//----------------------------------------------------------------------------//
//-- utilities                                                              --//
//----------------------------------------------------------------------------//


/**
 * Define a constant if it hasn't been already.
 *
 * @param string $name the constant name
 * @param scalar $value the value for the constant
 * @return scalar the actual constant value
 */
function def($name, $value)
{
    $name = strtoupper($name);
    if (!defined($name)) {
        define($name, $value);
    }
    return constant($name);
}

/**
 * Check if a function exists in the application namespace
 *
 * @param string $fn the function name, without namespacing
 *
 * @return bool
 */
function fn_exists($fn)
{
    return function_exists(__NAMESPACE__ . '\\' . $fn);
}


/**
 * Get or set the application data directory
 *
 * @param string $path set the data directory to this path
 *
 * @return string the path to the data directory
 */
function data_directory($path = null)
{
    static $directory = null;
    if (!is_null($path)) {
        if (!is_dir($path)) {
            mkdir($path);
        }
        $directory = realpath($path);
    }
    if (!$directory) {
        err('Data directory could not be created or found');
    }
    return $directory;
}

/**
 * Set the application data directory
 *
 * @return void
 */
function set_data_directory($path)
{
    data_directory($path);
}

//----------------------------------------------------------------------------//
//-- including files, phar archives                                         --//
//----------------------------------------------------------------------------//

/**
 * Get the current include paths, as an array
 *
 * @return array[string]
 */
function get_include_paths()
{
    $paths = get_include_path();
    // Fix for URL-style file wrappers (eg, phar://)
    $paths = str_replace(PATH_SEPARATOR, '#' . PATH_SEPARATOR . '#', $paths);
    $paths = str_replace('#:#//', '://', $paths);
    return explode('#' . PATH_SEPARATOR . '#', $paths);
}

/**
 * Get the path to an included file, resolving to the original phar if it is
 * in a proxied phar. The file must not necessarily exist.
 *
 * @param string $path the file path
 *
 * @string string the resolved path
 */
function get_included_filepath($file)
{
    if (is_dir($file) || substr($file, -7) == '_phar__') {
        $file .= '/';
    }
    if (strpos($file, '_phar__/') === false) {
        return $file;
    }
    list ($proxy, $subpath) = explode('_phar__/', $file, 2);
    $proxy .= '_phar__';
    if (!file_exists($proxy . '/__phar.txt')) {
        return $file;
    }
    $phar = file_get_contents($proxy . '/__phar.txt');
    if (!file_exists($phar)) {
        return $file;
    }
    $file = 'phar://' . $phar . '/' . $subpath;
    $file = preg_replace('#/+$#', '', $file);
    return $file;
}

/**
 * Add an include path. If the include path is a phar archive, the archive
 * will be extracted to a proxy in data_dir, and the extracted proxy path
 * used instead.
 *
 * @param string $path the path to add to the include paths
 * @param bool $prepend put this add at the beginning of the include paths
 *
 * @return void
 */
function add_include_path($path, $prepend = true)
{
    if ($proxy_for = phar_proxy_for($path)) {
        phar_check_proxy($proxy_for, $path);
        remove_include_path('phar://' . $proxy_for);
    } elseif ($proxy_dir = phar_proxy_dir($path)) {
        phar_check_proxy($path, $proxy_dir);
        remove_include_path($path);
        $path = $proxy_dir;
    }
    $paths = get_include_paths();
    if (in_array($path, $paths)) {
        return;
    }
    if ($prepend) {
        array_unshift($paths, $path);
    } else {
        $paths[] = $path;
    }
    set_include_path(implode(PATH_SEPARATOR, $paths));
}

/**
 * Remove an include path from the include paths
 *
 * @param string $path the path to remove
 *
 * @return void
 */
function remove_include_path($path)
{
    $paths = get_include_paths();
    if (($key = array_search($path, $paths)) !== false) {
        unset($paths[$key]);
        set_include_path(implode(PATH_SEPARATOR, $paths));
    }
}

/**
 * Similar to scandir(), but returns all files in all include paths. Returns
 * something like this:
 *
 *     // without $flatten
 *     scan_include_dirs("/plugins");
 *     // array(
 *     //     '/path/to/includes-1/' => array('.', '..', 'a.php', 'b.php'),
 *     //     '/path/to/includes-2/' => array('.', '..', 'b.php', 'c.php'),
 *     // );
 *
 *     // with $flatten
 *     scan_include_dirs("/plugins", true);
 *     // array('.', '..', 'a.php', 'b.php', 'c.php');
 *
 * @param string $subdir the subdir with the include paths to scan
 * @param bool $flatten flatten the array, more like scandir()
 */
function scan_include_dirs($subdir = '/', $flatten = true)
{
    $return = array();
    if (strpos($subdir, '/') === 0) {
        $subdir = substr($subdir, 1);
    }
    foreach (get_include_paths() as $path) {
        if (is_dir($path . '/' . $subdir)) {
            $return[$path . '/' . $subdir] = scandir($path . '/' . $subdir);
        }
    }
    if (!$flatten) {
        return $return;
    }
    $flattened = array();
    foreach ($return as $ret) {
        $flattened = array_merge($flattened, $ret);
    }
    return array_unique($flattened);
}

/**
 * Check if a path is a directory that proxies for a phar archive
 *
 * @param string $path the path to check
 *
 * @return string|false the path to the original phar archive, or false if it's
 * not a phar archive proxy.
 */
function phar_proxy_for($path)
{
    if (strpos($path, 'phar://') === 0) {
        return false;
    }
    if (!is_dir($path)) {
        return false;
    }
    if (!file_exists($path . '/__phar.txt')) {
        return false;
    }
    $proxy_for = file_get_contents($path . '/__phar.txt');
    return $proxy_for && file_exists($proxy_for) ? $proxy_for : false;
}

/**
 * Get the path to a proxy dir to use for a phar file
 *
 * @param string $phar the path to the phar file
 *
 * @return false if the path is not a proxyable phar or the path to the proxy location
 */
function phar_proxy_dir($phar)
{
    if (strpos($phar, 'phar://') !== 0) {
        return false;
    }
    if (pathinfo($phar, PATHINFO_EXTENSION) !== 'phar') {
        return false;
    }
    $proxy_dir = preg_replace('/[^a-zA-Z0-9]/', '_', basename($phar));
    $proxy_dir = data_directory() . '/__' . $proxy_dir . '__';
    return $proxy_dir;
}

/**
 * Check if a phar proxy exists and is up to date. Create/update it as needed
 *
 * @param string $phar the path to the phar file
 * @param string $proxy_dir the path that the phar was extracted to
 */
function phar_check_proxy($phar, $proxy_dir)
{
    if (strpos($phar, 'phar://') === 0) {
        $phar = substr($phar, 7);
    }
    if (!file_exists($phar)) {
        return;
    }
    if (
        // proxy does not exist
        (!is_dir($proxy_dir) || !file_exists($proxy_dir . '/__phar.txt'))
        // proxy is out of date
        || (filemtime($phar) > filemtime($proxy_dir . '/__phar.txt'))
    ) {
        phar_create_proxy($phar, $proxy_dir);
    }
}

/**
 * Create an phar proxy by extracting it to a directory
 *
 * @param string $phar_path the path to the phar
 * @param string $proxy_dir the path to extract the phar into
 */
function phar_create_proxy($phar_path, $proxy_dir)
{
    $phar = new \Phar($phar_path);
    $phar->extractTo($proxy_dir, null, true);
    file_put_contents($proxy_dir . '/__phar.txt', $phar_path);  
}


//----------------------------------------------------------------------------//
//-- module management                                                      --//
//----------------------------------------------------------------------------//



/**
 * List all of the modules in the system and whether it is currently being used
 *
 * @return array[string] all module names
 */
function modules()
{
    static $modules;
    if (!is_null($modules)) {
        return $modules;
    }
    $modules = scan_include_dirs('modules');
    $modules = array_map(function ($file) {
        $regex = '/^inc-([a-z0-9\-]+)\.php$/';
        return preg_match($regex, $file, $m) ? $m[1] : false;
    }, $modules);
    $modules = array_filter($modules);

    return $modules;
}

/**
 * Record the modules currently being used.
 *
 * @param string $name add a module to the list
 *
 * @return array[string] the used modules
 */
function used_modules($name = null)
{
    static $modules = array();
    if ($name && !in_array($name, $modules)) {
        $modules[] = $name;
    }
    return $modules;
}

/**
 * Use a module
 */
function use_module($name)
{
    used_modules($name);
    require_once 'modules/inc-' . $name . '.php';
}


/**
 * Load a module driver
 *
 * @private
 *
 * @param string $module the module to get the driver for
 * @param mixed $force_driver force the use of this driver regardless of config,
 * set to null to reset.
 * 
 * @return string the name of the driver
 */
function driver($module, $force_driver = false)
{
    static $drivers = array();

    $driver_option = str_replace('-', '_', $module) . '_driver';
    if (is_null($force_driver)) {
        if (isset($drivers[$module])) {
            unset($drivers[$module]);
        }
        force_conf($driver_option, null);
        if (!option($driver_option, '')) {
            return '';
        }
    }

    if ($force_driver) {
        if (isset($drivers[$module]) && $drivers[$module] != $force_driver) {
            unset($drivers[$driver]);
        }
        if (empty($drivers[$module])) {
            force_conf($driver_option, $force_driver);
        }
    }

    if (empty($drivers[$module])) {
        $driver = config($driver_option, '');
        $drivers[$module] = $driver;
        require_once 'modules/' . $module . '-drivers/' . $driver . '.php';
    }
    return $drivers[$module];
}

/**
 * List all of the drivers for a module
 *
 * @return array
 */
function drivers($module)
{
    $drivers = scan_include_dirs('modules/' . $module . '-drivers');
    $drivers = array_map(function ($file) {
        $regex = '/^([a-z0-9\-]+)\.php$/';
        return preg_match($regex, $file, $m) ? $m[1] : false;
    }, $drivers);

    return array_filter($drivers);
}

/**
 * Pass a function call through to a module driver
 *
 * @param string $module the module to call the driver from
 * @param string $fn the function, to which __NAMESPACE__ . '\\' . $driver . '_'
 * will be prepended
 * @param array $args arguments to pass to the underlying driver function
 * @return mixed the results of the called method
 */
function driver_call($module, $fn, array $args = array())
{
    $fn = __NAMESPACE__ . '\\' . str_replace('-', '_', driver($module)) . '_' . $fn;
    return call_user_func_array($fn, $args);
}



//----------------------------------------------------------------------------//
//-- Reading configuration                                                  --//
//----------------------------------------------------------------------------//

/**
 * Get the path to a plugin config file
 *
 * @private
 *
 * @return string
 */
function conf_file($plugin)
{
    return stream_resolve_include_path('config/' . $plugin . '.php');
}

/**
 * Force a config setting in code. For testing purposes only.
 *
 * @param string $name the config option name
 * @param mixed $value the value, or null to unset
 *
 * @return array all forced configs
 */
function &force_conf($name = null, $value = null)
{
    static $forced = array();

    if (!is_null($name)) {
        $forced[$name] = $value;
    }
    return $forced;
}

/**
 * Clear forced configuration items
 *
 * @return void
 */
function force_conf_clear()
{
    $forced = &force_conf();
    $forced = array();
}

function load_conf($plugin)
{
    static $configs = array();

    if (!isset($configs[$plugin])) {
        $conf_file = 'config/' . $plugin . '.php';
        // call user func is a pseudo sandbox
        $configs[$plugin] = call_user_func(function () use ($conf_file) {
            require $conf_file;
            return isset($config) ? $config : null;
        });
        if (!is_array($configs[$plugin])) {
            err('$config array was not declared in ' . $conf_file);            
        }
    }

    return $configs[$plugin];
}

/**
 * Get a config option from the plugin config file.  Throw an error if it is not
 * of the correct type.
 *
 * @private
 *
 * @param string $plugin the plugin name
 * @param string $name the configuration name
 * @param string $default the expected a default variable, used to calculate
 *        expected type
 * @param bool $raise_errors also throw an error if the config option is unset
 *        instead of using the default value
 * @return mixed the config option value
 */
function conf($plugin, $name, $default, $raise_errors)
{
    $err = function ($error) use ($plugin, $name, $raise_errors) {
        if ($raise_errors) {
            $msg = 'Error in /config/%s.php::$config["%s"]: %s';
            err(sprintf($msg, $plugin, $name, $error));
        }
    };

    $config = array_merge(load_conf($plugin), force_conf());

    $type = gettype($default);
    if (isset($config[$name])) {
        $value = $config[$name];
    } else {
        $err('Offset is not defined');
        $value = $default;
    }

    $d_type = gettype($default);
    $v_type = gettype($value);
    if ($d_type != $v_type) {
        $err(sprintf('Expected type %s, got type %s', $d_type, $v_type));
    }
    return $value;
}

/**
 * Get a framework configuration setting.
 *
 * @param string $name @see conf()
 * @param string $default @see conf()
 * @return mixed the config option value
 */
function framework_config($name, $default)
{
    return conf('_framework', $name, $default, true);
}


/**
 * Get a framework config option with loose checking
 * @param string $name @see conf()
 * @param string $default @see conf()
 * @return mixed the config option value
 */
function framework_option($name, $default)
{
    return conf('_framework', $name, $default, false);
}


//----------------------------------------------------------------------------//
//-- Plugin loading                                                         --//
//----------------------------------------------------------------------------//

/**
 * Bootstrap a plugin
 *
 * @param string $plugin the plugin to bootstrap
 *
 * @return void
 */
function bootstrap_plugin($plugin)
{
    if (defined('VSAC_PLUGIN')) {
        if (constant('VSAC_PLUGIN') !== $plugin) {
            $msg = 'Cannot bootstrap "%s", plugin "%s" already bootstrapped';
            err(sprintf($msg, $plugin, constant('VSAC_PLUGIN')));
        }
    } else {
        define('VSAC_PLUGIN', $plugin);
    }
    if ($plugin !== '_framework') {
        $functions = 'plugins/' . $plugin . '/functions.php';
        if (!stream_resolve_include_path($functions)) {
            err('Could not resolve path to plugin');
        }
        require_once $functions;
        $fn = __NAMESPACE__ . '\\' . str_replace('-', '_', $plugin) . '_bootstrap';
        call_user_func($fn);
    }
}

/**
 * Get the name of the currently declared plugin, die with error message if
 * none is bootstrapped
 */
function plugin()
{
    $plugin =  @constant('VSAC_PLUGIN');
    if (!$plugin) {
        err('No plugin bootsrapped', __FILE__.__LINE__);
    }
    return $plugin;
}

function plugin_info($plugin = null)
{
    static $plugins = array();
    if (!$plugin) {
        $plugin = plugin();
    }
    if (!isset($plugins[$plugin])) {
        $ini_path = stream_resolve_include_path('plugins/' . $plugin . '/_info.ini');
        $base_dir = $ini_path ? dirname($ini_path) : stream_resolve_include_path($plugin.'/');
        if (!$base_dir) {
            err('Could not resolve plugin path.');
        }
        $plugins[$plugin] = array_merge(array(
            'name'          => $plugin,
            'description'   => 'No description provided',
            'priority'      => 100,
            'doc_file'      => 'index.php',
            'base_dir'      => $base_dir,
        ), $ini_path ? parse_ini_file($ini_path) : array());
    }
    return $plugins[$plugin];
}

/**
 * Get all plugins in the application
 * @return array where key is plugin name and value is an array containing
 * the plugin _info file content, with defaults applied.
 */
function plugins()
{
    $plugins = array();
    foreach (scan_include_dirs('plugins', false) as $include_path => $files) {
        foreach ($files as $file) {
            if (file_exists($include_path . '/' . $file . '/_info.ini')) {
                $plugins[$file] = plugin_info($file);
            }
        }
    }
    return $plugins;
}

/**
 * Get a config option with strict checking.
 *
 * @param string $name @see conf()
 * @param string $default @see conf()
 * @return mixed the config option value
 */
function config($name, $default)
{
    return conf(plugin(), $name, $default, true);
}

/**
 * Get a config option with loose checking
 * @param string $name @see conf()
 * @param string $default @see conf()
 * @return mixed the config option value
 */
function option($name, $default)
{
    return conf(plugin(), $name, $default, false);
}




//----------------------------------------------------------------------------//
//-- Error/Debugging functions                                              --//
//----------------------------------------------------------------------------//

/**
 * Fetch one of the superglobals ($_SERVER, $_REQUEST, $_GET, $_POST). Exists to
 * allow overriding for testing. 
 *
 * @param string $name one of superglobal namse, lowercase with leading
 * underscore removed.
 * @param mixed $override set to an array to override the existing superglobal,
 * or anything else but NULL to restore the default superglobal.
 *
 * @return array the superglobal, returned by reference
 */
function &superglobal($name, $override = null)
{
    static $overrides = array();
    if (is_array($override)) {
        $overrides[$name] = $override;
    } elseif (!is_null($override)) {
        if (isset($overrides[$name])) {
            unset($overrides[$name]);
        }
    }
    if (isset($overrides[$name])) {
        return $overrides[$name];
    }

    // Note: can't use variable variables for superglobals
    switch ($name) {
        case 'global'   : return $GLOBALS   ;
        case 'server'   : return $_SERVER   ;
        case 'get'      : return $_GET      ;
        case 'post'     : return $_POST     ;
        case 'files'    : return $_FILES    ;
        case 'cookie'   : return $_COOKIE   ;
        case 'session'  : return $_SESSION  ;
        case 'request'  : return $_REQUEST  ;
        case 'env'      : return $_ENV      ;
    }
}


/**
 * Note the used of a deprecated function
 *
 * @param string $function: use the __FUNCTION__ magic constant
 */
function deprecated($function, $replaced_with)
{
    trigger_error("Used deprecated function {$function}");
}

/**
 * Print an error message and die.
 * @param string $msg the error description
 * @param string $src where the error occured, something like __FILE__.__LINE__
 *        the path to this directory will be stripped to avoid exposing too much
 *        to the outside.
 */
function err($msg, $src = '')
{
    $sanitize = function ($str) {
        foreach (get_include_paths() as $offset => $path) {
            $str = str_replace($path, "include_path[$offset]", $str);
        }
        return htmlspecialchars($str);
    };
    
    printf('<br><b>Error</b> in %s: %s', $src, $msg);
    die();
}

/**
 * Print a variable in a human-readable format. Mostly for debugging
 */
function printR($variable, $return = false)
{
    $value = trim(htmlspecialchars(print_r($variable, true)));
    $value = preg_replace("/Array\s+\(/", "Array (", $value);

    $value = sprintf('<pre style="font-size:12px">%s<code></code></pre>', $value);
    if ($return) return $value;
    echo $value;    
}

/**
 * Very simple profiling; wrap a suspected slow code block in this and the
 * time it took to execute will be printed at the end of the response.
 *
 * @param callable $callback the callback to call. Leave as null to just get
 * a return what's currently in the timer
 * @param string $handle a unique identifier for the code block, so that you
 * can profile multiple things in a single request.
 */
function timer(callable $callback = null, $handle = 'default')
{
    static $times = array();
    static $registered = false;
    if (!$registered) {
        register_shutdown_function(function () {
            printR(timer());
        });

    }
    if (is_null($callback)) {
        return $times;
    }
    $start = microtime(true);
    $return = call_user_func($callback);
    if (empty($times[$handle])) {
        $times[$handle] = 0;
    }
    $times[$handle] += microtime(true) - $start;
    return $return;
}


