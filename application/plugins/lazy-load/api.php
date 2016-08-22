<?php

namespace VSAC;

/**
 * Convert the attributs string from an html element to an array of key:value
 * pairs
 *
 * @param string $attrs
 * @return array
 */
function ll_attrs_to_array($attrs)
{
    $return = array();
    while (
        preg_match('/^\s*([a-z0-9\-]+)+=\'([^\']*)\'*/', $attrs, $m)
        || preg_match('/^\s*([a-z0-9\-]+)+="([^"]*)"*/', $attrs, $m)
        || preg_match('/^\s*([a-z0-9\-]+)+=([^ ]*) */', $attrs, $m)
    ) {
        $attrs = str_replace($m[0], '', $attrs);
        $return[$m[1]] = $m[2];
    }
    return $return;
}


/**
 *
 */
function ll_attrs_to_string($attrs)
{
    $return = array();
    foreach ($attrs as $attr => $value) {
        $return[] = sprintf("%s='%s'", $attr, $value);
    }
    return implode(' ', $return);
}

function ll_modify_img($orig, $attrs, &$parameters)
{
    extract($parameters); // $aspect, $inline, $image, $strategy
    $attrs = ll_attrs_to_array($attrs);
    if (empty($attrs['src'])) {
        $parameters['error'] = 'Could not find SRC attribute';
        return $orig;
    }
    if (strpos($attrs['src'], '//') === 0) {
        $attrs['src'] = 'http:' . $attrs['src'];
    }
    if (!($attrs['src'] = http_uri_is_authorized($attrs['src']))) {
        $parameters['error'] = 'Unauthorized source';
        return $orig;
    }

    $low_quality_url = lazy_load_image_url($strategy, $aspect, 450, $attrs['src']);
    $placeholder_url = lazy_load_placeholder_url($aspect);
    $aspect_ratio = $aspect['width'] . 'x' . $aspect['height'];

    $noscript_attrs = $attrs;
    $noscript_attrs['src'] = $low_quality_url;

    $attrs['data-src'] = $attrs['src'];
    if (!$inline) {
        $attrs['src'] = lazy_load_placeholder_url($aspect);
    } else {
        $base64 = lazy_load_scale($attrs['data-src'], $aspect, 350, $strategy);
        $base64 = 'data:image/jpeg;base64,' . base64_encode($base64);
        $attrs['src'] = $base64;
        $attrs['data-aspect'] = $aspect_ratio;
    }
    $attrs['data-strategy'] = $strategy;
    $classes = empty($attrs['class']) ? array() : explode(' ', $attrs['class']);
    $classes[] = 'lazy-load hidden-no-js';
    $attrs['class'] = implode(' ', $classes);
        
    return sprintf(
        '<img %s><noscript><img %s></noscript>',
        ll_attrs_to_string($attrs),
        ll_attrs_to_string($noscript_attrs)
    );
}

if (!apikey_is_valid()) {
    $response['error'] = 'Invalid API Key';
    response_send_json($response);
}


$parameters = lazy_load_get_parameters($error, false);
$parameters['inline'] = (bool) request_query('inline');

$cache_key = ' api: ' . md5(serialize($parameters));

$response = cal_get_item($cache_key, function () use ($parameters) {
    $paramters['error'] = false;

    if (strpos($parameters['image'], '<img') === false) {
        $paramters['image'] = sprintf('<img src="%">', htmlspecialchars($image, ENT_QUOTES));
    }
    $parameters['lazyload'] = preg_replace_callback(
        '/<img([^>]*)>/',
        function ($matches) use (&$parameters) {
            return ll_modify_img($matches[0], $matches[1], $parameters);
        },
        $parameters['image']
    );
    return $parameters;
});

response_send_json($response);


