<?php

/**
 * This is the sqlite driver for the Cache Abstraction Layer (cal).
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

/** @see cal_depends() */
function cal_sqlite_depends()
{
    return array('sqlite', 'filesystem');
}

/** @see cal_sysconfig */
function cal_sqlite_sysconfig()
{
    return true;
}


//-- Utilities ---------------------------------------------------------------//

/** @see cal_size() */
function cal_sqlite_size()
{
    return sqlite_size(
        'cal.sq3',
        __NAMESPACE__ . '\\cal_sqlite_create',
        array(
            'items.id', 'items.identifier', 'items.expire', 'items.invalidate',
            'items.content', 'permutations.id', 'permutations.item_id',
            'permutations.identifier', 'permutations.content',
        )
    );
}


/** @see cal_clean() */
function cal_sqlite_clean($invalidate = 3600, $vacuum =  86400)
{
    return sqlite_clean(
        'cal.sq3',
        __NAMESPACE__ . '\\cal_sqlite_create',
        function () use ($clean) {
            $invalid_time = time() - $clean;
            $sql = 'DELETE FROM items WHERE invalidate < ? AND invalidate <> 0';
            cal_sqlite_exec($sql, time());
        },
        $clean,
        $vacuum
    );
}


//-- Metadata ----------------------------------------------------------------//

/** @see cal_get_meta() */
function cal_sqlite_get_meta($name)
{
    return sqlite_get_meta('cal.sq3', __NAMESPACE__ . '\\cal_sqlite_create', $name);
}

/** @see cal_set_meta() */
function cal_sqlite_set_meta($name, $value)
{
    return sqlite_set_meta(
        'cal.sq3',
        __NAMESPACE__ . '\\cal_sqlite_create',
        $name,
        $value
    );
}


//-- Top level items ---------------------------------------------------------//


/** @see cal_get_item_meta() */
function cal_sqlite_get_item_meta($identifier)
{
    $sql = "SELECT id, expire FROM items
            WHERE identifier = ? AND (invalidate > ? OR invalidate = ?)
            ORDER BY expire DESC
            LIMIT 0, 1";

    return cal_sqlite_query($sql, $identifier, time(), 0)->fetch();
}


/** @see cal_insert_item() */
function cal_sqlite_insert_item($identifier, $content)
{
    cal_sqlite_exec(
        'INSERT OR REPLACE INTO items (id, identifier, content)
        VALUES ((SELECT id FROM items WHERE identifier = ?), ?, ?)',
        $identifier,
        $identifier,
        serialize($content)
    );

    return cal_sqlite_query(
        'SELECT id FROM items WHERE identifier = ?',
        $identifier
    )->fetchColumn();

}

/** @see cal_touch() */
function cal_sqlite_touch($item_id, $expire)
{
    $sql = 'UPDATE items SET expire = ? WHERE id = ?';
    cal_sqlite_exec($sql, $expire, $item_id);
    return $item_id;
}


/** @see call_get_item_content() */
function cal_sqlite_get_item_content($item_id)
{
    $sql = 'SELECT content FROM items WHERE id = ?';
    $content = cal_sqlite_query($sql, $item_id)->fetchColumn();
    return $content ? unserialize($content) : null;
}


//-- Permutations ------------------------------------------------------------//


/** @see cal_get_permutation_content */
function cal_sqlite_get_permutation_content($item_id, $permutation_identifier) {
    $permutation = cal_sqlite_query(
        'SELECT content FROM permutations WHERE item_id = ? AND identifier = ?',
        $item_id,
        $permutation_identifier
    )->fetchColumn();
    return $permutation ? unserialize($permutation) : null;
}

/** @see cal_insert_permutation */
function cal_sqlite_insert_permutation(
    $item_id,
    $permutation_identifier,
    $permutation
) {
    cal_sqlite_exec(
        'INSERT OR REPLACE INTO permutations (id, item_id, identifier, content)
         VALUES ((SELECT id FROM permutations WHERE item_id = ? AND identifier = ?), ?, ?, ?)',
        $item_id,
        $permutation_identifier,
        $item_id,
        $permutation_identifier,
        serialize($permutation)
    );
}


//----------------------------------------------------------------------------//
//-- Driver-specific                                                        --//
//----------------------------------------------------------------------------//


//-- Database access ---------------------------------------------------------//

/**
 * A simple wrapper for sqlite_queryexec()
 */
function cal_sqlite_queryexec($args)
{
    array_unshift($args, __NAMESPACE__ . '\\cal_sqlite_create');
    array_unshift($args, 'cal.sq3');
    return sqlite_queryexec($args);
}

/**
 * Functionally identical to sqlite_exec()
 */
function cal_sqlite_exec($sql)
{
    return cal_sqlite_queryexec(func_get_args())[0];
}

/**
 * Functionally identical to sqlite_query()
 */
function cal_sqlite_query($sql)
{
    return cal_sqlite_queryexec(func_get_args())[1];
}

/**
 * Create the database schema
 *
 * @return void
 */
function cal_sqlite_create(\PDO $pdo)
{
    $pdo->exec('CREATE TABLE items (
                  id         INTEGER PRIMARY KEY, 
                  identifier TEXT,
                  expire     INTEGER DEFAULT 0,
                  invalidate INTEGER DEFAULT 0,
                  content    BLOB
                )');
    $pdo->exec('CREATE INDEX items_id_idx ON items (identifier)');
    $pdo->exec('CREATE UNIQUE INDEX items_identifier_idx ON items (identifier)');
    $pdo->exec('CREATE INDEX items_expire_idx ON items (expire)');
    $pdo->exec('CREATE INDEX items_invalidate_idx ON items (invalidate)');
    $pdo->exec('CREATE TABLE permutations (
                  id         INTEGER PRIMARY KEY, 
                  item_id    INTEGER REFERENCES items(id) ON DELETE CASCADE,
                  identifier TEXT,
                  content    BLOB
                )');
    $pdo->exec('CREATE INDEX permutations_id_idx ON permutations (identifier)');
    $pdo->exec('CREATE UNIQUE INDEX permutations_identifier_idx ON permutations (item_id, identifier)');

}






