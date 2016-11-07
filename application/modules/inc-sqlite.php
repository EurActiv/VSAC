<?php

/**
 * Utilities for interfacing with an SQLite database. Normally should only be
 * used by drivers to maintain compatability with systems that don't have it
 * installed.
 *
 * Primary limitation of this module is database size. Namely, some combinations
 * of OS and filesystem (32 bit linux, simfs) will only allow you to have files
 * of 2.1GB in size before they just crash.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function sqlite_depends()
{
    return array();
}

/** @see example_module_config_items() */
function sqlite_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function sqlite_sysconfig()
{
    $drivers = \PDO::getAvailableDrivers();
    if (!in_array('sqlite', $drivers)) {
        return 'SQLite PDO Driver not installed';
    }
    if (!class_exists('\\SQLite3')) {
        return 'SQLite3 extension is not installed';
    }
    $version = \SQLite3::version()['versionString'];
    if (!version_compare($version, '3.6.19', '>=')) {
        return 'Minimum of SQLite 3.6.19 required';
    }
    return true;
}

/** @see example_module_test() */
function sqlite_test()
{
    return true;
}


//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//

//-- Database access ---------------------------------------------------------//

/**
 * Get a connection to an sqlite database.
 *
 * @param string $path the path to the database, relative to the files path
 * @param callable $create the callback to create the 
 */
function sqlite_get_connection($path, callable $create)
{
    static $connections = array();
    if (!isset($connections[$path])) {
        $abspath = filesystem_files_path() . '/' . $path;
        $requires_create = !file_exists($abspath) || !filesize($abspath);
        $pdo = new \PDO('sqlite:' . $abspath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        if ($requires_create) {
            $pdo->exec('CREATE TABLE meta (
                            id    INTEGER PRIMARY KEY, 
                            name  TEXT,
                            value TEXT
                        )');
            $pdo->exec('CREATE UNIQUE INDEX meta_name_idx ON meta (name)');
            call_user_func($create, $pdo);
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
        $connections[$path] = $pdo;
    }
    return $connections[$path];
}

/**
 * Execute a prepared statement, return both the pdo statement and the pdo
 * object. Used by sqlite_query() and sqlite_exec();
 *
 * @param array $args func_get_args from calling function
 *
 * @return array[\PDO, \PDOStatement]
 */
function sqlite_queryexec($args)
{
    $path = array_shift($args);
    $create = array_shift($args);
    $sql = array_shift($args);

    $pdo = sqlite_get_connection($path, $create);
    $stmt = $pdo->prepare($sql);

    if (empty($args)) {
        $stmt->execute();
    } else {
        $stmt->execute($args);
    }
    return array($pdo, $stmt);

}

/**
 * Execute a prepared statement, return the pdo statement; best for SELECT
 *
 * @param string $path @see sqlite_get_connection()
 * @param callable $create @see sqlite_get_connection()
 * @param string $sql the statement to execute
 * @param array mixed $params... parameters to bind to the statement
 *
 * @return \PDOStatement
 */
function sqlite_query($path, callable $create, $sql)
{
    list($pdo, $stmt) = sqlite_queryexec(func_get_args());
    return $stmt;
}

/**
 * Execute a prepared statement, return the pdo pdo; best for non-SELECT
 *
 * @param string $path @see sqlite_get_connection()
 * @param callable $create @see sqlite_get_connection()
 * @param string $sql the statement to execute
 * @param array mixed $params... parameters to bind to the statement
 *
 * @return \PDO
 */
function sqlite_exec($path, callable $create, $sql)
{
    list($pdo, $stmt) = sqlite_queryexec(func_get_args());
    return $pdo;
}



//-- Utilities ---------------------------------------------------------------//

/**
 * Get the size of the data in the sqlite database
 *
 * @param string $path @see sqlite_get_connection
 * @param callable $create @see sqlite_get_connection
 * @param array $columns the columns to calculate on, each entry should
 * be "table.column"
 *
 * @return float the size in bytes
 */
function sqlite_size($path, callable $create, array $columns)
{
    $size = 0.0;
    foreach ($columns as $column) {
        list($t, $c) = explode('.', $column);
        $sql = sprintf('SELECT SUM(LENGTH(HEX(%s))/2) FROM %s', $c, $t);
        $size += (float) sqlite_query($path, $create, $sql)->fetchColumn();
    }
    return $size;
}

/**
 * Get the real size on disk of the sqlite database
 *
 * @param string $path @see sqlite_get_connection
 * @param callable $create @see sqlite_get_connection
 *
 * @return float the size in bytes
 */
function sqlite_size_disk($path, callable $create)
{
    $pdo = sqlite_get_connection($path, $create);
    $abspath = filesystem_files_path() . '/' . $path;
    return (float) filesize($abspath);
}

/**
 * Conduct periodic database maintenence
 *
 * @param string $path @see sqlite_get_connection
 * @param callable $create @see sqlite_get_connection
 * @param callable $clean_cb the callback to clean the database
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
function sqlite_clean(
    $path,
    callable $create,
    callable $clean_cb,
    $clean = 3600, // hourly
    $vacuum =  86400    // daily
) {
    $last_clean = (int) sqlite_get_meta($path, $create, 'last_clean');
    $last_vacuum = (int) sqlite_get_meta($path, $create, 'last_vacuum');

    if ($vaccumed = $last_vacuum < (time() - $vaccum)) {
        sqlite_set_meta($path, $create, 'last_vaccum', time());
    }
    if ($cleaned = $vacuumed || $last_clean < (time() - $clean)) {
        sqlite_set_meta($path, $create, 'last_clean', time());
    }
    if ($cleaned) {
        call_user_func($clean_cb);
    }
    if ($vaccumed) {
        sqlite_query($path, $create, 'VACUUM');
    }

    return compact('last_invalidate', 'last_vacuum', 'invalidated', 'vacuumed');
}


//-- Metadata ----------------------------------------------------------------//

/**
 * Get a value from the meta table
 *
 * @param string $path @see sqlite_get_connection
 * @param callable $create @see sqlite_get_connection
 * @param string $name the meta data name
 *
 * @return mixed the value, or null if not found
 */
function sqlite_get_meta($path, callable $create, $name)
{
    $sql = 'SELECT value FROM meta WHERE name = ?';
    $meta = sqlite_query($path, $create, $sql, $name)->fetchColumn();
    return $meta ? unserialize($meta) : null;
}

/**
 * Set a value in the meta table
 *
 * @param string $path @see sqlite_get_connection
 * @param callable $create @see sqlite_get_connection
 * @param string $name the meta data name
 * @param serializable the metadata value
 */
function sqlite_set_meta($path, callable $create, $name, $value)
{
    if (is_null($value)) {
        $sql = 'DELETE FROM meta WHERE name = ?';
        sqlite_exec($path, $create, $sql, $name);
    } else {
        $sql = 'INSERT OR REPLACE INTO meta (id, name, value)
                VALUES ((SELECT id FROM meta WHERE name = ?), ?, ?)';
        sqlite_exec($path, $create, $sql, $name, $name, serialize($value));
    }
}

