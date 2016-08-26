<?php

$config = array(

    'http_allowed_urls'   => array(),
    'http_allowed_domains'   => array(
        $_SERVER['SERVER_NAME'],
    ),

    'cal_ttl'        => (int) (60 * 60 * 6),
    'cal_quota'      => (float) (1024 * 1024 * 1024 * 10), // 10GB
    'cal_driver'     => 'fscache',
    'api_key'        => 'keyboard_cat',
    'callmap_driver' => 'sqlitecallmap',
    'aspect_ratios'     => array('16x9', '4x3', '3x2', '1x1'),

);




