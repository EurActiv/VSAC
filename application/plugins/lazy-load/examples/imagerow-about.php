<?php

namespace VSAC;

// define $file before including

$url = router_plugin_url($file);
$size = number_format(filesize($file) / (1024 * 1024), 1);

?>

<p>We have a <a href="<?= $url ?> " target="_blank">fairly large
    image</a> (<?= $size ?>MB) that we want to use as a thumbnail. It is too big for
    desktops and certainly too big for mobiles. We may have many of these images
    on one page, which will slow it down considerably.</p>
<p>Note that when you loaded this example you have may seen a flash of red
    background on the image.  This red is for demonstration; you set it to a
    color that will not jar on your design. There is a small, clear 16x9 [or
    4x3] pixel png image in this place, allowing us to scale with CSS and hold
    the aspect ratio. The clear placeholder prevents the page from jumping
    around while the real image loads.</p>
<p>After the image loads, there may be a case where the user resizes the screen.
    If the scaled image stays in place, it will be pixelated and look bad.  So
    we watch for window resize events and reload the image if it resizes. Press
    the "resize" button to see this in action.</p>
