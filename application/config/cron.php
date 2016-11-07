<?php

$config = array(
    'log_per_file' => 3,
    'log_keep_files' => 72,
	'cron_jobs' => array(
	    '* * * * * lazy-load/clean-cache.php',
	    '* * * * * feeds/clean-cache.php',
	    '* * * * * social/clean-cache.php',
	),
	'cron_safety' => 60,
	'api_key'   => 'keyboard_cat',
);
