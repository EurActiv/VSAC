<?php

/**
 * A simple Key:Value store for situations where the full caching system (cal_*)
 * is not appropriate. This is the Key-Value Abstraction Layer (kval_*). It
 * requires an appropriate storage driver.
 *
 * Key:Value drivers are stored in __DIR__ . '/kval-drivers/'.
 *
 * Note: when writing a driver, ensure that it conforms to the API. The API is
 * defined as the functions checked for in kval_check_driver and the functions
 * should be implented such that they are equivalent to the corresponding
 * kval_* function.
 *
 * For example, a driver called "mydriver" would be located in
 * __DIR__.'kval-drivers/mydriver.php' and define the function mydriver_size that
 * returns the size of the key:value store as a float.
 *
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function kval_depends()
{
    return array_merge(
        driver_call('kval', 'depends'),
        array()
    );
}

/** @see example_module_config_items() */
function kval_config_items()
{
    return array(
        [
            'kval_ttl',
            0,
            'The time, in seconds, to cache key:value pairs before revalidating'
        ], [
            'kval_driver',
            '',
            'The key:value driver to use, either "fskv" or "sqlitekv"'
        ],
    );
}

/** @see example_module_sysconfig() */
function kval_sysconfig()
{
    return driver_call('kval', 'sysconfig');
}

/** @see example_module_test() */
function kval_test()
{
    force_conf('kval_ttl', 2);
    $key = 'test_' . time();
    $value = md5($key);
    $value2 = md5($key);

    if (!is_null(kval_get($key))) {
        return 'fetching a non-existing key returned a value';
    }
    kval_set($key, $value);
    if (kval_get($key) !== $value) {
        return 'wrong value returned';
    }
    sleep(3);
    if (!is_null(kval_get($key))) {
        return 'item did not expire';
    }
    if (kval_get($key, 0) !== $value) {
        return 'item could not be resurrected';
    }
    return true;
}

//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//


//-- Utilities ---------------------------------------------------------------//

/**
 * Get the size of the cache database. Drivers may have limitations that push
 * the number around, but it should at least provide the sum of the keys and
 * values.
 *
 * @return float the size, in bytes
 */
function kval_size()
{
    return driver_call('kval', 'size');
}

/**
 * Clean the database
 *
 * @return array timestamps of last clean and last invalidate
 */
function kval_clean($clean = 3600, $vacuum =  86400)
{
    return driver_call('kval', 'clean', [$clean, $vacuum]);
}

/**
 * Print a status message about the cache: size, last maintenence, maintenence
 * configuration. For use in teh backend.
 *
 * @param string $clean_url the URL to configure with cron to clear the cache
 * @param string $hl_tag the tag name for the title element (eg, 'h3')
 *
 * @return void
 */
function kval_status($clean_url)
{
    backend_collapsible('Key-Value Store', function () use ($clean_url) {
        $size = backend_format_size(kval_size());
        $last_invalidate = kval_get('__last_invalidate', 0);
        $last_vacuum = kval_get('__last_vacuum', 0);
        $interval = time() - (60 * 60 * 24);
        $is_cleaned = ($last_invalidate > $interval) && ($last_vacuum > $interval);
        $clean_url = router_plugin_url($clean_url, true);


        ?>
        <p>The database that powers this API is currently using
            <code><?= $size ?></code>.<p>
        <p>Expired items were last cleaned from the cache
            <?= backend_format_time_ago($last_invalidate) ?>. The database was
            last vacuumed <?= backend_format_time_ago($last_vacuum) ?>.</p>
        <p>To ensure that the database is regularly cleaned, make sure the
            following line is in your cron tab (adjusting the frequency to your
            needs):</p>
        <pre><code>/15 * * * wget -q -O - <?= $clean_url ?></code></pre>
        <p class="text-right">
            Driver: <?= driver('kval') ?> |
            <a target="_blank" href="<?= $clean_url ?>">Clean cache now</a>.
        </p>
        <?php if (!$is_cleaned) { ?>
            <p><strong>Warning!</strong> It looks like the database isn't being
            cleaned regularly. Are you sure cron is configured?</p>
        <?php }
    });
}


//-- The key:value functions -------------------------------------------------//

/**
 * Keys may be any string, but the underlying driver may need to transform them
 * to conform to the storage engine. For example, mysql would need to truncate
 * after 191 characters to be able to use a BTREE index on the key column. This
 * transformation introduces the obvious risk of key collisions.
 *
 * This function is exposed as part of the abstraction layer to allow developers
 * to debug key collisions.
 *
 * In general, your key will not be touched if it is less than 128 characters
 * with only alpha-numeric characters. In most cases, a key that is too long
 * will be hashed while non-alpha-numeric characters will beconverted to
 * underscores.
 *
 * @param string $key the key, as input by the caller
 * @return string the key as it will be stored by the engine
 */
function kval_key($key)
{
    return driver_call('kval', 'key', [$key]);
}

/**
 * Turn a passed expires value into a timestamp that can be compared to
 * the timestamp in an item
 * 
 * @param int $expires the max age the maximimum allowable age, in
 * seconds, of the value. Set to a zero for no expiration, null for the
 * configured default.
 */
function kval_expires($expires)
{
    if (is_null($expires)) {
        $expires = config('kval_ttl', 0);
    }
    $expires_ts = ($expires && $expires > 0) ? time() - $expires : 0;
    return $expires_ts;
}

/**
 * Get a value by key
 *
 * @param string $key @see kval_key()
 * @param int max age the maximimum allowable age, in seconds, of the value.
 * Set to a zero for no expiration, null for the configured default.
 * @return mixed the meta value, or null if it does not exist
 */
function kval_get($key, $expires = null)
{
    $_key = kval_key($key);
    $_expires = kval_expires($expires);
    $item = kval_get_item($_key);
    if ($item && $item['ts'] > $_expires) {
        return $item['item'];
    }
    return null;
}

/**
 * Set a value
 *
 * @param string $key @see kval_key()
 * @param mixed $value anything that can be serialized and unserialized. An
 * explicit NULL will unset the value.
 * @return void
 */
function kval_set($key, $value)
{
    $_key = kval_key($key);
    $item = array(
        'item' => $value,
        'ts'   => time(),
    );
    return kval_set_item($_key, $item);
}

/**
 * Get a value, generating it if it does not exist.
 *
 * @param string $key @see kval_key
 * @param integer $expires @see kval_get()
 * @param callable $create the callback to generate the key. Note: unlike in
 * kval_set, if this function returns NULL, an existing value will be "touched"
 * instead of deleted.
 * @return mixed the meta value, or null if it does not exist and could not be
 * created.
 */
function kval_value($key, $expires, callable $generate)
{
    $_key = kval_key($key);
    $_expires = kval_expires($expires);

    $existing = kval_get_item($_key);
    if ($existing && $existing['ts'] > $_expires) {
        return $existing['item'];
    }
    $value = call_user_func($create);
    if (is_null($value)) {
        if ($existing) {
            $existing['ts'] = time();
            kval_set_item($_key, $existing);
            return $existing['item'];
        }
        return null;
    }
    kval_set_item($_key, array('item' => $value, 'ts' => time()));
    return $value;
}


//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//

/**
 * Get the complete option, with timestamp
 *
 * @param string $key
 * @param int $expires_ts
 *
 * @return null|array('item' => mixed, 'ts' => (integer))
 */
function kval_get_item($key)
{
    return driver_call('kval', 'get_item', [$key]);
}

/**
 * Set the complete option, with timestamp
 *
 * @param string $key
 * @param array $item['item', 'ts']
 *
 * @return null
 */
function kval_set_item($key, $item)
{
    return driver_call('kval', 'set_item', [$key, $item]);
}

/**
 * Delete a value if it exists
 *
 * @param string $key
 */
function kval_delete($key)
{
    return driver_call('kval', 'delete', [$key]);
}


