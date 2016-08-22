<?php

namespace VSAC;

$file = config('example_file','');
$cdn_url = cdn_url($file);
$min_url = cdn_url('min/' . $file);
?>
<p>In most cases, it is better to reference minified resources directly if the
    source offers them. If not, you can prepend the requested file with
    <code>min/</code> to have this CDN do the minifcation for you. The code
    from the quick start example would change:</p>

<?= backend_code( $cdn_url) ; ?>

<p>to:</p>

<?= backend_code( $min_url) ; ?>

