<?php

$config = array(

    'cal_ttl' => 60 * 60 * 2,
    'cal_quota' => 1.0 * 1024 * 1024 * 128,
    'cal_driver' => 'sqlitecache',

    'http_allowed_domains' => array(
        'www.feedforall.com',
    ),
    'http_allowed_urls' => array(),

    'api_key' => 'keyboard_cat',

    'callmap_driver' => 'sqlitecallmap',

    'full_content_feeds' => array(
        array(
            'handle' => 'voa-news',
            'url'    => 'http://www.voanews.com/api/zq$omekvi_',
            'xpath'  => '//*[contains(@class, "wysiwyg")]',
        ),
    ),
);

