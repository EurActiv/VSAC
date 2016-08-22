<?php

/**
 * This module handles api key verification
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_config_items() */
function apikey_config_items()
{
    return array(array(
        'api_key',
        '',
        'The key to access protected API calls. Should be set as the "api_key"
        query parameter. This key should only be used in server-to-server
        communication to avoid exposing it to the broader internet.',
        true,
    ));
}

/** @see example_module_sysconfig() */
function apikey_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function apikey_test()
{
    $key = md5(time());

    force_conf(plugin(), 'api_key', $key);
    $get =& superglobal('get');
    if (isset($get['api_key'])) {
        unset($get['api_key']);
    }
    if (apikey_is_valid()) {
        return 'validated empty key';
    }
    $bad_key = substr($key, 0, -1);
    $get['api_key'] = $bad_key;
    if (apikey_is_valid()) {
        return 'validated bad key';
    }
    $get['api_key'] = $key;
    if (!apikey_is_valid()) {
        return 'failed to validate correct key';
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
    return request_query('api_key', false) === config('api_key', '');
}



