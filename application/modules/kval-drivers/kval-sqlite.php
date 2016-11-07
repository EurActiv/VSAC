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

/** @see kval_depends() */
function kval_sqlite_depends()
{
    return array('sqlite', 'filesystem');
}

/** @see kval_sysconfig() */
function kval_sqlite_sysconfig()
{
    return true;
}


//-- Utilities ---------------------------------------------------------------//

/** @see kval_size() */
function kval_sqlite_size()
{
    return sqlite_size(
        'kval.sq3',
        __NAMESPACE__ . '\\kval_sqlite_create',
        array('kv.id', 'kv.kv_key', 'kv.kv_ts', 'kv.kv_value')
    );
}

/** @see kval_clean() */
function kval_sqlite_clean($clean = 3600, $vacuum =  86400)
{
    return sqlite_clean(
        'kval.sq3',
        __NAMESPACE__ . '\\kval_sqlite_create',
        function () use ($clean) {
            $invalid_time = time() - $clean;
            kval_sqlite_exec('DELETE FROM kv WHERE kv_ts < ?', $invalid_time);
        },
        $clean,
        $vacuum
    );
}

/** @see kval_key() */
function kval_sqlite_key($key)
{
    $key = strtolower($key);
    if (strlen($key) > 100) { // to fit in a terminal window
        $suffix = md5($key);
        $prefix = substr($key, 0, (-1 * strlen($key)));
        $key = $suffix . $prefix;
    }
    return $key;
}


//-- Getting/Setting items from the store ------------------------------------//

/** @see kval_get_item() */
function kval_sqlite_get_item($key)
{
    $sql = 'SELECT * FROM kv WHERE kv_key = ?';
    $return = kval_sqlite_query($sql, $key)->fetch();
    if (!$return) {
        return null;
    }
    return array(
        'item' => unserialize($return['kv_value']),
        'ts'   => (int) $return['kv_ts'],
    );
}

/** @see kval_set_item() */
function kval_sqlite_set_item($key, $item)
{
    $sql = 'INSERT OR REPLACE INTO kv (id, kv_key, kv_ts, kv_value)
            VALUES ((SELECT id FROM kv WHERE kv_key = ?), ?, ?, ?)';

    kval_sqlite_exec($sql, $key, $key, $item['ts'], serialize($item['item']));
}


/** @see kval_delete */
function kval_sqlite_delete($key)
{
    $sql = 'DELETE FROM kv WHERE kv_key = ?)';
    kval_sqlite_exec($sql, $key);
}


//----------------------------------------------------------------------------//
//-- Driver-specific                                                        --//
//----------------------------------------------------------------------------//


//-- Database access ---------------------------------------------------------//

/**
 * A simple wrapper for sqlite_queryexec()
 */
function kval_sqlite_queryexec($args)
{
    array_unshift($args, __NAMESPACE__ . '\\kval_sqlite_create');
    array_unshift($args, 'kval.sq3');
    return sqlite_queryexec($args);
}

/**
 * Functionally identical to sqlite_exec()
 */
function kval_sqlite_exec($sql)
{
    return kval_sqlite_queryexec(func_get_args())[0];
}

/**
 * Functionally identical to sqlite_query()
 */
function kval_sqlite_query($sql)
{
    return kval_sqlite_queryexec(func_get_args())[1];
}

/**
 * Create the database schema
 *
 * @private
 *
 * @return void
 */
function kval_sqlite_create(\PDO $pdo)
{
    $pdo->exec('CREATE TABLE kv (
                  id       INTEGER PRIMARY KEY, 
                  kv_key   TEXT,
                  kv_ts    INTEGER DEFAULT 0,
                  kv_value BLOB
                )');
    $pdo->exec('CREATE UNIQUE INDEX kv_key_idx ON kv (kv_key)');
}




