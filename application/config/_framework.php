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
    'error_driver' => 'sqliteerror',
    // default configuration for call map logging
    'callmap_driver' => 'sqlitecallmap',
    // default configuration of apikey module
    'api_key'        => 'keyboard_cat',


    // default configuration of http module
    'http_allowed_urls'   => array(),
    'http_allowed_domains'   => array(
        $_SERVER['SERVER_NAME'],
        'httpbin.org',
        'example.com',
    ),

    // default configuration of cache abstraction layer (cal) module
    'cal_ttl'    => 60 * 60,                 // 1 hour
    'cal_quota'  => 1.0 * 1024 * 1024 * 128, // 128MB
    'cal_driver' => 'sqlitecache',


    // default configuration of key-value store (kval) module
    'kval_ttl'    => (int) (60 * 60),            // 1 hour
    'kval_quota'  => (float) (1024 * 1024 * 64), // 64MB
    'kval_driver' => 'sqlitekv',

    // default configuration of the URL shortener module
    'shortener_driver'   =>  'noshorten',
    'shortener_base_url' =>  '',
    'shortener_api_key'  =>  '', 


    // testing
    'bitly_shortener_base_url'     =>  'j.mp',
    'bitly_shortener_api_key'      =>  '',
    'noshorten_shortener_base_url' =>  'example.com',
    'noshorten_shortener_api_key'  =>  'keyboard_cat',

);

