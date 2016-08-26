<?php

$config = array(

    // necessary for testing
    'http_allowed_domains' => array(
        'www.feedforall.com',
    ),

    // load VOA by default
    'full_content_feeds' => array(
        array(
            'handle' => 'voa-news',
            'url'    => 'http://www.voanews.com/api/zq$omekvi_',
            'xpath'  => '//*[contains(@class, "wysiwyg")]',
        ),
    ),
);

