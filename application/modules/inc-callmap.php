<?php

/**
 * This file manages the call mapping functionality of the application.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function callmap_depends()
{
    return array_merge(
        driver_call('callmap', 'depends'),
        array('request')
    );
}

/** @see example_module_config_items() */
function callmap_config_items()
{
    return array(
        [
            'callmap_driver',
            '',
            'The callmap driver to use, either "sqlite", "fsstore" or "noop"'
        ], [
            'callmap_labels',
            array(),
            'Labels for the callmap, where the key is the regular expression to
            match and the value is the label to use',
            true,
        ], [
            'callmap_visualize_default',
            array(),
            'Nodes to visualize by default',
            true
        ], [
            'callmap_probability',
            1,
            'Probability that a given hit will log. Important for high traffic
             sites. Set higher for lower probability.'
        ]
    );
}

/** @see example_module_sysconfig() */
function callmap_sysconfig()
{
    return driver_call('callmap', 'sysconfig');
}

/** @see example_module_test() */
function callmap_test()
{
    return true;
}

//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//


/**
 * Log a call in the callmap log
 *
 * @param string $provider the provider (aka, the application answering the call)
 * @param string $consumer the consumer (aka, the application making the call),
 * will be extracted from Referer header if not set.
 * @param string $gateway the gateway in this application, usually the current
 * plugin or the current plugin + controller
 *
 * @return void
 */
function callmap_log($provider, $consumer = null, $gateway = null)
{
    $probability = config('callmap_probability', 0);
    if (rand(0, $probability)) {
        return;
    }
    $provider = callmap_normalize_label($provider);

    if (!$consumer) {
        $consumer = request_header('referer', request_query('referer', 'WWW'));
    }
    $consumer = callmap_normalize_label($consumer);

    $provider = callmap_alias_label($provider);
    $consumer = callmap_alias_label($consumer);

    if (!$gateway) {
        $gateway = plugin();
        if ($gateway == '_framework') {
            $gateway = 'vsac';
        }
    }
    $gateway = '[' . callmap_normalize_label($gateway) . ']';
    return callmap_log_hit($consumer, $provider, $gateway);
}

/**
 * Get a node id from its label
 *
 * @param string $label the label, already normalized
 *
 * @return integer the node id
 */
function callmap_get_node_id($label)
{
    return driver_call('callmap', 'get_node_id', [$label]);
}

/**
 * Clean out old entries in the callmap. "Old" is defined as more than a month.
 *
 * @return void
 */
function callmap_clean()
{
    return driver_call('callmap', 'clean');
}

/**
 * Dump the contents of the callmap log, for passing to the visualisation layer.
 * Returns an array with two elements:
 *
 *    - 'nodes' an array of all the nodes in the system, each node is an array
 *      with keys 'id', 'label', and 'last_touched'
 *    - 'edges' an array of edge relationships between nodes. each edge is an
 *      array with the keys 'id' (the relationship id), 'cid' (the consumer node
 *      id), 'gid' (the gateway node id) and 'pid' (the provider node id).
 *
 * @return array an array of relationships, each relationship has the format
 * array($consumer, $provider, $relationship_index)
 */
function callmap_dump()
{
    return driver_call('callmap', 'dump');
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//

/**
 * Do the actual logging, driver callback for callmap_log()
 *
 * @param string $provider see callmap_log()
 * @param string $consumer see callmap_log()
 * @param string $gateway see callmap_log()
 */
function callmap_log_hit($provider, $consumer, $gateway)
{
    return driver_call('callmap', 'log_hit', [$provider, $consumer, $gateway]);
}

/**
 * Check if the label has an alias in the callmap, and return the alias if so
 *
 * @param string $label the label to match
 *
 * @return string the alias, or the original label
 */
function callmap_alias_label($label)
{
    $aliases = config('callmap_labels', array());
    foreach ($aliases as $regex => $alias) {
        if (preg_match($regex, $label)) {
            return $alias;
        }
    }
    return $label;
}

/**
 * If one of the node labels is passed as a web address, extract the domain name.
 *
 * @param string $node_label the label for the node
 *
 * @return string the domain name, or the whole label if not set
 */
function callmap_normalize_label($node_label)
{
    $label = strpos($node_label, '//') === 0
           ? 'http:' . $node_label
           : $node_label;
    if (!filter_var($label, FILTER_VALIDATE_URL)) {
        return $node_label;
    }
    if ($label = parse_url($label, PHP_URL_HOST)) {
        return $label;
    }
    return $node_label;
}



