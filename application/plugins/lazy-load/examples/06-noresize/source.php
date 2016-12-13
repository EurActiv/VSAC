<?php

namespace VSAC;

$file = realpath(__DIR__ . '/../01-wide/image.jpg');
$url = router_plugin_url($file);
$base_file = substr($file, strlen(filesystem_plugin_path()));

?>
<div>
    <div class="thumbnail">
        <img
            src="<?= lazy_load_placeholder_url('16x9') ?>"
            class="lazy-load lazy-load-preserve img-responsive"
            data-src="<?= $url ?>"
        >
        <div class="caption">
            No resizing; when inspecting the image, the source should be <?= $url ?>.
        </div>
    </div>
</div>
