<?php

/**
 * This is the filesystem based cache driver for the Cache Abstraction Layer
 * (cal_*). It tries to cache items in folders on disk. It's a bit of a kludge,
 * but it works for most things. It's useful for systems that can't handle big
 * sqlite databases.
 *
 * NOTE: The directory-based structure for this cache system is not entirely
 * mutually exclusive, and may occasionally have race condition errors. There
 * is a built-in locking mechanism that prevents most issues, but highly
 * concurrent applications may have errors. For this reason, it is better to
 * use the sqlitecache driver where possible. 
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//

//-- Framework ---------------------------------------------------------------//

/** @see cal_sysconfig() */
function fscache_sysconfig()
{
    $os = strtolower(php_uname('s'));
    if (substr($os, 0, 3) == 'win') {
        return 'Windows is not supported, cache cleaning and size calculation will not work.';
    }
    return true;
}

//-- Utilities ---------------------------------------------------------------//

/** @see cal_size() */
function fscache_size()
{
    $cmd = 'du -s ' . escapeshellarg(fscache_path());
    exec($cmd, $response);
    $response = array_shift($response);
    $response = preg_replace('/[^0-9].*/', '', trim($response));
    return ((float) $response) * 1024; 
}

/** @see cal_clean() */
function fscache_clean($invalidate = 3600, $vacuum =  86400)
{
    // last invalidate for compat with sqlitecache    
    $last_invalidate = (int) fscache_get_meta('last_invalidate');
    $last_vacuum = (int) fscache_get_meta('last_vacuum');
    $vacuumed = $invalidated = false;

    if (true || $last_vacuum < (time() - $invalidate)) {
        $cache_path = fscache_path();
        fscache_set_meta('last_invalidate', time());
        fscache_set_meta('last_vacuum', time());
        $last_vacuum = $last_invalidate = time();
        filesystem_files_rmdir($cache_path, true);
        $last_vacuum = $last_invalidate = time();
        $vacuumed = $invalidated = true;
    }

    return compact('last_invalidate', 'last_vacuum', 'invalidated', 'vacuumed');
}

//-- Metadata ----------------------------------------------------------------//

/** @see cal_get_meta() */
function fscache_get_meta($name)
{ 
    $path = fscache_path() . 'meta-' . md5($name) . '.txt';
    if (!file_exists($path)) {
        return null;
    }
    $contents = @unserialize(file_get_contents($path));
    return isset($contents['value']) ? $contents['value']: null;
}

/** @see cal_set_meta() */
function fscache_set_meta($name, $value)
{
    $path = fscache_path() . 'meta-' . md5($name) . '.txt';
    file_put_contents($path, serialize(compact('name', 'value')));
}

//-- Actual caching ----------------------------------------------------------//

/** @see cal_get_item() */
function fscache_get_item($identifier, callable $refresh)
{
    $item_id = fscache_get_item_id($identifier, $refresh);
    return fscache_get_item_content($item_id);
}

/** @see cal_get_permutation */
function fscache_get_permutation(
    $item_identifier,
    callable $refresh_item,
    $permutation_identifier,
    callable $refresh_permutation
) {
    $iid = fscache_get_item_id($item_identifier, $refresh_item);
    $pid = fscache_get_permutation_id($iid, $permutation_identifier, $refresh_permutation);
    return fscache_get_permutation_content($pid);
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//

//-- Utilities ---------------------------------------------------------------//

/**
 * Get the path to the cache directory, create it if it does not exist.
 *
 * @private
 *
 * @return string
 */
function fscache_path()
{
    return filesystem_mkdir(filesystem_files_path() . 'fscache');
}


//-- Top level items ---------------------------------------------------------//


/**
 * Similar to cal_get_item(), but returns the subdir in which the item lives
 *
 * @private
 *
 * @return string
 */
function fscache_get_item_id($identifier, callable $refresh)
{
    $item_id = md5($identifier);
    $dir = fscache_path() . $item_id . '/'; 

    fscache_lock_wait($item_id);
    $item = is_dir($dir) && is_file($dir . 'item') && is_file($dir . 'meta')
          ? unserialize(file_get_contents($dir . 'meta'))
          : false;
    
    if ($item && (!$item['expire'] || $item['expire'] >= time())) {
        return $item_id;
    }

    // lock will be released in fscache_touch_item or fscache_insert_item
    fscache_lock($item_id); 

    $content = call_user_func($refresh, $identifier);

    // error handling: if there's an old item, touch it, otherwise store null
    // for five minutes to let the source cool off
    if ($content === null) { 
        if ($item) {
            return fscache_touch_item($item_id);
        } else {
            return fscache_insert_item($identifier, null, 60 * 5);
        }
    }

    // if there's an old item but it hasn't changed, avoid inserting new
    // items because that will cause all the permutations to recalculate
    if ($item) {
        $old_content = fscache_get_item_content($item_id);
        if ($old_content === $content) {
            return fscache_touch_item($item_id);
        }
    }

    // At this point, we can just insert the item
    return fscache_insert_item($identifier, $content);
}

/**
 * Insert an item in the database
 *
 * @private
 *
 * @param string $identifier @see cal_get_item()
 * @param mixed $content anything that can be serialized
 * @param integer $expire expire the item in seconds, defaults to global setting
 * @return mixed driver specific item id
 */
function fscache_insert_item($identifier, $content, $expire = null)
{
    $item_id = md5($identifier);
    // lock will be released in fscache_touch_item
    fscache_lock($item_id);
    $dir = fscache_path() . $item_id . '/';
    foreach (scandir($dir) as $file) {
        if (strpos($file, '.') !== 0 && $file != 'lock') {
            unlink($dir . $file);
        }
    }
    file_put_contents($dir . 'item', serialize($content));
    return fscache_touch_item($item_id, $expire);
}

/**
 * "Touch" an item, that is make it as if it is fresh now
 *
 * @private
 *
 * @param integer $item_id the directory containing the item
 * @param integer $expire expires in this many seconds, defaults to global config
 * @return void
 */
function fscache_touch_item($item_id, $expire = null)
{
    if (is_null($expire)) {
        $expire = config('cal_ttl', 0);
    }
    if ($expire) {
        $now = (int) time();
        $invalidate = $now + ($expire * 2);
        $expire = $now + $expire;
    } else {
        $invalidate = 0;
    }
    fscache_lock($item_id);
    $file = fscache_path() . $item_id . '/meta';
    file_put_contents($file, serialize(compact('expire')));
    fscache_unlock($item_id);
    return $item_id;
}

/**
 * Get the unserialized content of an item
 *
 * @private
 *
 * @param string $item_id the subdirectory containing the item
 * @return mixed
 */
function fscache_get_item_content($item_id)
{
    $file = fscache_path() . $item_id . '/item';
    return file_exists($file)
         ? unserialize(file_get_contents($file))
         : null
         ; 
}


//-- Permutations ------------------------------------------------------------//


/**
 * Like cal_get_permutation, but returns the relative path to the identifier
 * instead
 *
 * @private
 *
 * @return string;
 */
function fscache_get_permutation_id($item_id, $identifier, $create)
{
    $dir = fscache_path();
    $permutation_id = $item_id . '/' . md5($identifier);
    fscache_lock_wait($item_id);
    fscache_lock_wait($permutation_id);

    if (is_file($dir . $permutation_id)) {
        return $permutation_id;
    }
    // lock released in fscache_insert_permutation
    fscache_lock($permutation_id);
    $content = fscache_get_item_content($item_id);
    $permutation = $content === null
                 ? null
                 : call_user_func($create, $content, $identifier)
                 ;
    return fscache_insert_permutation($item_id, $identifier, $permutation);
}

/**
 * Insert a generated permutation into the database
 *
 * @private
 *
 * @param string $item_id the item subdirectory
 * @param string $identifier the permutation identifier
 * @param mixed $permutation the permutation content
 * @return string the relative path to the permutation
 */
function fscache_insert_permutation($item_id, $identifier, $permutation)
{
    $permutation_id = $item_id . '/' . md5($identifier);
    if (is_null($permutation)) {
        fscache_unlock($permutation_id);
        return null;
    }
    file_put_contents(fscache_path() . $permutation_id, serialize($permutation));
    fscache_unlock($permutation_id);
    return $permutation_id;
}

/**
 * Get the unserialized content of a permutation
 *
 * @private
 *
 * @param string $permutation_id the relative path to the permutation
 * @return mixed
 */
function fscache_get_permutation_content($permutation_id)
{
    if (!$permutation_id) {
        return null;
    }
    fscache_lock_wait($permutation_id);
    $file = fscache_path() . $permutation_id;
    return file_exists($file) ? unserialize(file_get_contents($file)) : null;
}

//-- File locking ------------------------------------------------------------//

/**
 * Get a unique lock id for this process
 *
 * @return string a unique id, unique for the process
 */
function fscache_lock_id()
{
    static $lock_id = null;
    if (is_null($lock_id)) {
        $lock_id = uniqid();
    }
    return $lock_id;
}

/**
 * Get the path to a lock file for either an item id or permutation id
 *
 * @param string $id either the item id or permutation id
 *
 * @return string the absolute path to the lock file (may not exist yet)
 */
function fscache_lock_file($id)
{
    if (strpos($id, '/') === false) {
        $dir = fscache_path() . $id;
        if (!is_dir($dir)) {
            @mkdir($dir);
        }
        return $dir .  '/lock';
    }
    list($item_id, $permutation_id) = explode('/', $id, 2);
    return fscache_lock_file($item_id) . '.' . $permutation_id;
}

/**
 * Wait until a locked item or locked permutation is available. Will only wait
 * for 30 seconds before it gives up.
 *
 * @param $item_id the item id or permutation id
 * 
 * @return bool true if resource is unlocked, false if it wasn't unlocked.
 */
function fscache_lock_wait($item_id)
{
    $lock_file = fscache_lock_file($item_id);
    $lock_id = fscache_lock_id();

    $breakout = time() + 30;
    for ($i = 0; $i < 300; $i += 1) {
        if (!file_exists($lock_file)) {
            return true;
        }
        if (file_get_contents($lock_file) == $lock_id) {
            return true;
        }
        // a previous locking process died
        if (filemtime($lock_file) < (time() - 30)) {
            unlink($lock_file);
            return true;
        }
        usleep(100000); // 0.1 seconds
    }
    return false;
}

/**
 * Lock a resourse
 *
 * @param $item_id the item id or permutation id
 * 
 * @return bool the locking was successful
 */
function fscache_lock($item_id)
{
    $lock_file = fscache_lock_file($item_id);
    $lock_id = fscache_lock_id();
    if (file_exists($lock_file) && file_get_contents($lock_file) == $lock_id) {
        return true;
    }
    if (!fscache_lock_wait($item_id)) {
        return false;
    }
    file_put_contents($lock_file, $lock_id);
    return file_exists($lock_file) && file_get_contents($lock_file) == $lock_id;
}

/**
 * Unlock a resource when done with it
 *
 * @param $item_id the item id or permutation id
 *
 * @return void
 */
function fscache_unlock($item_id)
{
    $lock_file = fscache_lock_file($item_id);
    $lock_id = fscache_lock_id();
    if (file_exists($lock_file) && file_get_contents($lock_file) == $lock_id) {
        unlink($lock_file);
    }
}

