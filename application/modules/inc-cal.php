<?php

/**
 * Caching in this application relies on the concept of cached "items" and
 * cached "permutations".  An "item" is something that is slow or unreliable
 * to generate. A permutation is a transformation that must happen on an item
 * before it is actually used, but that is computationally expensive. Generally,
 * there should be more than one permutation per item. When an item expires,
 * its permutations are deleted, but a permutation is not deleted until the
 * underlying item is deleted.
 *
 * For example, the raw content of an image fetched over HTTP would be an "Item",
 * while the scaled thumbnails of that image would be "Permutations".
 *
 * This file contains the "Cache Abstraction Layer" (cal). It requires a driver
 * that is appropriate for the caching situation.
 *
 * Cache drivers are stored in __DIR__ . '/cal-drivers/'.
 *
 * Note: when writing a driver, ensure that it conforms to the API. The API is
 * defined as the functions checked for in cal_check_driver and the functions
 * should be implented such that they are equivalent to the corresponding
 * cal_* function.
 *
 * For example, a driver called "mydriver" would be located in
 * __DIR__.'cal-drivers/mydriver.php' and define the function mydriver_size that
 * returns the size of the items and permutations as a float.
 *
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function cal_depends()
{
    return driver_call('cal', 'depends');
}

/** @see example_module_config_items() */
function cal_config_items()
{
    return array(
        [
            'cal_ttl',
            0,
            'The time, in seconds, to cache items before revalidating'
        ], [
            'cal_driver',
            '',
            'The cache driver to use, either "fs" or "sqlite" or "noop"'
        ],
    );
}

/** @see example_module_sysconfig() */
function cal_sysconfig()
{
    return driver_call('cal', 'sysconfig');
}

/** @see example_module_test() */
function cal_test()
{
    force_conf('cal_ttl', 2);
    $item_key = 'test_' . time();
    $item_value = md5($item_key);
    $permutation_value1 = md5($item_value);
    $permutation_value2 = md5(md5($item_value));

    $permutation1 = cal_get_permutation(
        $item_key,
        function () use ($item_value) { return $item_value; },
        'permutation_1',
        function ($item) { return md5($item); }
    );
    if ($permutation1 != $permutation_value1) {
        return 'Permuation transformation failed';
    }
    $permutation2 = cal_get_permutation(
        $item_key,
        function () use ($item_value) { return $item_value; },
        'permutation_2',
        function ($item) { return md5(md5($item)); }
    );
    if ($permutation2 != $permutation_value2) {
        return 'Permuation transformation (2) failed';
    }

    sleep(3);
    $refresh_called = false;
    $value = cal_get_item($item_key, function () use(&$refresh_called) {
        $refresh_called = true;
        return null;
    });
    if (!$refresh_called) {
        return 'Refresh was not called on expired item';
    }
    if ($value != $item_value) {
        return 'Item could not be resurrected';
    }

    return true;
}

//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//


//-- Utilities ---------------------------------------------------------------//

/**
 * Get the size of the cache database. Drivers may have limitations that push
 * the number around, but it should at least provide the sum of the cached
 * content itself (items and permutations).
 *
 * @return float the size, in bytes
 */
function cal_size()
{
    return driver_call('cal', 'size');
}

/**
 * Clean the database
 *
 * @return array timestamps of last clean and last invalidate
 */
function cal_clean($invalidate = 3600, $vacuum =  86400)
{
    return driver_call('cal', 'clean', [$invalidate, $vacuum]);
}

/**
 * Print a status message about the cache: size, last maintenence, maintenence
 * configuration. For use in the backend.
 *
 * @param string $clean_url the URL to configure with cron to clear the cache
 * @param string $hl_tag the tag name for the title element (eg, 'h3')
 *
 * @return void
 */
function cal_status($clean_url)
{

    backend_collapsible('Cache', function () use ($clean_url) {

        $size = backend_format_size(cal_size());
        $last_invalidate = cal_get_meta('last_invalidate');
        $last_vacuum = cal_get_meta('last_vacuum');
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
            Driver: <?= driver('cal') ?> |
            <a target="_blank" href="<?= $clean_url ?>">Clean cache now</a>.
        </p>
        <?php if (!$is_cleaned) { ?>
            <p><strong>Warning!</strong> It looks like the database isn't being
            cleaned regularly. Are you sure cron is configured?</p>
        <?php } ?>
        <?php
    });
}



//-- Cache metadata: metadata relates to the cache database itself, not ------//
//-- cached items.  Internally it's used for storing info about when ---------//
//-- maintenence happened, but the API is there if you have another use. -----//
//-- It's a simple key:value store. ------------------------------------------//

/**
 * Get a cache meta value
 *
 * @param string $name the meta value name
 * @return mixed the meta value
 */
function cal_get_meta($name)
{
    return driver_call('cal', 'get_meta', [$name]);
}

/**
 * Set a cache meta value
 *
 * @param string $name the meta value name
 * @param mixed $value anything that can be serialized and unserialized
 * @return mixed the meta value
 */
function cal_set_meta($name, $value)
{
    return driver_call('cal', 'set_meta', [$name, $value]);
}



//-- The cache functions -----------------------------------------------------//

/**
 * Get a cached item, refresh it if it doesn't exist or if it's expired. If
 * there is an expired item in the cache and the refresh function fails, the
 * expired item will be 'touched'
 *
 * @param string $identifier a unique but permanent string to identify the
 * resource, such as the URL to an RSS feed.
 * @param callable $refresh the function to call to refresh the item. It should
 * return NULL on failure, otherwise anything serializable
 * @return mixed the item content
 */
function cal_get_item($identifier, callable $refresh)
{
    $item_id = cal_get_item_id($identifier, $refresh);
    return cal_get_item_content($item_id);
}

/**
 * Get an item permutation from the database. Generate either (or both) the item
 * or the permuation if it does not exist.
 *
 * @param string $item_identifier see cal_item_is_cached
 * @param callable $refresh_item see call_get_item
 * @param string $permutation_identifier see cal_permutation_is_cached
 * @param callable $refresh_permutation the callback that transforms the item,
 * will receive the item as its argument.
 * @return mixed the permutation
 */
function cal_get_permutation(
    $item_identifier,
    callable $refresh_item,
    $permutation_identifier,
    callable $refresh_permutation
) {
    if (!($item_id = cal_get_item_id($item_identifier, $refresh_item))) {
        return null;
    }
    $permutation = cal_get_permutation_content($item_id, $permutation_identifier);
    if (!is_null($permutation)) {
        return $permutation;
    }
    $item = cal_get_item_content($item_id);
    if (is_null($item)) {
        return null;
    }
    $permutation = call_user_func($refresh_permutation, $item);
    if (is_null($permutation)) {
        return null;
    }
    cal_insert_permutation($item_id, $permutation_identifier, $permutation);
    return $permutation;
}


//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//

/**
 * Similar to cal_get_item(), but returns the record unique identifier for the
 * underlying cache layer instead of the entire row
 *
 * @private
 *
 * @param string $identifier @see cal_get_item()
 * @param callable $refresh @see cal_get_item()
 *
 * @return the underlying cache layer's unique id for the item
 */
function cal_get_item_id($identifier, callable $refresh)
{
    $meta = cal_get_item_meta($identifier);

    if ($meta && (!$meta['expire'] || $meta['expire'] >= time())) {
        return $meta['id'];
    }
    $content = call_user_func($refresh, $identifier);

    if ($content === null) { // error handling
        // if there's an old item just touch it on error
        if ($meta) {
            return cal_touch_item($meta['id']);
        }
        // cache error state for 5 minutes to let the source cool off
        $item_id = cal_insert_item($identifier, null);
        return cal_touch_item($item_id, 60 * 5);
    }

    // if there's an old item but it hasn't changed, avoid inserting new
    // items because that will cause all the permutations to recalculate
    if ($meta) {
        $old_content = cal_get_item_content($meta['id']);
        if ($old_content === $content) {
            return cal_touch_item($meta['id']);
        }
    }

    // At this point, we can just insert the item
    $item_id = cal_insert_item($identifier, $content);
    return cal_touch_item($item_id);
}


/**
 * "Touch" an item, that is make it as if it is fresh now
 *
 * @private
 *
 * @param integer $item_id the cache layer's unique id for the item
 * @param integer $expire expires in this many seconds, defaults to global config
 *
 * @return void
 */
function cal_touch_item($item_id, $expire = null)
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
    cal_touch($item_id, $expire);
    return $item_id;
}

/**
 * Get the meta for a an item in the cache layer, if it exists
 *
 * @param string $identifier the identifier for the item
 *
 * @return array['id' => unique id, 'expires' => timestamp]
 */
function cal_get_item_meta($identifier)
{
    return driver_call('cal', 'get_item_meta', [$identifier]);
}


/**
 * Insert an item in the database
 *
 * @private
 *
 * @param string $identifier @see cal_get_item()
 * @param mixed $content anything that can be serialized
 * @return mixed driver specific item id
 */
function cal_insert_item($identifier, $content, $expire = null)
{
    return driver_call('cal', 'insert_item', [$identifier, $content, $expire]);
}

/**
 * Execute the actual item touch from cal_touch_item()
 *
 * @private
 *
 * @param string $item_id @see cal_touch_item()
 * @param integer $expire expire the item in seconds
 * @return void
 */
function cal_touch($item_id, $expire)
{
    return driver_call('cal', 'touch', [$item_id, $expire]);
}


/**
 * Get the unserialized content of an item
 *
 * @private
 *
 * @param string $item_id the data layer's unique id for the item
 * @return mixed
 */
function cal_get_item_content($item_id)
{
    return driver_call('cal', 'get_item_content', [$item_id]);
}


//-- Permutations ------------------------------------------------------------//


/**
 * Get the unserialized content of a permutation
 *
 * @private
 *
 * @param string $item_id data layer's unique id for the item
 * @param string $permutation_identifier the caller identifier for the permutation
 * @return mixed
 */
function cal_get_permutation_content($item_id, $permutation_identifier)
{
    return driver_call(
        'cal',
        'get_permutation_content',
        [$item_id, $permutation_identifier]
    );
}


/**
 * Insert permutation content in the database
 *
 * @private
 *
 * @param string $item_id data layer's unique id for the item
 * @param string $permutation_identifier the caller identifier for the permutation
 * @return void
 */
function cal_insert_permutation($item_id, $permutation_identifier, $permutation)
{
    return driver_call(
        'cal',
        'insert_permutation',
        [$item_id, $permutation_identifier, $permutation]
    );
}



