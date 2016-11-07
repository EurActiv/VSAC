<?php

/**
 * This is the sqlite driver for the callmap.
 *
 * This driver is not appropriate for high-traffic applications, it mostly
 * exists for testing purposes.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//


//-- Framework ---------------------------------------------------------------//

/** @see callmap_depends() */
function callmap_sqlite_depends()
{
    return array('sqlite');
}


/** @see callmap_sysconfig() */
function callmap_sqlite_sysconfig()
{
    return true;
}

//-- Logging and reading -----------------------------------------------------//

/** @see callmap_log_hit */
function callmap_sqlite_log_hit($provider, $consumer, $gateway)
{
    callmap_sqlite_log_edge(
        callmap_sqlite_get_node_id($consumer),
        callmap_sqlite_get_node_id($provider),
        callmap_sqlite_get_node_id($gateway)
    );
}

/** @see callmap_get_node_id */
function callmap_sqlite_get_node_id($label)
{
    if (!($id = callmap_sqlite_find_node($label))) {
        $id = callmap_sqlite_insert_node($label);
    }
    callmap_sqlite_touch_node($id);
    return (int) $id;
}

/** @see callmap_clean() */
function callmap_sqlite_clean()
{
    $sql = 'DELETE FROM nodes WHERE last_touch < ?';
    $time = time() - (60 * 60 * 24 * 30);
    callmap_sqlite_exec($sql, $time);
}

/** @see callmap_dump() */
function callmap_sqlite_dump()
{
    $nodes = callmap_sqlite_query("SELECT * FROM nodes")->fetchAll();
    $edges = callmap_sqlite_query("SELECT * FROM edges")->fetchAll();
    return compact('nodes', 'edges');
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//

//-- Database access ---------------------------------------------------------//

/**
 * A simple wrapper for sqlite_queryexec()
 */
function callmap_sqlite_queryexec($args)
{
    array_unshift($args, __NAMESPACE__ . '\\callmap_sqlite_create');
    array_unshift($args, '../callmap.sq3');
    return sqlite_queryexec($args);
}

/**
 * Functionally identical to sqlite_exec()
 */
function callmap_sqlite_exec($sql)
{
    return callmap_sqlite_queryexec(func_get_args())[0];
}

/**
 * Functionally identical to sqlite_query()
 */
function callmap_sqlite_query($sql)
{
    return callmap_sqlite_queryexec(func_get_args())[1];
}


/**
 * Create the database schema
 *
 * @private
 *
 * @return void
 */
function callmap_sqlite_create(\PDO $pdo)
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

//-- Database helpers --------------------------------------------------------//

/**
 * Get the id for an edge
 */
function callmap_sqlite_edge_id($cid, $pid, $gid)
{
    $sql = 'SELECT id FROM edges
            WHERE cid = ? AND pid = ? AND gid = ?';
    return callmap_sqlite_query($sql, $cid, $pid, $gid)->fetchColumn();
}

/**
 * Insert an edge
 */
function callmap_sqlite_insert_edge($cid, $pid, $gid)
{
    $sql = 'INSERT OR REPLACE
            INTO edges (id, cid, pid, gid)
            VALUES ((SELECT id FROM edges WHERE cid=? AND pid=? AND gid=?),?,?,?)';
    callmap_sqlite_exec($sql, $cid, $pid, $gid, $cid, $pid, $gid);
}

/**
 * Log an edge
 */
function callmap_sqlite_log_edge($cid, $pid, $gid)
{
    if (!callmap_sqlite_edge_id($cid, $pid, $gid)) {
        callmap_sqlite_insert_edge($cid, $pid, $gid);
    }
}

/**
 * Touch a node
 */
function callmap_sqlite_touch_node($node_id)
{
    $sql = "UPDATE nodes SET last_touch = ? WHERE id = ?";
    callmap_sqlite_exec($sql, time(), $node_id);
}

/**
 * Find a node by label
 */
function callmap_sqlite_find_node($label)
{
    $sql = "SELECT id FROM nodes WHERE label = ?";
    return callmap_sqlite_query($sql, $label)->fetchColumn();
}

/**
 * Insert a node
 */
function callmap_sqlite_insert_node($label)
{
    $sql = "INSERT OR REPLACE INTO nodes (id, label)
            VALUES ((SELECT id FROM nodes WHERE label = ?), ?)";
    callmap_sqlite_exec($sql, $label, $label);
    return callmap_sqlite_find_node($label);
}








