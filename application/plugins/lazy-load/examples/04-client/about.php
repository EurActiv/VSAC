<?php

namespace VSAC;

$url_min = router_plugin_url('lazy-load-min.js');
$url = router_plugin_url('lazy-load.js');

?>
<ol>
    <li>Include the following script in your web page, after jQuery:<br><br>
        <?= backend_code("<script src='{$url_min}'></script>") ?>
        <br>Alternatively, to enable debugging, use this:<br><br>
        <?= backend_code("<script>vsac_lazyload_debug=true<script>\n<script src='{$url}'></script>") ?>
        <br></li>
    <li>Change the markup of the images you want to lazy load to look like this:<br><br>
    <?= backend_code('<img
        src="' .lazy_load_placeholder_url('<span class="text-danger">16x9</span>') .'"
        class="lazy-load"
        data-strategy="strategy"
        data-src="http://example.com/path/to/image.jpg"
    >'); ?>
    <br>A couple of notes:<ul>
            <li><code>src</code>: modify the two numbers to match the
                aspect ratio that the image should have (width by
                height). This aspect ratio will be used when resizing
                the source image.</li>
            <li>the <code>lazy-load</code> class can be combined with
                other classes (eg, <code>lazy-load img-responsive</code>)
                but it must be present.</li>
            <li><code>data-strategy</code> is the resizing strategy that
                should be used when resizing the image. Possible values:
                <ul>
                    <li><code>resize</code>: stretch to fit in both
                        directions</li>
                    <li><code>crop</code>: resize to the largest fit
                        on either axis, then crop in the middle to fit
                        the aspect ratio</li>
                    <li><code>crop-top</code>: like <code>crop</code>,
                        but crops from the top/right of the image (eg,
                        it cuts off the bottom or left of the image)</li>
                    <li><code>crop-bottom</code>: like <code>crop</code>,
                        but crops from the bottom/left of the image (eg,
                        it cuts off the top or right of the image)</li>
                </ul></li>
            <li><code>data-src</code> is the path to the original image
                that should be resized. It can be a relative path, an
                absolute path or a fully qualified url</li>
        </ul></li>
</ol>
