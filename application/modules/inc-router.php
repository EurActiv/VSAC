<?php

/**
 * Application routing and URL building functions
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_config_items() */
function router_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function router_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function router_test()
{
    return 'No tests to run';
}


//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//


/**
 * Whether the application is using URL rewriting
 *
 * @return bool true for yes, false for no
 */
function router_use_rewriting()
{
    return framework_config('pretty_urls', false, true);
}

/**
 * The base directory for the router, used in calculating paths. Throws an error
 * if the constant ROUTER_BASE_DIR hasn't been set
 *
 * @return string
 */
function router_basedir()
{
    if (!defined('ROUTER_BASE_DIR')) {
        err('Constant ROUTER_BASE_DIR not defined.');
    }
    return constant('ROUTER_BASE_DIR');
}

/**
 * The scheme to use when generating URLs for the current request.
 *
 * @return string eg 'http:' or 'https:';
 */
function router_scheme()
{
    return request_scheme();
}


/**
 * The base URL for locally stored asset reference
 *
 * @param bool $with_scheme prepend the current request scheme (http/https) to
 * the url
 *
 * @return string the URL, eg "//assets.example.com/path/to/app/"
 */
function router_base_url($with_scheme = false)
{
    static $url;
    if (!is_null($url)) {
        return $with_scheme ? router_scheme() . $url : $url;
    }

    $domain = request_host();
    $dir = superglobal('server')['SCRIPT_NAME'];
    if (router_use_rewriting()) {
        $dir = dirname($dir);
    }
    if (strpos($dir, '/') !== 0) {
        $dir = '/'.$dir;
    }
    if (substr($dir, -1) !=='/') {
        $dir .= '/';
    }
    $url = '//'.$domain.$dir;
    return $with_scheme ? router_scheme() . $url : $url;
}

/**
 * Add query parameters to a URL.
 *
 * @param string $url the existing URL
 * @param array $qp the query parameters, as will be passed to http_build_query
 *
 * @return string the new URL.
 */
function router_add_query($url, array $qp = array())
{
    if (empty($qp)) {
        return $url;
    }
    if (strpos($url, '?') !== false) {
        list($url, $query_str) = explode('?', $url, 2);
        parse_str($query_str, $old_qp);
        $qp = array_merge($old_qp, $qp);
    }
    if (!empty($qp)) {
        $url .= '?' . http_build_query($qp);
    }
    return $url;
}

/**
 * Get a route to a location within a plugin.
 *
 * @param string $path the path within the plugin (or absolute path)
 * @param bool $with_scheme prepend the scheme (http/https) to the url
 * @param bool $must_exist the requested file must exist or we'll have a conniption.
 */
function router_plugin_url($path = '', $with_scheme = false, $must_exist = false)
{
    $base_path = filesystem_plugin_path();
    if ($must_exist) {
        if (!($_path = filesystem_path_in_dir($path, $base_path))) {
            err("Path '{$path}' does not exist in '{$base_dir}'");
        }
    }
    if (strpos($path, $base_path) === 0) {
        $path = substr($path, strlen($base_path));
    }
    return router_url(plugin() . '/' . $path, $with_scheme);
      
}

function router_url($path, $with_scheme = false)
{
    if (strpos($path, '/') === 0) {
        $path = substr($path, 1);
    }
    return router_base_url($with_scheme) . $path;
}


/**
 * A url to a file, without the protections of router_plugin_url or
 * router_files_url
 *
 * @param string $abspath the absolute path to the file
 * @param bool $with_scheme include the scheme (http/https) in the URL
 * @retur string;
 */
function router_any_file_url($abspath, $with_scheme = false)
{
    $realpath = filesystem_realpath($abspath);
    
    if (strpos($realpath, filesystem_plugin_path()) === 0) {
        return router_plugin_url($realpath);
    }

    if (strpos($realpath, router_basedir()) !== 0) {
        err('could not generate url to file '.$abspath, __FILE__.__LINE__);
    }

    $relativepath = substr($realpath, strlen(request_server('DOCUMENT_ROOT')) + 1);
    return router_base_url() . $relativepath;

}


