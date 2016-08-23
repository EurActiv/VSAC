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

/** @see example_module_config_items() */
function kval_config_items()
{
    return array(
        [
            'kval_ttl',
            0,
            'The time, in seconds, to cache key:value pairs before revalidating'
        ], [
            'kval_quota',
            0.0,
            'The size, in bytes, to allow the key:value store to grow to'
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
    force_conf('kval_quota', 1024 * 1024);
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
 * Clean the database and enforce disc quota
 *
 * @return array timestamps of last clean and last invalidate
 */
function kval_clean($invalidate = 3600, $vacuum =  86400)
{
    return driver_call('kval', 'clean', [$invalidate, $vacuum]);
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
        $quota = backend_format_size(config('kval_quota', 0.0));
        $last_invalidate = kval_get('__last_invalidate', 0);
        $last_vacuum = kval_get('__last_vacuum', 0);
        $interval = time() - (60 * 60 * 24);
        $is_cleaned = ($last_invalidate > $interval) && ($last_vacuum > $interval);
        $clean_url = router_plugin_url($clean_url, true);


        ?>
        <p>The database that powers this API is currently using
            <code><?= $size ?></code>. The configured disk quota is
            <code><?= $quota ?></code>.<p>
        <p>Expired items were last cleaned from the cache
            <?= backend_format_time_ago($last_invalidate) ?>. The dabase was last vacuumed
            <?= backend_format_time_ago($last_vacuum) ?>.</p>
        <p>To ensure that the database is regularly cleaned to respect quotas,
            make sure the following line is in your cron tab (adjusting the
            frequency to your needs):</p>
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
 * Get a value by key
 *
 * @param string $key @see kval_key()
 * @param int max age the maximimum allowable age, in seconds, of the value.
 * Set to a zero for no expiration, null for the configured default.
 * @return mixed the meta value, or null if it does not exist
 */
function kval_get($key, $expires = null)
{
    return driver_call('kval', 'get', [$key, $expires]);
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
    return driver_call('kval', 'set', [$key, $value]);
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
    return driver_call('kval', 'value', [$key, $expires, $generate]);
}



