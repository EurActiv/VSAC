<?php

/**
 * This is the sqlite driver for the error handler.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//

//-- Framework ---------------------------------------------------------------//

/** @see error_sysconfig() */
function sqliteerror_sysconfig()
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


//-- Driver callbacks --------------------------------------------------------//

/** @see error_driver_limitations() */
function sqliteerror_driver_limitations()
{
    return '';
}

/** @see error_log() */
function sqliteerror_log($errno, $errstr, $errfile, $errline, $trace)
{
    $err_key = $errno . '#' . $errstr . '#' . $errfile . '#' . $errline;
    if (sqliteerror_get($err_key)) {
        sqliteerror_touch($err_key);
        return;
    }

    $errlog_obj = compact('errno', 'errstr', 'errfile', 'errline', 'trace');
    try {
        $errlog_obj = serialize($errlog_obj);
    } catch (\Exception $e) {
        $errlog_obj['trace'] = array_map(function ($line) {
            $line['args'] = false;
            return $line;
        }, $errlog_obj['trace']); 
        $errlog_obj = serialize($errlog_obj);
    }

    sqliteerror_query(
        "INSERT OR REPLACE INTO errlog (id, errlog_key, errlog_obj)
         VALUES ((SELECT id FROM errlog WHERE errlog_key = ?), ?, ?)",
        array($err_key, $err_key, $errlog_obj)
    );
    sqliteerror_touch($err_key);
}

/** @see error_list */
function sqliteerror_list($page = 0)
{
    $skip = $page * 50;
    $sql = "SELECT errlog_key
            FROM errlog
            ORDER BY errlog_ts DESC
            LIMIT $skip, 50";
    $ret = array();
    $stmt = sqliteerror_query($sql);
    while (false !== ($key = $stmt->fetchColumn())) {
        $ret[] = $key;
    }
    return $ret;
}

/** @see error_get */
function sqliteerror_get($err_key)
{
    $sql = 'SELECT * FROM errlog WHERE errlog_key = ?';
    $error = sqliteerror_query($sql, $err_key)->fetch();
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
function sqliteerror_resolve($err_key)
{
    $sql = 'DELETE FROM errlog WHERE errlog_key = ?';
    sqliteerror_query($sql, $err_key);
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//


/**
 * Get a connection to the sqlite error log
 *
 * @private
 *
 * @return \PDO
 */
function sqliteerror_get_connection()
{
    static $connection;
    if (is_null($connection)) {
        $path = data_directory() . '/error-log.sq3';
        $create = !file_exists($path) || !filesize($path);
        $connection = new \PDO('sqlite:'.$path);
        $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        if ($create) {
            sqliteerror_create($connection);
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
function sqliteerror_query($sql, $params = null, $return_stmt = true)
{
    $pdo = sqliteerror_get_connection();
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
function sqliteerror_create(\PDO $pdo)
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

/**
 * Touch a record in the database, incrementing its counter and updating its
 * timestamp
 *
 * @param string $err_key the key to the record
 */
function sqliteerror_touch($err_key)
{
    $sql = 'UPDATE errlog
            SET errlog_ts = ?, errlog_cnt = errlog_cnt + 1
            WHERE errlog_key = ?';
    sqliteerror_query($sql, array(time(), $err_key));
}





