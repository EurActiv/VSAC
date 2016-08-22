<?php

namespace VSAC;

$file = config('example_file','');
$domain = cdn_get_domain($file);
$cdn_url = cdn_url($file);

?>
<p>To use, rewrite the URL of the CDN provided asset to replace the domain name
with this CDN. For example:</p>

<?= backend_code( $domain . $file) ; ?>

<p>should become:</p>

<?= backend_code( $cdn_url) ; ?>

<p>The supported libraries are:</p>

<ul>
<?php foreach(config('domain_map', []) as $item) { ?>
    <li><?= $item['name'] ?></li>
<?php } ?>
</ul>
