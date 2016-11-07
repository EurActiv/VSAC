<?php

/**
 * The driver for the eurac.tv URL shortener
 */

namespace VSAC;

use_module('http');
use_module('router');

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//

/** @see shortener_depends() */
function shortener_bitly_depends()
{
    return array('http', 'router');
}

/** @see shortener_config_items */
function shortener_bitly_config_items()
{
    return array();
}

/** @see shortener_test */
function shortener_bitly_test()
{
    return true;
}


/** @see shortener_shorten() */
function shortener_bitly_shorten($url)
{
    $shortener_url = config('shortener_base_url', '');
    $shortener_api_key = config('shortener_api_key', '');

    if (!$shortener_url || !$shortener_api_key) {
        return $url;
    }

    $api_url = router_add_query(
        'https://api-ssl.bitly.com/v3/shorten',
        array(
            'access_token' => $shortener_api_key,
            'domain'       => $shortener_url,
            'longUrl'      => $url,
            'format'       => 'json',
        )
    );

    $response = http_get($api_url);
    if (!$response['body'] || $response['error']) {
        return $url;
    }
    $arr = json_decode($response['body'], true);
    if (empty($arr['data']) || $arr['data']['long_url'] != $url || !$arr['data']['url']) {
        return $url;
    }
    return $arr['data']['url'];
}


