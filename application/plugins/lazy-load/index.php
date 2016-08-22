<?php

namespace VSAC;


use_module('backend-all');

if (auth_is_authenticated()) {
    build_minify_js(__DIR__.'/lazy-load.js');
}


backend_head('Image lazy load API', [], function () {
    ?>
    <style class="example-include">
        img.lazy-load {
            outline:1px solid green;
            background-color:red;
            width:100%;
        }
    </style>
    <style>
        #example-resize {
            display:none;
        }
        #example-source {
            display:none;
        }
    </style>
    <script>vsac_lazyload_debug=true</script>
    <script src="<?= router_plugin_url('lazy-load-min.js') ?>"></script>
    <script>$(function () {
        'use strict';
        $('.nav-tabs a').on('click', function () {
            setTimeout(function () {
                $(window).trigger('scroll');
            }, 500);
        });
    });</script>
    <?php
});
?>
<p>The Image Lazy-Load API exists to deal with the combined issues that
    arise when combining graphics-heavy sites with responsive web
    design. It is divorced from CMSs to prevent having to re-implement
    the back code for each system.</p>
<p>It is not the most advanced of lazy-load systems, but exists
    accomplishes a couple of key tasks:</p>
<ul>
    <li>Resizes images so that they fit the required area but remain
        reasonably small as files.</li>
    <li>Defers image loading until after the page loads. Uses
        spaceholders to prevent the page content from jumping
        around.</li>
    <li>Only loads images that are actually visible and poll regularly
        to load images that have become visible after page load.</li>
    <li>Converts JPEGs to progressive JPEGs</li>
</ul>

<?php docs_examples() ?>

<hr><h3>Test</h3>
<?php
    $aspects = array_values(config('aspect_ratios', array()));
    $aspect = array_shift($aspects);
?>
<div class="row"><div class="col-md-6 col-md-offset-3">
    <img
        src="<?= lazy_load_placeholder_url($aspect) ?>"
        class="lazy-load img-responsive"
        data-strategy="resize"
        data-src="<?= router_plugin_url('examples/waterfall.png', true) ?>"
    >
    <p>This image is a check that the lazy loader works immediately on
        page load. The test image should be more than 500px from top,
        but visible in the browser screen on load.</p>
</div></div>
<hr><h3>System Status</h3><br>

<?php

cal_status('clean-cache.php');
backend_config_table();

backend_foot();
