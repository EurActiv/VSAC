<?php

$config = array(

    'cal_ttl'        => (int) (60 * 60),
    'cal_quota'      => (float) (1024 * 1024 * 128), // 128MB
    'cal_driver'     => 'sqlitecache',

    'kval_ttl'    => (int) (60 * 60),
    'kval_quota'  => (float) (1024 * 1024 * 64), // 64MB
    'kval_driver' => 'sqlitekv',

    'shortener_driver'   =>  'noshorten',
    'shortener_base_url' =>  '',
    'shortener_api_key'  =>  '', 

    'http_allowed_domains' => array(
        'example.com',
        $_SERVER['SERVER_NAME'],
    ),
    'http_allowed_urls' => array(),


    'twitter_search_replace' => array(
        ' - Asset Manager' => ' #VSAC',
    ),
);
