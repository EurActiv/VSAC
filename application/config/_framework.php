<?php

$config = array(

    // these are the application wide settings
    'pretty_urls'  => true,
    'description'  => 'Overwrite with your application description',
    'legal'        => 'Any disclaimer you may have.',
    'app_name'     => 'Very Simple Asset Coordinator',
    'gaq_account'  => '',
    'bootswatch'   => '',
    'https_enabled'=> true,
    'http_enabled' => true,
    'users'        => array(
        'example'    => 'An Example Passphrase.',
    ),

    // default configuration for error handling
    'error_driver' => 'noop',

    // default configuration for call map logging
    'callmap_driver'            => 'noop',
    'callmap_labels'            => array(),
    'callmap_visualize_default' => array(),
    'callmap_probability'       => 1000,

    // default configuration of apikey module
    'api_key'        => 'keyboard_cat',


    // default configuration of http module
    'http_allowed_urls'   => array(),
    'http_allowed_domains'   => array(
        empty($_SERVER['SERVER_NAME']) ? 'localhost' : $_SERVER['SERVER_NAME'],
        'httpbin.org',
        'example.com',
    ),

    // default configuration of cache abstraction layer (cal) module
    'cal_ttl'    => 60 * 60,                 // 1 hour
    'cal_driver' => 'noop',


    // default configuration of key-value store (kval) module
    'kval_ttl'    => (int) (60 * 60),            // 1 hour
    'kval_driver' => 'noop',

    // default configuration of the URL shortener module
    'shortener_driver'   =>  'noop',
    'shortener_base_url' =>  '',
    'shortener_api_key'  =>  '', 

);

