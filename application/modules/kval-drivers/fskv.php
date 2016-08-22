<?php

/**
 * This is the filesystem based key:value driver for the Key-Value Abstraction
 * Layer (kval_*). It tries to cache items in folders on disk. It's a bit of a
 * kludge, but it works for most things. It's useful for systems that can't
 * handle big sqlite databases.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//

//-- Framework ---------------------------------------------------------------//

/** @see kval_sysconfig */
function fskv_sysconfig() {
    $os = strtolower(php_uname('s'));
    if (substr($os, 0, 3) == 'win') {
        return 'Windows is not supported, cache cleaning and size calculation will not work.';
    }
    return true;
}

//-- Utilities ---------------------------------------------------------------//


/** @see kval_size() */
function fskv_size()
{
    $cmd = 'du -s ' . escapeshellarg(fskv_path());
    exec($cmd, $response);
    $response = array_shift($response);
    $response = preg_replace('/[^0-9].*/', '', trim($response));
    return ((float) $response) * 1024;
}

/** @see kval_clean() */
function fskv_clean($invalidate = 3600, $vacuum =  86400)
{
    // last invalidate for compat with sqlitekv    
    $last_invalidate = (int) fskv_get('__last_invalidate', 0);
    $last_vacuum = (int) fskv_get('__last_vacuum', 0);
    $vacuumed = $invalidated = false;

    $invalid_time = time() - $invalidate;

    if ($last_vacuum < $invalid_time) {
        $kv_path = fskv_path();
        fskv_set('__last_invalidate', time());
        fskv_set('__last_vacuum', time());
        $last_vacuum = $last_invalidate = time();

        foreach (scandir($kv_path) as $file) {
            if (strpos($file, '.') !== 0 && is_file($kv_path . $file)) {
                if (filemtime($kv_path . $file) < $invalid_time) {
                    unlink($kv_path . $file);
                }
            }
        }
        $vacuumed = $invalidated = true;
    }

    return compact('last_invalidate', 'last_vacuum', 'invalidated', 'vacuumed');
}

//-- The actual key:value functions ------------------------------------------//

/** @see kval_key() */
function fskv_key($key)
{
    $key = strtolower($key);
    $key = preg_replace('/[^a-z0-9\-_]/', '_', $key);
    if (strlen($key) > 100) { // to fit in a terminal window
        $suffix = md5($key);
        $prefix = substr($key, 0, (-1 * strlen($key)));
        $key = $suffix . $prefix;
    }
    return $key;
}

/** @see kval_get_meta() */
function fskv_get($key, $expires = null)
{ 
    $path = fskv_path() . fskv_key($key);
    if (!file_exists($path)) {
        return null;
    }
    if (is_null($expires)) {
        $expires = config('kval_ttl', 0);
    }
    if ($expires && $expires > 0) {
        if (filemtime($path) < (time() - $expires)) {
            return null;
        }
    }

    return @unserialize(file_get_contents($path));
}

/** @see kval_set() */
function fskv_set($key, $value)
{
    if (is_null($value)) {
        return fskv_delete($key);
    }
    $existing = fskv_get($key, 0);
    if (!is_null($existing) && $existing === $value) {
        return fskv_touch($key);
    }
    
    $path = fskv_path() . fskv_key($key);
    file_put_contents($path, serialize($value));
}

/** @see kval_value() */
function fskv_value($key, $expires, callable $create)
{
    $existing = fskv_get($key, $expires);
    if (!is_null($existing)) {
        return $existing;
    }
    $value = call_user_func($create);
    if (is_null($value)) {
        fskv_touch($key);
    } else {
        fskv_set($key, $value);
    }
    return fskv_get($key);    
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//

/**
 * Get the path to the storage directory, create it if it does not exist.
 *
 * @private
 *
 * @return string
 */
function fskv_path()
{
    return filesystem_mkdir(filesystem_files_path() . 'fskv');
}

/**
 * Touch a key (make it valid from now) if it exists.
 *
 * @private
 *
 * @param string $key @see kval_key
 * @return void
 */
function fskv_touch($key)
{
    $path = fskv_path() . fskv_key($key);
    if (file_exists($path)) {
        touch($path);
    }
}

/**
 * Delete a key:value if it exists.
 *
 * @private
 *
 * @param string $key @see kval_key
 * @return void
 */
function fskv_delete($key)
{
    $path = fskv_path() . fskv_key($key);
    if (file_exists($path)) {
        unlink($path);
    }
}


