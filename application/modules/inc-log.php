<?php

/**
 * This module handles writing to log files
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function log_depends()
{
    return array('filesystem');
}

/** @see example_module_config_items() */
function log_config_items()
{
    return array(
        [
            'log_per_file',
            0,
            'The number of entries to keep in a log file before rotating it',
        ], [
            'log_keep_files',
            0,
            'The number of log files to keep before deleting',
        ]
    );
}

/** @see example_module_sysconfig() */
function log_sysconfig()
{
    if (strpos(strtolower(PHP_OS), 'win') === 0) {
        return 'A *nix server is required';
    }
    if (!exec('which wget')) {
        return 'wget not installed';
    }
    return true;
}

/** @see example_module_test() */
function log_test()
{
    force_conf('log_per_file', 5);
    force_conf('log_keep_files', 10);
    $current = log_get_file();
    $log_dir = dirname($current);
    if (!is_dir($log_dir)) {
        return 'failed to create log dir';
    }
    filesystem_files_rmdir($log_dir, true);
    filesystem_mkdir($log_dir);
    log_log('%s %s', 'test',  'message');
    if (!file_exists($current)) {
        return 'log file not created';
    }
    $content = file_get_contents($current);
    $expected = '/^'
              . '[0-9a-f]+ '       // the message id
              . '[ \d]{3}\.\d{2} ' // the timer
              . '\| test message\n'  // the message itself
              . '$/'
              ;
    if (!preg_match($expected, $content)) {
        return 'log file message not set';
    }
    unlink($current);

    log_log(str_repeat('abcdef ghijkl ', 7));
    $content = file_get_contents($current);
    $expected = '/^'
              . '[0-9a-f]+ '       // the message id
              . '[ \d]{3}\.\d{2} ' // the timer
              . '\| [a-z ]+\n'     // the message line 1
              . ' +\| [a-z ]+\n'   // the message line 2
              . '$/'
              ;
    if (!preg_match($expected, $content)) {
        return 'log file message not line broken properly';
    }
    unlink($current);

    for ($i = 0; $i < 100; $i += 1) {
        log_rotate_files(true);
        log_log(md5(uniqid(true)));
    }
    if (count(filesystem_ls($log_dir)) != 11) {
        return 'log file rotating does not work';
    }
    filesystem_files_rmdir($log_dir, true);

    return true;
}


//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//


function log_log($message)
{
    log_rotate_files();
    $prefix = log_id() . ' ' . log_timer();
    $message = log_format_message($prefix, func_get_args());
    $file = log_get_file();
    file_put_contents($file, $message . "\n", FILE_APPEND|LOCK_EX);
}

function log_file_viewer()
{
    $log_file = log_get_file();
    $log_dir = dirname($log_file);
    $log_files = array(basename($log_file));
    $keep = config('log_keep_files', 0);
    for ($i = 1; $i <= $keep; $i += 1) {
        if (file_exists($log_dir . '/stash.' . $i . '.txt')) {
            $log_files[] = 'stash.' . $i . '.txt';
        }
    }

    use_module('backend');
    backend_collapsible('View Log Files', function () use ($log_files, $log_dir) {
        $log_file = log_get_file();
        if (!file_exists($log_file)) {
            ?><p>No log file to view</p><?php
            return;
        }
        $base_url = request_url();
        $current = request_query('log_file_viewer', '');
        ?><div class="row" id="log-file-viewer">
            <div class="col-sm-5 col-md-4 col-lg-3">
                <ul class="nav nav-pills nav-stacked"> <?php
                    foreach($log_files as $log_file) {
                        $url = router_add_query($base_url, ['log_file_viewer' => $log_file]);
                        printf(
                            '<li class="%s"><a href="%s">%s</a></li>',
                            $current === $log_file ? 'active' : '',
                            htmlspecialchars($url),
                            htmlspecialchars($log_file)
                        );
                    }
                    $url = router_add_query($base_url, ['log_file_viewer' => null]);
                    printf(
                        '<li><a href="%s">Close</a></li>',
                        htmlspecialchars($url)
                    );
                ?></ul>
            </div><div class="col-sm-7 col-md-8 col-lg-9">
                <?php if ($current && in_array($current, $log_files)) {
                    $log_contents = file_get_contents($log_dir . '/' . $current);
                    printf('<pre>%s</pre>', htmlspecialchars($log_contents));
                } ?>
            </div>
        </div><?php
        if ($current) {
            ?><script>jQuery(function () {
                jQuery('#log-file-viewer')
                    .closest('.collapse')
                    .siblings('h4')
                    .children('a')
                    .trigger('click');
            });</script><?php
        };
    });
}

function log_set_id($log_id)
{
    if (is_string($log_id)) {
        log_id($log_id);
    }
    return log_id();
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//


function log_id($reset = false)
{
    static $log_id = null;
    if (is_null($log_id)) {
        $log_id = uniqid();
    }
    if ($reset === true) {
        $reset = uniqid();
    }
    if ($reset && is_string($reset)) {
        $log_id = $reset;
    }
    return $log_id;
}

function log_timer($reset = false)
{
    static $start = null;
    if (is_null($start) || $reset) {
        $start = microtime(true);
    }
    $diff = microtime(true) - $start;
    $diff = number_format($diff, 2);
    $diff = str_pad($diff, 6, ' ', STR_PAD_LEFT);
    return $diff;
}

function log_get_file()
{
    $data_dir = filesystem_files_path();
    $log_dir = filesystem_mkdir($data_dir . '/log');
    return $log_dir . '/current.txt';
}

function log_rotate_files($force = false)
{
    static $rotated = false;
    if ($rotated && !$force) {
        return;
    }
    $rotated = true;

    $log_file = log_get_file();
    $log_dir = dirname($log_file);
    $max_entries = config('log_per_file', 0);
    $num_files = config('log_keep_files', 0);
    
    $entries = array_filter(file($log_file), function ($line) {
        return !(strpos(trim($line), '|') === 0);
    });
    if (count($entries) < $max_entries) {
        return;
    }
    rename($log_file, $log_dir . '/stash.0.txt');

    for ($i = ($num_files - 1); $i >= 0; $i -= 1) {
        $from = $log_dir . '/stash.' . $i . '.txt';
        $to = $log_dir . '/stash.' . ($i + 1) . '.txt';
        if (file_exists($from)) {
            rename($from, $to);
        }
    }
}

function log_format_message($prefix, $message_elements)
{
    $prefix_length = strlen($prefix);
    $message = count($message_elements) > 1
             ? call_user_func_array('sprintf', $message_elements)
             : array_shift($message_elements);
             ;
    $break = "\n" . str_repeat(' ', $prefix_length) . ' | ';
    $width = 72 - $prefix_length;
    $message = wordwrap($message, $width, $break);
    return $prefix . ' | ' . $message;
}

