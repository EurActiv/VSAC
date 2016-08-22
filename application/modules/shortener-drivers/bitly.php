<?php

/**
 * The driver for the eurac.tv URL shortener
 */

namespace VSAC;

use_module('http');

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//

/** @see shortener_shorten() */
function bitly_shorten($url)
{
    $shortener_url = config('shortener_base_url', '');
    $shortener_api_key = config('shortener_api_key', '');

    if (!$shortener_url || !$shortener_api_key) {
        return $url;
    }

    $api_query = http_build_query(array(
        'access_token' => $shortener_api_key,
        'domain'       => $shortener_url,
        'longUrl'      => $url,
        'format'       => 'json',
    ));
    $api_url = 'https://api-ssl.bitly.com/v3/shorten?' . $api_query; 
    if (!http_get($api_url, $json)) {
        return $url;
    }
    $arr = json_decode($json, true);
    if ($arr['data']['long_url'] != $url || !$arr['data']['url']) {
        return $url;
    }
    return $arr['data']['url'];
}


