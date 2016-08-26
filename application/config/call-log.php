<?php

$config = array(

    'http_allowed_urls'   => array(),
    'http_allowed_domains'   => array(
        $_SERVER['SERVER_NAME'],
        'httpbin.org',
    ),

    'api_key'        => 'keyboard_cat',
    'callmap_driver' => 'sqlitecallmap',

);

