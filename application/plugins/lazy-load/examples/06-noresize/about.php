<?php

namespace VSAC;


?>
<p>Under certain circumstances, you may wish to load the original, un-resized
    image without passing through the resizing callback.  The primary use case
    for this situation is images with an unusual or undetermined aspect ratio
    that you don't want to configure on the server.</p>
<p>To use this functionality, add the additional class <code>lazy-load-preserve</code>
    to the image tag:</p>
<?= backend_code('<img
    src="' .lazy_load_placeholder_url('<span class="text-danger">16x9</span>') .'"
    class="lazy-load lazy-load-preserve"
    data-src="http://example.com/path/to/image.jpg"
>'); ?>

