<?php

namespace VSAC;

$parameters = lazy_load_get_parameters($error);

if ($error) {
    http_response_code(400, $error);
    lazy_load_placeholder($parameters['aspect']);
}

$image = lazy_load_scale(
    $parameters['image'],
    $parameters['aspect'],
    $parameters['width'],
    $parameters['strategy']
);

callmap_log($parameters['image']);

response_send(
    $image,
    array('Content-Type' => 'image/jpeg')
);







