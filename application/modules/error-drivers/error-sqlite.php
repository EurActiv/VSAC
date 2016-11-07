<?php

/**
 * This is the sqlite driver for the error handler.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//

//-- Framework ---------------------------------------------------------------//

/** @see error_depends() */
function error_sqlite_depends()
{
    return array('sqlite');
}

/** @see error_sysconfig() */
function error_sqlite_sysconfig()
{
    return true;
}


//-- Driver callbacks --------------------------------------------------------//

/** @see error_driver_limitations() */
function error_sqlite_driver_limitations()
{
    return '';
}

/** @see error_log() */
function error_sqlite_log($errno, $errstr, $errfile, $errline, $trace)
{
    $err_key = $errno . '#' . $errstr . '#' . $errfile . '#' . $errline;
    if (error_sqlite_get($err_key)) {
        error_sqlite_touch($err_key);
        return;
    }
    $arg_to_str = function ($arg) {
        if (is_string($arg) && strlen($arg) > 75) {
            return substr($arg, 0, 72) . '...';
        }
        if (is_object($arg)) {
            return get_class($arg);
        }
        if (is_array($arg)) {
            return '[array]';
        }
        if (is_resource($arg)) {
            return '[' . get_resource_type($arg) . ']';
        }
        return $arg;
    };
    $trace = array_map(function ($ln) use ($arg_to_str) {
        $args = array_map($arg_to_str, $ln['args']);
        $ln = array_map($arg_to_str, $ln);
        $ln['args'] = $args;
        return $ln;
    }, $trace);

    $errlog_obj = compact('errno', 'errstr', 'errfile', 'errline', 'trace');

    $sql = 'INSERT OR REPLACE INTO errlog (id, errlog_key, errlog_obj)
            VALUES ((SELECT id FROM errlog WHERE errlog_key = ?), ?, ?)';
    error_sqlite_exec($sql, $err_key, $err_key, serialize($errlog_obj));
    error_sqlite_touch($err_key);
}

/** @see error_list */
function error_sqlite_list($page = 0)
{
    $skip = $page * 50;
    $sql = "SELECT errlog_key
            FROM errlog
            ORDER BY errlog_ts DESC
            LIMIT {$skip}, 50";
    $ret = array();
    $stmt = error_sqlite_query($sql);
    while (false !== ($key = $stmt->fetchColumn())) {
        $ret[] = $key;
    }
    return $ret;
}

/** @see error_get */
function error_sqlite_get($err_key)
{
    $sql = 'SELECT * FROM errlog WHERE errlog_key = ?';
    $error = error_sqlite_query($sql, $err_key)->fetch();
    if ($error && $error['errlog_obj']) {
        $error['errlog_obj'] = unserialize($error['errlog_obj']);
        if (is_array($error['errlog_obj'])) {
            $error = array_merge($error, $error['errlog_obj']);
            unset($error['errlog_obj']);
        }
    }
    return $error;
}

/** @see error_resolve */
function error_sqlite_resolve($err_key)
{
    $sql = 'DELETE FROM errlog WHERE errlog_key = ?';
    error_sqlite_query($sql, $err_key);
}

//----------------------------------------------------------------------------//
//-- Driver-specific functions                                              --//
//----------------------------------------------------------------------------//

//-- database helpers --------------------------------------------------------//

/**
 * Touch a record in the database, incrementing its counter and updating its
 * timestamp
 *
 * @param string $err_key the key to the record
 */
function error_sqlite_touch($err_key)
{
    $sql = 'UPDATE errlog
            SET errlog_ts = ?, errlog_cnt = errlog_cnt + 1
            WHERE errlog_key = ?';
    error_sqlite_exec($sql, time(), $err_key);
}

//-- Database access ---------------------------------------------------------//

/**
 * A simple wrapper for sqlite_queryexec()
 */
function error_sqlite_queryexec($args)
{
    array_unshift($args, __NAMESPACE__ . '\\error_sqlite_create');
    array_unshift($args, '../error.sq3');
    return sqlite_queryexec($args);
}

/**
 * Functionally identical to sqlite_exec()
 */
function error_sqlite_exec($sql)
{
    return error_sqlite_queryexec(func_get_args())[0];
}

/**
 * Functionally identical to sqlite_query()
 */
function error_sqlite_query($sql)
{
    return error_sqlite_queryexec(func_get_args())[1];
}

/**
 * Create the database schema
 *
 * @private
 *
 * @return void
 */
function error_sqlite_create(\PDO $pdo)
{
    $pdo->exec('CREATE TABLE errlog (
                  id       INTEGER PRIMARY KEY, 
                  errlog_key   TEXT,
                  errlog_ts    INTEGER DEFAULT 0,
                  errlog_cnt   INTEGER DEFAULT 0,
                  errlog_obj   BLOB
                )');
    $pdo->exec('CREATE UNIQUE INDEX errlog_key_idx ON errlog (errlog_key)');
}




