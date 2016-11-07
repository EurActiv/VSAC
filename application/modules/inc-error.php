<?php

/**
 * Error handling
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function error_depends()
{
    return driver_call('error', 'depends');
}

/** @see example_module_config_items() */
function error_config_items()
{
    return array([
        'error_driver',
        '',
        'The driver to use for error logging, one of "noop" or "sqlite"'
    ],);
}

/** @see example_module_sysconfig() */
function error_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function error_test()
{
    return true;
}

//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//

/**
 * Format a PHP error code into something human readable
 *
 * @param mixed $code the error code
 *
 * @return string the human readable error code
 */
function error_format_errcode($code) {
    switch ($code) {
        case E_ERROR:             return 'E_ERROR';
        case E_USER_ERROR:        return 'E_USER_ERROR';
        case E_WARNING:           return 'E_WARNING';
        case E_USER_WARNING:      return 'E_USER_WARNING';
        case E_RECOVERABLE_ERROR: return 'E_RECOVERABLE_ERROR';
        case E_NOTICE:            return 'E_NOTICE';
        case E_USER_NOTICE:       return 'E_USER_NOTICE';
        case E_STRICT:            return 'E_STRICT';
        case E_DEPRECATED:        return 'E_DEPRECATED';
        case E_USER_DEPRECATED:   return 'E_USER_DEPRECATED';
        default:                  return (string) $code;
    }
}

/**
 * Remove the inclue path from a file name in error messages.
 *
 * @param string $file the absolute path to the file
 *
 * @return string
 */
function error_shorten_filename($file) {
    foreach (get_include_paths() as $include_path) {
        if (strpos($file, $include_path) === 0) {
            return substr($file, strlen($include_path) + 1);
        }
    }
    return $file;
}


/**
 * The callback for error handling. Parameters match those of the callback in
 * set_error_handler, except the last one, which should be the contents of
 * debug_backtrace().
 */
function error_handle_error($no, $str, $file, $line, $trace)
{
    error_log($no, $str, $file, $line, $trace);
    $fatal = array(E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR);
    if (in_array($no, $fatal)) {
        die();
    }
    return false;
}

/**
 * List 50 errors from the error log
 *
 * @param int $page paginate
 *
 * @return array[string] an array of keys that can be passed to error_get to get
 * the full error record.
 */
function error_list($page = 0)
{
    return error_force_driver(function () use ($page) {
        return driver_call('error', 'list', array($page));
    });
}

/**
 * Get a full error record
 *
 * @param string $key one of the keys returned by error_list()
 *
 * @return array the full error
 */
function error_get($key)
{
    return error_force_driver(function () use ($key) {
        return driver_call('error', 'get', array($key));
    });
}

/**
 * Mark an error as resolved so that it dissappears from listings. Usually means
 * deleting it from the log, it will reappear if it's not really resolved
 *
 * @param string $key one of the keys returned by error_list()
 *
 * @return void
 */
function error_resolve($key)
{
    return error_force_driver(function () use ($key) {
        return driver_call('error', 'resolve', array($key));
    });
}

/**
 * For the backend, list limitations that the current driver has
 *
 * @return string HTML that can be echo'd immediately
 */
function error_driver_limitations()
{
    return error_force_driver(function () {
        return driver_call('error', 'driver_limitations');
    });
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//

/**
 * We want error logging to work even if a given plugin isn't configured for it.
 * So this will force the system configuration for the driver even if it isn't
 * configured. All driver calls should be wrapped in this one.  It's pretty ugly,
 * but the error handling should not be called that often anyway.
 * 
 * @param callable $callback the callback to execute once the driver is forced
 *
 * @return mixed whatever $callback returns
 */
function error_force_driver(callable $callback)
{
    $force = false;
    if (!option('error_driver', '')) {
        $force = framework_option('error_driver', 'noop');
        force_conf('error_driver', $force);
    }
    $return = call_user_func($callback);
    if ($force) {
        force_conf('error_driver', null);
    }
    return $return;
}

/**
 * Log an error to the error log. Parameters match those of error_handle_error.
 *
 * @return void
 */
function error_log($no, $str, $file, $line, $trace)
{
    return error_force_driver(function () use ($no, $str, $file, $line, $trace) {
        return driver_call('error', 'log', array($no, $str, $file, $line, $trace));
    });
}


