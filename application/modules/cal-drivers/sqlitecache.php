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

/** @see cal_sysconfig */
function sqlitecache_sysconfig()
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


//-- Utilities ---------------------------------------------------------------//

/** @see cal_size() */
function sqlitecache_size()
{
    $items_size = sqlitecache_query(
        'SELECT SUM(LENGTH(HEX(content))/2) FROM items'
    )->fetchColumn();

    $permutations_size = sqlitecache_query(
        'SELECT SUM(LENGTH(HEX(content))/2) FROM permutations'
    )->fetchColumn();
    return ((float) $permutations_size) + ((float) $items_size);
}

/** @see cal_clean() */
function sqlitecache_clean($invalidate = 3600, $vacuum =  86400)
{
    $last_invalidate = (int) sqlitecache_get_meta('last_invalidate');
    $last_vacuum = (int) sqlitecache_get_meta('last_vacuum');
    $vacuumed = $invalidated = false;


    if ($last_invalidate < (time() - $invalidate)) {
        sqlitecache_set_meta('last_invalidate', time());
        sqlitecache_query("DELETE FROM items WHERE invalidate < ? AND invalidate <> 0", time());
        $invalidated = true;
    }

    if ($last_vacuum < (time() - $vacuum)) {
        sqlitecache_set_meta('last_vacuum', time());
        $disk_quota = config('cal_quota', 0.0);
        while ($disk_quota && sqlitecache_size() > $disk_quota) {
            sqlitecache_query("DELETE FROM items ORDER BY id ASC LIMIT 0, 1");
        }
        sqlitecache_query("VACUUM");
        $vacuumed = true;
    }
    return compact('last_invalidate', 'last_vacuum', 'invalidated', 'vacuumed');
}


//-- Metadata ----------------------------------------------------------------//

/** @see cal_get_meta() */
function sqlitecache_get_meta($name)
{
    $meta = sqlitecache_query(
        'SELECT meta_value FROM cache_meta WHERE meta_name = ?',
        $name
    )->fetchColumn();
    return $meta ? unserialize($meta) : null;
}

/** @see cal_set_meta() */
function sqlitecache_set_meta($name, $value)
{
    if (is_null($value)) {
        sqlitecache_query('DELETE FROM cache_meta WHERE meta_name = ?', $name);
    } else {
        sqlitecache_query(
            "INSERT OR REPLACE INTO cache_meta (id, meta_name, meta_value)
             VALUES ((SELECT id FROM cache_meta WHERE meta_name = ?), ?, ?)",
            array($name, $name, serialize($value))
        );
    }
}

//-- Actual caching ----------------------------------------------------------//

/** @see cal_get_item() */
function sqlitecache_get_item($identifier, callable $refresh)
{
    $item_id = sqlitecache_get_item_id($identifier, $refresh);
    return sqlitecache_get_item_content($item_id);
}

/** @see cal_get_permutation */
function sqlitecache_get_permutation(
    $item_identifier,
    callable $refresh_item,
    $permutation_identifier,
    callable $refresh_permutation
) {
    $iid = sqlitecache_get_item_id($item_identifier, $refresh_item);
    $pid = sqlitecache_get_permutation_id($iid, $permutation_identifier, $refresh_permutation);
    return sqlitecache_get_permutation_content($pid);
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//

//-- Utilities ---------------------------------------------------------------//

/**
 * Get a connection to the sqlite cache
 *
 * @private
 *
 * @return \PDO
 */
function sqlitecache_get_connection()
{
    static $connection;
    if (is_null($connection)) {
        $path = filesystem_files_path() . 'cache.sq3';
        $create = !file_exists($path) || !filesize($path);
        $connection = new \PDO('sqlite:'.$path);
        $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        if ($create) {
            sqlitecache_create_cache($connection);
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
function sqlitecache_query($sql, $params = null, $return_stmt = true)
{
    $pdo = sqlitecache_get_connection();
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
function sqlitecache_create_cache(\PDO $pdo)
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
    $pdo->exec('CREATE TABLE cache_meta (
                  id         INTEGER PRIMARY KEY, 
                  meta_name  TEXT,
                  meta_value TEXT
                )');
    $pdo->exec('CREATE UNIQUE INDEX cache_meta_meta_name_idx ON cache_meta (meta_name)');

}



//-- Top level items ---------------------------------------------------------//

/**
 * Similar to cal_get_item(), but returns the sqlite row id for the item
 *
 * @private
 *
 * @return the sqlite row id for the item
 */
function sqlitecache_get_item_id($identifier, callable $refresh)
{
    
    $item = sqlitecache_query(
        "SELECT id, expire
         FROM items
         WHERE identifier = ? AND (invalidate > ? OR invalidate = ?)
         ORDER BY expire DESC
         LIMIT 0, 1",
        array($identifier, time(), 0)
    )->fetch();

    if ($item && (!$item['expire'] || $item['expire'] >= time())) {
        return $item['id'];
    }
    $content = call_user_func($refresh, $identifier);

    if ($content === null) { // error handling
        // if there's an old item just touch it on error
        if ($item) {
            sqlitecache_touch_item($item['id']);
            return $item['id'];
        }
        // cache error state for 5 minutes to let the source cool off
        return sqlitecache_insert_item($identifier, false, 60 * 5);
    }

    // if there's an old item but it hasn't changed, avoid inserting new
    // items because that will cause all the permutations to recalculate
    if ($item) {
        $old_content = sqlitecache_get_item_content($item['id']);
        if ($old_content === $content) {
            sqlitecache_touch_item($item['id']);
            return $item['id'];
        }
    }

    // At this point, we can just insert the item
    return sqlitecache_insert_item($identifier, $content);
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
function sqlitecache_insert_item($identifier, $content, $expire = null)
{
    sqlitecache_query(
        "INSERT OR REPLACE INTO items (id, identifier, content)
         VALUES ((SELECT id FROM items WHERE identifier = ?), ?, ?)",
        array($identifier, $identifier, serialize($content))
    );
    $item_id = sqlitecache_query(
        'SELECT id FROM items WHERE identifier = ?',
        $identifier
    )->fetchColumn();

    sqlitecache_touch_item($item_id, $expire);
    return $item_id;
}

/**
 * "Touch" an item, that is make it as if it is fresh now
 *
 * @private
 *
 * @param integer $item_id the sqlite row id
 * @param integer $expire expires in this many seconds, defaults to global config
 * @return void
 */
function sqlitecache_touch_item($item_id, $expire = null)
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

    sqlitecache_query(
        "UPDATE items SET expire = ?, invalidate = ? WHERE id = ?",
        array($expire, $invalidate, $item_id)
    );
}

/**
 * Read the cached item from the database into memory
 *
 * @private
 *
 * @param integer $item_id the sqlite row id
 * @return mixed
 */
function sqlitecache_get_item_content($item_id)
{
    $content = sqlitecache_query(
        "SELECT content FROM items WHERE id = ?",
        $item_id
    )->fetchColumn();
    return $content ? unserialize($content) : null;
}


//-- Permutations ------------------------------------------------------------//

/**
 * Get the sqlite row id of a permutation
 *
 * @private
 *
 * @param integer $item_id the sqlite row id of the item
 * @param string $identifier the permutation identifier
 * @param callable $create the creation callback
 * @return integer the sqlite row id
 */
function sqlitecache_get_permutation_id($item_id, $identifier, callable $create)
{
    $permutation_id = sqlitecache_query(
        "SELECT id
         FROM permutations
         WHERE item_id = ? AND identifier = ?
         ORDER BY id DESC
         LIMIT 0, 1",
        array($item_id, $identifier)
    )->fetchColumn();
    if ($permutation_id) {
        return $permutation_id;
    }

    $content = sqlitecache_get_item_content($item_id);
    $permutation = $content === null
                 ? null
                 : call_user_func($create, $content, $identifier)
                 ;
    return sqlitecache_insert_permutation($item_id, $identifier, $permutation);
}

/**
 * Insert a generated permutation into the database
 *
 * @private
 *
 * @param integer $item_id the sqlite row id for the item
 * @param string $identifier the permutation identifier
 * @param mixed $permutation the permutation content
 * @return integer the sqlite row id for the permutation
 */
function sqlitecache_insert_permutation($item_id, $identifier, $permutation)
{
    sqlitecache_query(
        "INSERT OR REPLACE INTO permutations (id, item_id, identifier, content)
         VALUES ((SELECT id FROM permutations WHERE item_id = ? AND identifier = ?), ?, ?, ?)",
        array($item_id, $identifier, $item_id, $identifier, serialize($permutation))
    );
    return sqlitecache_query(
        'SELECT id FROM permutations WHERE item_id = ? AND identifier = ?',
        array($item_id, $identifier)
    )->fetchColumn();
}

/**
 * Get the unserialized content of a permutation
 *
 * @private
 *
 * @param integer $permutation_id the sqlite row id of the permutation
 * @return mixed
 */
function sqlitecache_get_permutation_content($permutation_id)
{
    $content = sqlitecache_query(
        "SELECT content FROM permutations WHERE id = ?",
        $permutation_id
    )->fetchColumn();
    return $content ? unserialize($content) : null;
}



