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


/** @see callmap_sysconfig() */
function sqlitecallmap_sysconfig()
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



/** @see callmap_log_hit */
function sqlitecallmap_log_hit($provider, $consumer, $gateway)
{
    sqlitecallmap_log_edge(
        sqlitecallmap_get_node_id($consumer),
        sqlitecallmap_get_node_id($provider),
        sqlitecallmap_get_node_id($gateway)
    );
}

/** @see callmap_clean() */
function sqlitecallmap_clean()
{
    sqlitecallmap_query(
        'DELETE FROM nodes WHERE last_touch < ?',
        (time() - (60 * 60 * 24 * 30))
    );
}

/** @see callmap_dump() */
function sqlitecallmap_dump()
{
    $nodes = sqlitecallmap_query("SELECT * FROM nodes")->fetchAll();
    $edges = sqlitecallmap_query("SELECT * FROM edges")->fetchAll();
    return compact('nodes', 'edges');
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
function sqlitecallmap_get_connection()
{
    static $connection;
    if (is_null($connection)) {
        $path = data_directory() . '/callmap.sq3';
        $create = !file_exists($path) || !filesize($path);
        $connection = new \PDO('sqlite:'.$path);
        $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        if ($create) {
            sqlitecallmap_create($connection);
        }
        $connection->exec('PRAGMA foreign_keys = ON');
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
function sqlitecallmap_query($sql, $params = null, $return_stmt = true)
{
    $pdo = sqlitecallmap_get_connection();
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
function sqlitecallmap_create(\PDO $pdo)
{
    $pdo->exec('CREATE TABLE nodes (
                  id         INTEGER PRIMARY KEY, 
                  label      TEXT,
                  last_touch INTEGER DEFAULT 0
                )');
    $pdo->exec('CREATE UNIQUE INDEX nodes_label_idx ON nodes (label)');
    $pdo->exec('CREATE TABLE edges (
                  id  INTEGER PRIMARY KEY, 
                  pid INTEGER REFERENCES nodes(id) ON DELETE CASCADE,
                  cid INTEGER REFERENCES nodes(id) ON DELETE CASCADE,
                  gid INTEGER REFERENCES nodes(id) ON DELETE CASCADE
                )');
    $pdo->exec('CREATE UNIQUE INDEX edges_idx ON edges (pid, cid, gid)');


}

/**
 * Get a callmap node id
 */
function sqlitecallmap_get_node_id($label)
{
    $touch = function ($node_id) {
        $sql = "UPDATE nodes SET last_touch = ? WHERE id = ?";
        sqlitecallmap_query($sql, array(time(), $node_id));
    };
    $find = function () use ($label) {
        $sql = "SELECT id FROM nodes WHERE label = ?";
        return sqlitecallmap_query($sql, $label)->fetchColumn();
    };
    $insert = function () use ($label) {
        $sql = "INSERT OR REPLACE INTO nodes (id, label)
                VALUES ((SELECT id FROM nodes WHERE label = ?), ?)";
        sqlitecallmap_query($sql, array($label, $label));
    };

    if (!($id = $find())) {
        $insert();
        $id = $find();
    }
    $touch($id);
    return $id;
}

function sqlitecallmap_log_edge($cid, $pid, $gid)
{
    $find = function () use ($cid, $pid, $gid) {
        $sql = "SELECT id FROM edges
                WHERE cid = ? AND pid = ? AND gid = ?";
        $params = array($cid, $pid, $gid);
        return sqlitecallmap_query($sql, $params)->fetchColumn();
    };
    $insert = function () use ($cid, $pid, $gid) {
        $sql = "INSERT OR REPLACE
                INTO edges (id, cid, pid, gid)
                VALUES (
                    (SELECT id FROM edges WHERE cid = ? AND pid = ? AND gid= ?), ?, ?, ?
                )";
        $params = array($cid, $pid, $gid, $cid, $pid, $gid);
        sqlitecallmap_query($sql, $params);
    };
    if (!$find()) {
        $insert();
    }
}










