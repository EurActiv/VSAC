<?php

namespace VSAC;


use_module('backend-all');


if (auth_is_authenticated()) {
    build_minify_js(__DIR__.'/vsac-social.js');
    build_compile_sass(__DIR__.'/');
    // NOTE: an earlier version of this application used VGMH as the global
    // namespace instead of VSAC. This recreates the scripts and styles for
    // backwards compatability.
    $fix_backcompat = function ($file) {
        $file = __DIR__ . '/' . $file;
        $dest = basename($file);
        $dest = str_replace('vsac', 'vgmh', $dest);
        $dest = dirname($file) . '/' . $dest;
        if (file_exists($dest) && filemtime($dest) >= filemtime($file)) {
            return;
        }
        $source = file_get_contents($file);
        $source = str_replace(['VSAC','vsac'], ['VGMH', 'vgmh'], $source);
        file_put_contents($dest, $source);
    };
    $files = array(
        'vsac-social.js',
        'vsac-social-min.js',
        'vsac-social.css',
        'vsac-social-min.css',
    );
    foreach($files as $f) {
        $fix_backcompat($f);
    }
}


$script_url = router_plugin_url('vsac-social.js');
$script_url_min = router_plugin_url('vsac-social-min.js');
$stylesheet_url = router_plugin_url('vsac-social.css');
$stylesheet_url_min = router_plugin_url('vsac-social-min.css');


backend_head('Social Share Buttons API', [], function () use ($script_url) {
    echo "<script src='{$script_url}'></script>";
});

?>
<p>This API provides social media sharing buttons that:</p>
<ul>
    <li>are simple and light weight</li>
    <li>are easy to customize</li>
    <li>protect the user's privacy by proxying count requests to this
        server.</l>
</ul>

<hr><h3>Getting Started</h3>
<p>Start by including the script:</p>
<?= backend_code("<script src='{$script_url_min}'></script>"); ?>
<p>Notes:</p>
<ul>
    <li>jQuery must be loaded first</li>
    <li>The default button formatter outputs share buttons that are
        marked up for a Bootstrap website using Font Awesome to provide
        icons. If you wish not to use these, you must override the
        button formatter.</li>
    <li>The script will load the stylesheet
        <?= backend_codelink($stylesheet_url_min) ?>
        (<?= backend_codelink($stylesheet_url, 'uncompressed') ?>)</li>
</ul>

<?php http_examples_in_config(array('example.com', request_host())); ?>
<?php docs_examples(); ?>
<hr><h3>System Status</h3><br>
<?php

cal_status('clean-cache.php');
kval_status('clean-cache.php');

backend_config_table();

backend_foot();
