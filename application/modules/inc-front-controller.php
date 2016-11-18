<?php

/**
 * Shorten URLs.  This is the abstraction layer, it requires a driver.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function front_controller_depends()
{
    return array('filesystem', 'response');
}

/** @see example_module_config_items() */
function front_controller_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function front_controller_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function front_controller_test()
{
    return true;
}

//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//

/**
 * Parse the request URL to get the plugin and controller, then hand over to
 * the controller.
 *
 * @return void
 */
function front_controller_dispatch()
{

    $server = &superglobal('server');
    $query = &superglobal('get');
    $path_info = empty($server['PATH_INFO']) ? '' : $server['PATH_INFO'];

    if (strpos($path_info, '/') === 0) {
        $path_info = substr($path_info, 1);
    }
    $path_info = explode('/', $path_info);

    $plugin = front_controller_locate_plugin($path_info);
    bootstrap_plugin($plugin);

    $controller_path = front_controller_locate_controller($plugin, $path_info);

    $server['PATH_INFO'] = implode('/', $path_info);

    if (!isset($query['path'])) {
        $query['path'] = $server['PATH_INFO'];
    }

    front_controller_discharge($controller_path);
}

//----------------------------------------------------------------------------//
//-- Private Functions                                                      --//
//----------------------------------------------------------------------------//

/**
 * Resolve the path to a controller/asset within a plugin
 *
 * @param string $plugin the plugin to resolve to
 * @param string $file the file to look for for the controller/asset
 *
 * @return string the path to the controller/asset file, or false if not found
 */
function front_controller_resolve($plugin, $file)
{
    $file = $plugin == '_framework'
          ? 'framework/' . $file
          : 'plugins/' . $plugin . '/' . $file
          ;
    $file = filesystem_realpath($file);
    return is_file($file) ? $file : false;
}

/**
 * Find the plugin that corresponds to a URL path
 *
 * @param array $path_info the Query path, exploded on "/", the plugin will be
 * shifted off it if it's found.
 *
 * @return string the plugin to use
 */
function front_controller_locate_plugin(&$path_info)
{
    if (count($path_info) < 2) {
        return '_framework';
    }
    if (!preg_match('/^[a-z\-\_]+$/', $path_info[0])) {
        return '_framework';
    }
    if (!stream_resolve_include_path('plugins/' . $path_info[0])) {
        return '_framework';
    }
    return array_shift($path_info);
}

/**
 * Find the controller file
 *
 * @param string $plugin the return value from front_controller_locate_plugin()
 * @param array $pathinfo the $path_info array, after it was passed to
 * front_controller_locate_plugin(). The controller will be popped off it if
 * it exists. If it doesn't, index.php is returned
 *
 * @return string the front controller abspath, from front_controller_resolve()
 */
function front_controller_locate_controller($plugin, &$path_info)
{
    $controller = array();
    while (count($path_info)) {
        $controller[] = array_shift($path_info);
        $path = implode('/', $controller);
        if ($resolved = front_controller_resolve($plugin, $path)) {
            return $resolved;
        }
        $path .= '.php';
        if ($resolved = front_controller_resolve($plugin, $path)) {
            return $resolved;
        }
    }
    $path_info = $controller;
    if (!($resolved = front_controller_resolve($plugin, 'index.php'))) {
        err('Could not resolve controller');
    }
    return $resolved;
}

/**
 * Take the resolved controller path from front_controller_locate_controller()
 * and send it. PHP files will be executed. Other assets will be sent.
 * Non-whitelisted file types will 404.
 *
 * @param string $controller_path
 *
 * @return void (will always die).
 */
function front_controller_discharge($controller_path)
{
    $ext = strtolower(pathinfo($controller_path, PATHINFO_EXTENSION));
    $authorized_exts = array(
        'js', 'json', 'xml', 'rss', 
        'css', 'sass', 'less',
        'php', 'html',
        'png', 'jpeg', 'jpg', 'gif', 'ico',
        'ttf', 'otf', 'woff', 'woff2', 'eot', 'svg',
    );

    if ($ext == 'php') {
        require $controller_path;
    } elseif (in_array($ext, $authorized_exts)) {
        if ($file = substr($controller_path, strlen(filesystem_plugin_path()))) {
            $file = array_filter(explode('/', $file));
            while (count($file) > 2) {
                array_pop($file);
            }
            if (!empty($file)) {
                // note: must load late because it uses a driver and therefore
                // requires configuration from the plugins
                use_module('callmap');
                callmap_log(implode('/', $file));
            }
        }
        response_send_file($controller_path); 
    } else {
        response_send_error(400, 'Bad extension?');
    }
}




