<?php

namespace VSAC;

/**
 * Convert the attributs string from an html element to an array of key:value
 * pairs
 *
 * @param string $attrs
 * @return array
 */
function ll_attrs_to_array($img)
{
    $doc = new \DOMDocument();
    $doc->loadHTML('<html><body>' . $img . '</body></html>');
    $xpath = new \DOMXpath($doc);
    $img = $xpath->query('//img');
    if ($img->length != 1) {
        return false;
    }
    $attrs = array();
    $img = $img->item(0);
    $attrs = array();
    if ($img->hasAttributes()) {
        foreach ($img->attributes as $attr) {
            $attrs[$attr->nodeName] = $attr->nodeValue;
        }
    }
    return $attrs;
}


/**
 *
 */
function ll_attrs_to_string($attrs)
{
    $return = array();
    foreach ($attrs as $attr => $value) {
        $value =  htmlspecialchars(
            $value,
            ENT_QUOTES|ENT_HTML5,
            ini_get("default_charset"),
            false
        );
        $return[] = sprintf("%s='%s'", $attr, $value);
    }
    return implode(' ', $return);
}


function ll_modify_img($orig, &$parameters)
{
    extract($parameters); // $aspect, $inline, $image, $strategy

    if (!($attrs = ll_attrs_to_array($orig))) {
        $parameters['error'] = 'Could not parse image';
        return $orig;
    }

    if (empty($attrs['src'])) {
        $parameters['error'] = 'Could not find SRC attribute';
        return $orig;
    }

    $classes = empty($attrs['class']) ? array() : array_filter(explode(' ', $attrs['class']));
    if (in_array('lazy-load', $classes)) {
        $parameters['error'] = 'lazy-load class already exists, looks like double-encode';
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
    $attrs['data-aspect'] = $aspect_ratio;
    if (isset($attrs['srcset'])) {
        unset($attrs['srcset']);
    }
    if (isset($attrs['sizes'])) {
        unset($attrs['sizes']);
    }
    if (!$inline) {
        $attrs['src'] = lazy_load_placeholder_url($aspect);
    } else {
        $base64 = lazy_load_scale($attrs['data-src'], $aspect, 350, $strategy);
        $base64 = 'data:image/jpeg;base64,' . base64_encode($base64);
        $attrs['src'] = $base64;
    }
    $attrs['data-strategy'] = $strategy;
    $classes[] = 'lazy-load hidden-no-js';
    if ($parameters['preserve']) {
        unset($attrs['data-aspect']);
        $classes[] = 'lazy-load-preserve';
    }

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
$parameters['preserve'] = (bool) request_query('preserve');

$cache_key = ' api: ' . md5(serialize($parameters)) . uniqid();

$response = cal_get_item($cache_key, function () use ($parameters) {
    $paramters['error'] = false;

    if (strpos($parameters['image'], '<img') === false) {
        $paramters['image'] = sprintf('<img src="%">', htmlspecialchars($image, ENT_QUOTES));
    }
    $parameters['lazyload'] = preg_replace_callback(
        '/<img[^>]*>/',
        function ($matches) use (&$parameters) {
            return ll_modify_img($matches[0], $parameters);
        },
        $parameters['image']
    );
    return $parameters;
});

response_send_json($response);


