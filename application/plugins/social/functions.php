<?php

namespace VSAC;


//----------------------------------------------------------------------------//
//-- Framework functions                                                    --//
//----------------------------------------------------------------------------//

/** @see plugins/example-plugin/example_plugin_config_items() */
function social_config_items()
{
    return array(
        [
            'twitter_search_replace',
            [],
            'An array to search and replace the title to format it for twitter.
            Format <code>["search_string"=>"replace_string"]</code>',
        ],
    );
}

/** @see plugins/example-plugin/example_plugin_bootstrap() */
function social_bootstrap()
{
	use_module('cal');
	use_module('kval');
	use_module('http');
	use_module('shortener');
}


//----------------------------------------------------------------------------//
//-- Plugin functions                                                       --//
//----------------------------------------------------------------------------//


/**
 * For storing share counts provided by user, generate a key to the URL with
 * requires salting. This stores the last three salts, with TTL of five minutes
 *
 * @return array the last three salts
 */
function social_get_salts()
{
    $file = filesystem_files_path() . '/salts.txt';
    $salts = is_file($file) ? unserialize(file_get_contents($file)) : null;
    if (!is_array($salts)) {
        $salts = array();
    }
    $invalid = time() - (60 * 15);
    foreach ($salts as $offset => $salt) {
        if ($salt['ts'] < $invalid) {
            unset($salts[$offset]);
        }
    }
    return array_values($salts);
}


/**
 * Get a currently active salt, with TTL of five minutes and TTI of 15 minutes
 *
 * @return string the salt
 */
function social_get_salt()
{
    $salts = social_get_salts();
    $time = time();
    $expires = $time - (60 * 5);
    if (empty($salts[0]) || $salts[0]['ts'] < $expires) {
        // not cryptographically secure, but good enough
        $salt = array('ts' => time(), 'salt' => rand());
        array_unshift($salts, $salt);
        file_put_contents(filesystem_files_path() . '/salts.txt', serialize($salts));
    } else {
        $salt = $salts[0];
    }
    return $salt['salt'];
}


/**
 * Generate a public key for storing a share count of a URL, with TTL of 5
 * minutes and
 * TTI of 15 minutes
 *
 * @param string $url the url to get a key for
 * @return string $key the hashed key
 */
function social_get_key($url)
{
    return hash('sha256', social_get_salt() . '#' . $url);
}


/**
 * Check that the user submitted key for a salt is valid
 *
 * @param string $key the user submitted key
 * @param string $url the user submitted URL
 *
 * @return true if valid, false if not.
 */
function social_validate_key($key, $url)
{
    $salts = social_get_salts();
    foreach ($salts as $salt) {
        $check_key = hash('sha256', $salt['salt'] . '#' . $url);
        if ($check_key == $key) {
            return true;
        }
    }
    return false;
}







