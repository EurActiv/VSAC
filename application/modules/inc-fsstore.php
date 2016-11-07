<?php

/**
 * Utilities for building a temporary storage mechanism based on the filesystem.
 * It's for use in drivers, much in the same way the sqlite module works. Some
 * of the functions are designed to be drop-in identical to that one.
 *
 * You should use the filesystem store if:
 *
 *   * Your database will grow to big for SQLite (sometimes just 2.1GB, depending
 *     on system configuration)
 *   * The SQLite database is too slow. If you're experiencing speed issues
 *     with SQLite, direct filesystem will be faster if you forego the locking
 *     concurrency protections.
 *
 * This storage mechanism is a bit of a kludge, but it works for most things.
 * HOWEVER, do note: The directory-based structure for this storage mechanism is
 * not entirely mutually exclusive, even when using the file locking. So you
 * may experience race conditions in highly concurrent applications. For this
 * reason, it is better to use the sqlite mechanism where possible. 
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function fsstore_depends()
{
    return array('filesystem');
}

/** @see example_module_config_items() */
function fsstore_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function fsstore_sysconfig()
{
    $os = strtolower(php_uname('s'));
    if (substr($os, 0, 3) == 'win') {
        return 'Windows is not supported.';
    }
    return true;
}

/** @see example_module_test() */
function fsstore_test()
{
    return true;
}


//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//

//-- Database access ---------------------------------------------------------//

/**
 * Get a the path to an fsstore directory, creating it if it does not exist
 *
 * @param string $path the path to the store, relative to the files path
 *
 * @return string
 */
function fsstore_get_path($path)
{
    static $abspaths = array();
    if (!isset($abspaths[$path])) {
        $abspath = data_directory();
        $_path = strpos($path, $abspath . '/') === 0
                ? substr($path, strlen($abspath) + 1)
                : $path
                ;
        $_path = array_filter(explode('/', $_path));
        foreach ($_path as $p) {
            $abspath = filesystem_mkdir($abspath . '/' . $p);
        }
        $abspaths[$path] = $abspath;
    }
    return $abspaths[$path];
}

/**
 * Get the absolute path to a file we're going to work on; might not exist
 *
 * @param string $path @see fsstore_get_path()
 * @param string $subpath the path within the fsstore, relative to $path
 */
function fsstore_file_path($path, $subpath)
{
    if (strpos($subpath, '/') !== false) {
        $path .= '/' . dirname($subpath);
        $subpath = pathinfo($subpath, PATHINFO_FILENAME);
    }
    $path = fsstore_get_path($path);
    return $path . '/' . $subpath;
}

/**
 * Get a file's contents
 *
 * @param string $path @see fsstore_file_path()
 * @param string $subpath @see fsstore_file_path()
 * @param bool $lock_wait wait for locking actions to complete before fetching
 *
 * @return mixed unserialized file content, or null if does not exist
 */
function fsstore_get($path, $subpath, $lock_wait = true)
{
    if ($lock_wait) {
        fsstore_lock_wait($path, $subpath);
    }
    $file = fsstore_file_path($path, $subpath);
    if (file_exists($file)) {
        return unserialize(file_get_contents($file));
    }
    return null;
}

/**
 * Put contents in a file
 *
 * @param string $path @see fsstore_file_path()
 * @param string $subpath @see fsstore_file_path()
 * @param mixed $content the content to put, will be serialized
 * @param bool $lock lock the file while writing
 *
 * @return void
 */
function fsstore_put($path, $subpath, $content, $lock = true)
{
    if ($lock) {
        fsstore_lock_wait($path, $subpath);
        fsstore_lock($path, $subpath);
    }
    $file = fsstore_file_path($path, $subpath);
    file_put_contents($file, serialize($content));
    if ($lock) {
        fsstore_unlock($path, $subpath);        
    }

}

/**
 * Delete a file
 *
 * @param string $path @see fsstore_file_path()
 * @param string $subpath @see fsstore_file_path()
 * @param bool $lock_wait for locking actions to complete before deleting
 *
 * @return void
 */
function fsstore_delete($path, $subpath, $lock_wait = true)
{
    if ($lock_wait) {
        fsstore_lock_wait($path, $subpath);
    }
    $file = fsstore_file_path($path, $subpath);
    if (file_exists($file)) {
        unlink($file);
    }
}



//-- Utilities ---------------------------------------------------------------//

/**
 * Get the size of the data in the fs store
 *
 * @param string $path @see fsstore_get_path
 * @param array $columns the columns to calculate on, each entry should
 * be "table.column"
 *
 * @return float the size in bytes
 */
function fsstore_size($path)
{
    $path = fsstore_get_path($path);
    $cmd = 'du -s ' . escapeshellarg($path);
    exec($cmd, $response);
    $response = array_shift($response);
    $response = preg_replace('/[^0-9].*/', '', trim($response));
    return ((float) $response) * 1024; 
}


/**
 * Conduct periodic storage maintenence
 *
 * @param string $path @see fsstore_get_path()
 * @param callable $clean_cb the callback to clean files
 * @param integer $clean frequency to run the clean callback, seconds
 * @param integer $vacuum frequence to vacuum the database, seconds
 *
 * @return array(
 *     'cleaned'     => (bool) clean ran on this call
 *     'vacummed'    => (bool) the database was vacuumed on this call
 *     'last_clean'  => (int) the timestamp of the last clean run
 *     'last_vacuum' => (int) the timestamp of the last vacuum
 * );
 */
function fsstore_clean(
    $path,
    callable $clean_cb,
    $clean = 3600, // hourly
    $vacuum =  86400    // daily
) {
    $last_clean = (int) fsstore_get_meta($path, 'last_clean');
    $last_vacuum = (int) fsstore_get_meta($path, 'last_vacuum');

    if ($vaccumed = $last_vacuum < (time() - $vaccum)) {
        fsstore_set_meta($path, 'last_vaccum', time());
    }
    if ($cleaned = $vacuumed || $last_clean < (time() - $clean)) {
        fsstore_set_meta($path, 'last_clean', time());
    }
    if ($cleaned) {
        call_user_func($clean_cb);
    }
    if ($vaccumed) {
        $files = scandir(fsstore_get_path($path));
        foreach ($files as $file) {
            if (strpos($file, '.' || $file == '_meta_') === 0) {
                continue;
            }
            if (is_dir($path . '/' . $file)) {
                filesystem_rmdir($path . '/' . $file, "I've really tested this");
            } else {
                unlink($path . '/' . $file);
            }
        }
    }

    return compact('last_clean', 'last_vacuum', 'cleaned', 'vacuumed');;
}


//-- Metadata ----------------------------------------------------------------//

/**
 * Get a value from the meta table
 *
 * @param string $path @see fsstore_get_path
 * @param string $name the meta data name
 *
 * @return mixed the value, or null if not found
 */
function fsstore_get_meta($path, $name)
{
    $abspath = fsstore_get_path($path . '/_meta_') . '/' . md5($name) . '.txt';
    if (file_exists($abspath)) {
        $value = unserialize(file_get_contents($abspath));
        return $value['value'];
    }
    return null;
}

/**
 * Set a value in the meta table
 *
 * @param string $path @see fsstore_get_path
 * @param string $name the meta data name
 * @param serializable the metadata value
 */
function fsstore_set_meta($path, $name, $value)
{
    $abspath = fsstore_get_path($path . '/_meta_') . '/' . md5($name) . '.txt';
    if (is_null($value)) {
        if (file_exists($abspath)) {
            unlink($abpath);
        }
    } else {
        file_put_contents($abspath, serialize(compact('name', 'value')));
    }
}


//-- File locking ------------------------------------------------------------//

/**
 * Get a unique lock id for this process
 *
 * @return string a unique id, unique for the process
 */
function fsstore_lock_id()
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
 * @param string $path @see fsstore_get_path()
 * @param string $subpath the path within the fsstore to lock, relative to $path
 *
 * @return string the absolute path to the lock file (may not exist yet)
 */
function fsstore_lock_file($path, $subpath)
{
    return fsstore_file_path($path, $subpath) . '.lock';
}

/**
 * Wait until a locked item or locked permutation is available. Will only wait
 * for 30 seconds before it gives up.
 *
 * @param string $path @see fsstore_lock_file()
 * @param string $subpath @see fsstore_lock_file()
 * 
 * @return bool true if resource is unlocked, false if it wasn't unlocked.
 */
function fsstore_lock_wait($path, $subpath)
{
    $lock_file = fsstore_lock_file($path, $subpath);
    $lock_id = fsstore_lock_id();

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
 * Lock a resource
 *
 * @param string $path @see fsstore_lock_file()
 * @param string $subpath @see fsstore_lock_file()
 * 
 * @return bool the locking was successful
 */
function fsstore_lock($path, $subpath)
{
    $lock_file = fsstore_lock_file($path, $subpath);
    $lock_id = fsstore_lock_id();
    if (file_exists($lock_file) && file_get_contents($lock_file) == $lock_id) {
        return true;
    }
    if (!fsstore_lock_wait($path, $subpath)) {
        return false;
    }
    file_put_contents($lock_file, $lock_id);
    return file_exists($lock_file) && file_get_contents($lock_file) == $lock_id;
}

/**
 * Unlock a resource when done with it
 *
 * @param string $path @see fsstore_lock_file()
 * @param string $subpath @see fsstore_lock_file()
 *
 * @return void
 */
function fsstore_unlock($path, $subpath)
{
    $lock_file = fsstore_lock_file($path, $subpath);
    $lock_id = fsstore_lock_id();
    if (file_exists($lock_file) && file_get_contents($lock_file) == $lock_id) {
        unlink($lock_file);
    }
}

