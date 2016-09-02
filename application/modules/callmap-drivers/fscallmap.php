<?php

/**
 * This is the filesystem based callmap. It is faster than the sqlite callmap,
 * but it lacks some of the features.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//


/** @see callmap_sysconfig() */
function fscallmap_sysconfig()
{
    return true;
}



/** @see callmap_log_hit */
function fscallmap_log_hit($provider, $consumer, $gateway)
{
    $file = fscallmap_get_dir()
          . 'edge-'
          . md5($provider . '|' . $consumer . '|' . $gateway);
    if (file_exists($file)) {
        // since it's a high-traffic version, touching 0.1% of the time should be plenty
        if (rand(0, 1000) === 0) {
            touch($file);
        }
    } else {
        $data = compact('consumer', 'provider', 'gateway');
        file_put_contents($file, serialize($data));
    }
}

/** @see callmap_get_node_id */
function fscallmap_get_node_id($label)
{

    $file = fscallmap_get_dir()
          . 'node-'
          . md5($label);
    
    if (file_exists($file)) {
        touch($file);
    } else {
        file_put_contents($file, rand());
    }
    return (int) file_get_contents($file);
}

/** @see callmap_clean() */
function fscallmap_clean()
{
    $dir = fscallmap_get_dir();
    $edge_expire = time() - (60 * 60 * 24 * 30);
    $node_expire = time() - (60 * 30);
    foreach (scandir($dir) as $file) {
        $mtime = filemtime($dir . $file);
        if (
            (strpos($file, 'node-') === 0 && filemtime($dir . $file) < $node_expire)
            || 
            (strpos($file, 'edge-') === 0 && filemtime($dir . $file) < $edge_expire)
        ) {
            unlink($dir . $file);
        }
    }
}

/** @see callmap_dump() */
function fscallmap_dump()
{
    $edges = array();
    $nodes = array();
    $get_node = function ($label) use (&$nodes) {
        if (!isset($nodes[$label])) {
            $nodes[$label] = array(
                'label' => $label,
                'id' => fscallmap_get_node_id($label)
            );
        }
        return $nodes[$label]['id'];
    };
    $dir = fscallmap_get_dir();
    foreach (scandir($dir) as $file) {
        if (strpos($file, 'edge-') === 0) {
            $edge = unserialize(file_get_contents($dir . $file));
            $edges[] = array(
                'id' => 0,
                'cid' => $get_node($edge['consumer']),
                'pid' => $get_node($edge['provider']),
                'gid' => $get_node($edge['gateway']),
            );
        }
    }
    return array(
        'nodes' => array_values($nodes),
        'edges' => array_values($edges),
    );
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//


/**
 * Get a connection to the sqlite cache
 *
 * @private
 *
 * @return string the path to the directory
 */
function fscallmap_get_dir()
{
    static $dir;
    if (is_null($dir)) {
        $dir = data_directory() . '/callmap/';
        if (!is_dir($dir)) {
            mkdir($dir);
        }
    }
    return $dir;
}
















