<?php

/**
 * Shorten URLs.  This is the abstraction layer, it requires a driver.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function shortener_depends()
{
    return driver_call('shortener', 'depends');
}


/** @see example_module_config_items() */
function shortener_config_items()
{
    return array_merge(array(
        [
            'shortener_driver',
            '',
            'The driver for the shortener, can be one of "bitly", "eurac" or "noshorten"'
        ],
        [
            'shortener_base_url',
            '',
            'The base URL for the shortener',
        ],
        [
            'shortener_api_key',
            '',
            'The API key for the shortener',
            true,
        ]
    ), driver_call('shortener', 'config_items'));
}

/** @see example_module_sysconfig() */
function shortener_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function shortener_test()
{
    $driver = driver('shortener');
    $test_conf_error = load_test_conf(array(
        'shortener_base_url' => '',
        'shortener_api_key'  => '',
    ), $driver);
    if ($test_conf_error) {
        return $test_conf_error;
    }

    force_conf('http_allowed_domains', ['example.com']);

    $driver_test = driver_call('shortener', 'test');
    if ($driver_test !== true) {
        return $driver_test;
    }

    $long = 'http://www.example.com/' . uniqid();
    $short = shortener_shorten($long);

    if (!filter_var($short, FILTER_VALIDATE_URL)) {
        return 'Shortener did not return valid URL';
    }

    if ($driver != 'noop' && $long == $short) {
        return 'Url did not get shortened';
    }
    return true;
}

//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//


/**
 * Get a short url for sharing
 *
 * @param string $url the short url
 * @return string the short url
 */
function shortener_shorten($url)
{
    return driver_call('shortener', 'shorten', [$url]);
}



