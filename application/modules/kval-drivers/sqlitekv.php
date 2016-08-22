<?php

/**
 * This is the sqlite driver for the Key-Value Abstraction Layer (kval).
 *
 * Primary limitation of this driver is database size. Namely, some combinations
 * of OS and filesystem (32 bit linux, simfs) will only allow you to have files
 * of 2.1GB in size before they just crash.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//

//-- Framework ---------------------------------------------------------------//

/** @see kval_sysconfig() */
function sqlitekv_sysconfig()
{
    $drivers = \PDO::getAvailableDrivers();
    if (!in_array('sqlite', $drivers)) {
        return 'SQLite PDO Driver not installed';
    }
    if (!class_exists('\\SQLite3')) {
        return 'SQLite3 extension is not installed';
    }
    return true;
}

//-- Utilities ---------------------------------------------------------------//

/** @see kval_size() */
function sqlitekv_size()
{
    $value_size = sqlitekv_query(
        'SELECT SUM(LENGTH(HEX(kv_value))/2) FROM kv'
    )->fetchColumn();

    $key_size = sqlitekv_query(
        'SELECT SUM(LENGTH(HEX(kv_key))/2) FROM kv'
    )->fetchColumn();
    return ((float) $value_size) + ((float) $key_size);
}

/** @see kval_clean() */
function sqlitekv_clean($invalidate = 3600, $vacuum =  86400)
{
    $last_invalidate = (int) sqlitekv_get('__last_invalidate', 0);
    $last_vacuum = (int) sqlitekv_get('__last_vacuum', 0);
    $vacuumed = $invalidated = false;

    $invalid_time = time() - $invalidate;
    if ($last_invalidate < $invalid_time) {
        sqlitekv_set('__last_invalidate', time());
        sqlitekv_query("DELETE FROM kv WHERE kv_ts < ?", $invalid_time);
        $invalidated = true;
    }

    $vacuum_time = time() - $vacuum;
    if ($last_vacuum < $vacuum_time) {
        sqlitekv_set('__last_vacuum', time());
        $disk_quota = config('kval_quota', 0.0);
        while ($disk_quota && sqlitekv_size() > $disk_quota) {
            sqlitekv_query("DELETE FROM kv ORDER BY kv_ts ASC LIMIT 0, 1");
        }
        sqlitekv_query("VACUUM");
        $vacuumed = true;
    }
    return compact('last_invalidate', 'last_vacuum', 'invalidated', 'vacuumed');
}

//-- The actual key:value functions ------------------------------------------//

/** @see kval_key() */
function sqlitekv_key($key)
{
    $key = strtolower($key);
    if (strlen($key) > 100) { // to fit in a terminal window
        $suffix = md5($key);
        $prefix = substr($key, 0, (-1 * strlen($key)));
        $key = $suffix . $prefix;
    }
    return $key;
}

/** @see kval_get_meta() */
function sqlitekv_get($key, $expires = null)
{
    if (is_null($expires)) {
        $expires = config('kval_ttl', 0);
    }
    $expires_ts = ($expires && $expires > 0) ? time() - $expires : 0;
    $value = sqlitekv_query(
        "SELECT kv_value FROM kv WHERE kv_key = ? AND kv_ts > ?",
        array(sqlitekv_key($key), $expires_ts)
    )->fetchColumn();
    return $value ? @unserialize($value) : null;
}

/** @see kval_set() */
function sqlitekv_set($key, $value)
{
    if (is_null($value)) {
        return sqlitekv_delete($key);
    }
    $_key = sqlitekv_key($key);
    sqlitekv_query(
        "INSERT OR REPLACE INTO kv (id, kv_key, kv_value)
         VALUES ((SELECT id FROM kv WHERE kv_key = ?), ?, ?)",
        array($_key, $_key, serialize($value))
    );
    sqlitekv_touch($key);
}

/** @see kval_value() */
function sqlitekv_value($key, $expires, callable $create)
{
    $existing = sqlitekv_get($key, $expires);
    if (!is_null($existing)) {
        return $existing;
    }
    $value = call_user_func($create);
    if (is_null($value)) {
        sqlitekv_touch($key);
    } else {
        sqlitekv_set($key, $value);
    }
    return sqlitekv_get($key);    
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//


/**
 * Get a connection to the sqlite cache
 *
 * @private
 *
 * @return \PDO
 */
function sqlitekv_get_connection()
{
    static $connection;
    if (is_null($connection)) {
        $path = filesystem_files_path() . 'kv.sq3';
        $create = !file_exists($path) || !filesize($path);
        $connection = new \PDO('sqlite:'.$path);
        $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        if ($create) {
            sqlitekv_create($connection);
        }
    }
    return $connection;
}

/**
 * Run a prepared against the sqlite database
 *
 * @private
 *
 * @param string $sql the SQL to prepare
 * @param string $params parameters to bind to the statement
 * @param bool $return_stmt return the prepared statement instead of the db connection
 * @return \PDO or \PDOStatement, depending on value of $return_stmt
 */
function sqlitekv_query($sql, $params = null, $return_stmt = true)
{
    $pdo = sqlitekv_get_connection();
    $stmt = $pdo->prepare($sql);
    if (!is_null($params) && !is_array($params)) {
        $params = array($params);
    }
    $status = is_null($params) ? $stmt->execute() : $stmt->execute($params);
    return $return_stmt ? $stmt : $pdo;
}

/**
 * Create the database schema
 *
 * @private
 *
 * @return void
 */
function sqlitekv_create(\PDO $pdo)
{
    $pdo->exec('CREATE TABLE kv (
                  id       INTEGER PRIMARY KEY, 
                  kv_key   TEXT,
                  kv_ts    INTEGER DEFAULT 0,
                  kv_value BLOB
                )');
    $pdo->exec('CREATE UNIQUE INDEX kv_key_idx ON kv (kv_key)');
}


/**
 * Touch a key (make it valid from now) if it exists.
 *
 * @private
 *
 * @param string $key @see kval_key
 * @return void
 */
function sqlitekv_touch($key)
{
    sqlitekv_query(
        "UPDATE kv SET kv_ts = ? WHERE kv_key = ?",
        array(time(), sqlitekv_key($key))
    );
}

/**
 * Delete a key:value if it exists.
 *
 * @private
 *
 * @param string $key @see kval_key
 * @return void
 */
function sqlitekv_delete($key)
{
    sqlitekv_query("DELETE FROM kv WHERE kv_key = ?", sqlitekv_key($key));
}






