<?php

/**
 * This module handles api key verification
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function apikey_depends()
{
    return array('request');
}

/** @see example_module_config_items() */
function apikey_config_items()
{
    return array(
        [
            'api_key',
            '',
            'The key to access protected API calls. This key should only be used
            in server-to-server communication to avoid exposing it to the
            broader internet. In requests, the key can be set in the query
            as the "api_key" value (eg http://example.com?api_key=keyboard_cat),
            in a POST request body as the "api_key" value, or as a header with
            the name "X-Vsac-Api-Key".',
            true,
        ],
    );
}

/** @see example_module_sysconfig() */
function apikey_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function apikey_test()
{
    $check = function ($superglobal, $offset) {
        $sg = &superglobal($superglobal);
        $old = empty($sg[$offset]) ? null : $sg[$offset];
        $exit = function ($return) use (&$sg, $superglobal, $offset, $old) {
            if (is_null($old)) {
                if (isset($sg[$offset])) unset($sg[$offset]);
            } else {
                $sg[$offset] = $old;
            }
            if (is_string($return)) {
                $return .= sprintf(' (method: %s::%s)', $superglobal, $offset);
            }
            return $return;
        };
        $key = uniqid();
        force_conf('api_key', $key);
        if (isset($sg[$offset])) {
            unset($sg[$offset]);
        }
        if (apikey_is_valid()) {
            return $exit('validated empty key');
        }
        $bad_key = substr($key, 0, -1);
        $sg[$offset] = $bad_key;
        if (apikey_is_valid()) {
            return $exit('validated bad key');
        }
        $sg[$offset] = $key;
        if (!apikey_is_valid()) {
            return $exit('failed to validate correct key');
        }
        return null;
    };
    if ($error = $check('get', 'api_key')) {
        return $error;
    }
    if ($error = $check('post', 'api_key')) {
        return $error;
    }
    if ($error = $check('server', 'HTTP_X_VSAC_API_KEY')) {
        return $error;
    }
    return true;

}


//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//


/**
 * Check if the current request has a valid API key.
 *
 * @return bool valid or not
 */
function apikey_is_valid()
{
    $key = config('api_key', '');
    return request_query('api_key', false) === $key
        || request_post('api_key', false) === $key
        || request_header('X-Vsac-Api-Key') === $key;
}



