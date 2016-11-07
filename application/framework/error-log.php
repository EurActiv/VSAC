<?php

namespace VSAC;


use_module('backend-all');
use_module('error');

auth_require_authenticated();


//----------------------------------------------------------------------------//
//-- some templating functions                                              --//
//----------------------------------------------------------------------------//

// format the arguments in a backtrace for display
$format_args = function ($args) {
    if (empty($args)) return '';
    $args = array_map('htmlspecialchars', $args);
    return "\n\t\t" . implode("\n\t\t", $args) . "\n";
};

// format a backtrace array for display
$format_trace = function ($trace) use ($format_args) {
    $return = array();
    $default = array('function', 'file', 'line', 'class', 'type', 'args');
    $default = array_fill_keys($default, '');
    foreach ($trace as $t) {
        $t = array_merge($default, $t);
        $return[] = $t['file'] . ':' . $t['line'] . ":\n"
                  . $t['class'] . $t['type'] . $t['function']
                  . '(' . $format_args($t['args']) . ')';
    }
    return implode("\n\n", $return);
};

// print the inspect error panel
$print_inspect = function ($e) use ($format_trace) {
    $panel = '<div class="row"><div class="col-sm-4">
                <table class="table">
                    <tr> <td><b>Error code:      </b></td> <td>%s</td> </tr>
                    <tr> <td><b>Message:         </b></td> <td>%s</td> </tr>
                    <tr> <td><b>File:            </b></td> <td>%s</td> </tr>
                    <tr> <td><b>Line:            </b></td> <td>%s</td> </tr>
                    <tr> <td><b>Last Occurance:  </b></td> <td>%s</td> </tr>
                    <tr> <td><b>Occurances:      </b></td> <td>%s</td> </tr>
                </table>
              </div><div class="col-sm-8">
                <pre>%s</pre>
              </div></div>';
    printf(
        $panel,
        error_format_errcode($e['errno']),
        htmlspecialchars($e['errstr']),
        error_shorten_filename($e['errfile']),
        (int) $e['errline'],
        date('Y-m-d H:i:s', $e['errlog_ts']),
        $e['errlog_cnt'],
        $format_trace($e['trace'])
    );

};

// print the actions form that goes under the inspection panel
$resolve_form = function ($e, $burl) {
    form_form(
        array('method' => 'post'),
        function () use ($e, $burl) {
            form_hidden($e['errlog_key'], 'resolve', 'resolve');
            echo '<a class="btn btn-warning" href="'.$burl.'">Cancel</a> ';
            form_submit(false, 'Issue Resolved');
        },
        function () {
            error_resolve(request_post('resolve'));
            return form_flashbag('Error has been resolved');
        }
    );
};

// print the pagination links at the top of the log menu
$print_paginate = function ($p, $burl) {
    $link = ' | <a href="%s">%s</a>';
    echo '<p class="text-right">page: ' . ($p + 1);
    if ($p > 1) {
        printf($link, $burl, 'First');
    }
    if ($p > 0) {
        printf($link, router_add_query($burl, ['page'=> $p - 1]), 'Previous');
    }
    printf($link, router_add_query($burl, ['page'=> $p + 1]), 'Next');
    echo '</p>';
};

// print an error for display as a row in the log table
$print_row = function ($e, $burl) {
    $url = router_add_query($burl, array('inspect'=> $e['errlog_key']));
    echo '<tr>';
    echo '<td>' . error_format_errcode($e['errno']) . '</td>';
    echo '<td>' . htmlspecialchars($e['errstr']) . '</td>'; 
    echo '<td>' . error_shorten_filename($e['errfile']) . ':' . $e['errline'] . '</td>'; 
    echo '<td><a href="' . $url . '"><i class="fa fa-search"></i></a></td>'; 
    echo '</tr>';
};

// print the entire log tables
$print_log = function ($errs, $p, $burl) use ($print_paginate, $print_row) {
    $print_paginate($p, $burl);

    echo '<div class="table-responsive"><table class="table table-bordered">';
    echo '<tr><th>Level</th><th>Message</th><th>Location</th><th>Inspect</th></tr>';
    if (empty($errs)) {
        echo '<tr><td colspan="4">No errors to show</td></tr>';
    }
    foreach ($errs as $error_key) {
        $print_row(error_get($error_key), $burl);
    }
    echo '</table></div>';

};


//----------------------------------------------------------------------------//
//-- collect request information                                            --//
//----------------------------------------------------------------------------//

$inspect = request_query('inspect');
$page = request_query('page');
$base_url = router_url(basename(__FILE__));

// happens when an error has been resolved
if ($inspect && !($error = error_get($inspect))) {
    response_redirect($base_url);
}

//----------------------------------------------------------------------------//
//-- print out the page                                                     --//
//----------------------------------------------------------------------------//

backend_head('Error Log');


if (!framework_option('error_driver', '')) {
    echo '<p><b>Note:</b> no <code>error_driver</code> setting is set in
          <code>config/_framework.php</code>. The driver <code>nooperror</code>
          is being used by default</p>';
}

echo error_driver_limitations();

if ($inspect) {
    $print_inspect($error);
    $resolve_form($error, $base_url);
} else {
    $print_log(error_list($page), $page, $base_url);
}

backend_foot();




