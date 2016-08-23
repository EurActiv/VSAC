<?php

/**
 * Shorten URLs.  This is the abstraction layer, it requires a driver.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

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


function front_controller_resolve($plugin, $file)
{
    $file = $plugin == '_framework'
          ? 'framework/' . $file
          : 'plugins/' . $plugin . '/' . $file
          ;
    $file = filesystem_realpath($file);
    return is_file($file) ? $file : false;
}

function front_controller_locate_plugin(&$path_info)
{
    return count($path_info) > 1 ? array_shift($path_info) : '_framework';
}

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
        response_send_file($controller_path); 
    } else {
        response_send_error(400, 'Bad extension?');
    }
}




