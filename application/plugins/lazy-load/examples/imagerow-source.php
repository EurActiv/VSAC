<?php

namespace VSAC;

// define "file" before including

$url = router_plugin_url($file);
$base_file = substr($file, strlen(filesystem_plugin_path()));
$aspects = array_values(config('aspect_ratios', array()));
$strategies = array('resize','crop','crop-top','crop-bottom');
$uid = 'resize-' . uniqid();

// HACK! This plugin has a concurrency issue when using the filesystem cache
// plugin and very large image files. Fixing the bug is a low priority because
// it does not seem to be uncovered in real world use. But is shows in the demo.
// This will warm up the cache so that the concurrency issue does not appear in
// the demo.
$hack_url = router_plugin_url('img.php', true);
$hack_url = router_add_query($hack_url, array(
    'image' => $url,
    'strategy' => 'resize',
    'aspect'   => $aspects[0],
    'width'    => 400,
));
http_get($hack_url);


?>
<div id="<?= $uid ?>" data-width="1">
    <div class="row text-center">
        <button class="<?= $uid ?> btn btn-default">Resize</button><br><br>
    </div>
    <?php foreach ($aspects as $offset => $aspect): ?><div class="row">
        <?php foreach($strategies as $strategy): ?><div class="col-xs-3" data-width="1">
            <div class="thumbnail">
                <img
                    src="<?= lazy_load_placeholder_url($aspect) ?>"
                    class="lazy-load img-responsive"
                    data-strategy="<?= $strategy ?>"
                    data-src="<?= $url ?>"
                >
                <div class="caption">
                    <p><?= $base_file ?>, <?= $strategy ?>, <?= $aspect ?></p>
                </div>
            </div>
        </div><?php endforeach; ?>
    </div><?php endforeach; ?>
</div>

<script>$(function () {
    'use strict';
    $('.<?= $uid ?>').on('click', function (e) {
        e.preventDefault();
        $(this).parent().parent().find('.row > div').each(function () {
            var el = $(this), w = (el.data('width') % 4) + 1;
            el.data('width', w);
            el.attr('class', 'col-xs-' + (w * 3));
        });
        $(window).trigger('resize');
    });
    $('.nav-tabs a').on('click', function () {
        $(window).trigger('resize');
    });
});</script>

