<?php

/**
 * Shorten URLs.  This is the abstraction layer, it requires a driver.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_config_items() */
function shortener_config_items()
{
    return array(
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
    );
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

    $base_key = $driver . '_shortener_base_url';
    $base = option($base_key, '');

    $api_key_key = $driver . '_shortener_api_key';
    $api_key = option($api_key_key, '');
    if (!$base || !$api_key) {
        return sprintf('Cannot test %s unless config items %s and %s are set',
                        $driver, $base_key, $api_key_key);
    }

    force_conf('shortener_base_url', $base);
    force_conf('shortener_api_key', $api_key);
    force_conf('http_allowed_domains', ['example.com']);

    $long = 'http://www.example.com/';
    $short = shortener_shorten($long);

    if (!filter_var($short, FILTER_VALIDATE_URL)) {
        return 'Shortener did not return valid URL';
    }

    if ($driver != 'noshorten' && $long == $short) {
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



