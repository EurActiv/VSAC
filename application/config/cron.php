<?php

$config = array(
    'log_per_file' => 3,
    'log_keep_files' => 72,
	'cron_jobs' => array(
	    '0 0,6,12,18 * * * lazy-load/clean-cache.php',
	    '0 0,6,12,18 * * * feeds/clean-cache.php',
	    '0 0,6,12,18 * * * social/clean-cache.php',
	),
	'cron_safety' => 60,
	'api_key'   => 'keyboard_cat',
	'kval_driver' => 'sqlite',
);
