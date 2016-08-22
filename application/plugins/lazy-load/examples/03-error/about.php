<?php

namespace VSAC;

$file = __DIR__ . '/error.jpg';

$url = router_plugin_url($file, true);

?>

<p>We have a <a href="<?= $url ?> " target="_blank">malformatted image</a>
    that we want to use as a thumbnail. It will not scale properly, resulting
    in a broken image. This may throw off site design if the image is part
    of the page flow.</p>
<p>Here you should see that the image falls back to the placeholder.</p>
