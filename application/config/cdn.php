<?php

$config = array(
	'example_file' => 'bootstrap/3.3.5/css/bootstrap.css',
	'domain_map' => array(
        array(
            'name'    => 'Bootstrap (bootstrapcdn)',
            'regex'   => '#^bootstrap/#i',
            'domain'  => 'http://maxcdn.bootstrapcdn.com/',
        ),
        array(
            'name'    => 'Bootswatch (bootstrapcdn)',
            'regex'   => '#^bootswatch/#i',
            'domain'  => 'http://maxcdn.bootstrapcdn.com/',
        ),
        array(
            'name'    => 'Font Awesome (bootstrapcdn)',
            'regex'   => '#^font-awesome/#',
            'domain'  => 'http://maxcdn.bootstrapcdn.com/',
        ),
        array(
            'name'    => 'jQuery (code.jquery.com)',
            'regex'   => '#^jquery-#',
            'domain'  => 'http://code.jquery.com/',
        ),
        array(
            'name'    => 'Google Analytics (ga.js',
            'regex'   => '#^ga.js#',
            'domain'  => 'http://www.google-analytics.com/',
        ),
        array(
            'name'    => 'JS delivr (jsdelivr.com) [most likely match]',
            'regex'   => '#^[a-z0-9\.]+/#i',
            'domain'  => 'http://cdn.jsdelivr.net/',
        ),
    ),
    'api_key' => 'keyboard_cat',
    'http_allowed_domains' => array(),
    'http_allowed_urls' => array(),
    'callmap_driver' => 'sqlitecallmap',
);
