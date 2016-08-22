<?php

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_config_items() */
function request_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function request_sysconfig()
{
    // php_sapi_name is too diverse, this seems to always work
    if (empty(request_server('SERVER_NAME'))) {
        return 'Requests must run in web server environment';
    }
    return true;
}

/** @see example_module_test() */
function request_test()
{
    return 'No tests to run';
}

//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//


/**
 * Get an offset for a superglobal, or a default if not set. Exists as wrapper
 * around superglobal because these should be read only and this will
 * dereference the variables.
 *
 * @param string $superglobal_name @see superglobal()
 * @param string $name the offset name
 * @param mixed $default the default value
 */
function request_superglobal($superglobal_name, $name, $default = null)
{
    $superglobal = superglobal($superglobal_name);
    return isset($superglobal[$name]) ? $superglobal[$name] : $default;
}

/**
 * Shorthand for request_superglobal('server', $name, $default);
 *
 * @param string $name
 * @param mixed $default
 *
 * @return mixed
 */
function request_server($name, $default = null)
{
    return request_superglobal('server', $name, $default);
}

/**
 * Shorthand for request_superglobal('get', $name, $default);
 *
 * @param string $name
 * @param mixed $default
 *
 * @return mixed
 */
function request_query($name, $default = null)
{
    return request_superglobal('get', $name, $default);
}

/**
 * Get all query data
 *
 * @return array
 */
function request_query_all()
{
    return request_superglobal_all('get');
}

/**
 * Shorthand for request_superglobal('post', $name, $default);
 *
 * @param string $name
 * @param mixed $default
 *
 * @return mixed
 */
function request_post($name, $default = null)
{
    return request_superglobal('post', $name, $default);
}

/**
 * Get all POST data
 *
 * @return array
 */
function request_post_all()
{
    return request_superglobal_all('post');
}

/**
 * Shorthand for request_superglobal('request', $name, $default);
 *
 * @param string $name
 * @param mixed $default
 *
 * @return mixed
 */
function request_request($name, $default = null)
{
    return request_superglobal('request', $name, $default);
}


/**
 * Get all REQUEST data
 *
 * @return array
 */
function request_request_all()
{
    return request_superglobal_all('request');
}


/**
 * Get the URL requested by the user
 *
 * @return string
 */
function request_url()
{
    $scheme = request_scheme();
    $host = request_host();
    $ssl = request_ssl();
    $port = (int) request_server('SERVER_PORT', '80');
    $uri = request_server('REQUEST_URI', '/');

    $url = $scheme . '//' . $host;

    if (!$ssl && $port !== 80 || $ssl && $port !== 443) {
        $url .= ':' . $port;
    }
    return $url . $uri;
}

function request_ssl()
{
    $https = request_server('HTTPS', false);
    return $https && $https != 'off';
}

function request_scheme()
{
    $proto = strtolower(request_server('SERVER_PROTOCOL', 'HTTP/1.1'));
    $scheme = substr($proto, 0, strpos($proto, '/'));
    if (request_ssl()) {
        $scheme .= 's';
    }
    return $scheme . ':';
}

function request_host()
{
    return request_server('HTTP_HOST', request_server('SERVER_NAME', 'localhost'));
}

function request_method()
{
    return strtolower(request_server('REQUEST_METHOD'));
}

/**
 * Force the request into HTTPS if the installation allows it.
 *
 * @WARNING this will abort the current request
 *
 * @return void
 */
function request_force_https()
{
    if (!framework_config('https_enabled', false) || request_ssl()) {
        return;
    }
    $url = request_url();
    $surl = preg_replace('/^http:/', 'https:', $url);
    if ($url != $surl) {
        header('Location: ' . $surl);
        die();
    }
}

/**
 * Force the request into HTTP if the installation allows it.
 *
 * @WARNING this will abort the current request
 *
 * @return void
 */
function request_force_http()
{
    if (!framework_config('http_enabled', false) || !request_ssl()) {
        return;
    }
    $surl = request_url();
    $url = preg_replace('/^https:/', 'http:', $url);
    if ($url != $surl) {
        header('Location: ' . $url);
        die();
    }
}
