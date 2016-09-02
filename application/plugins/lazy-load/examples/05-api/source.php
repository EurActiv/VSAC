<?php

namespace VSAC;


$endpoint = router_plugin_url('api.php', true);
$i_url = router_plugin_url(__DIR__ . '/image.jpg', true);
$aspects = array_values(config('aspect_ratios', array()));
$aspect = array_shift($aspects);

$qp_public = $qp = array(
    'image' => sprintf('<img src="%s">', $i_url),
    'strategy' => 'crop',
    'aspect'   => $aspect,
    'inline'   => true,
    'api_key'  => config('api_key', ''),
);


$url = router_add_query($endpoint, $qp);
$response = json_decode(file_get_contents($url), true);
$img = $response['lazyload'];

$regex = '/(src=[\'"]data:image\/jpeg;base64,)([^\'"]{30})([^\'"]+)([\'"])/';
$replace = '$1$2(...)$4';
$response['lazyload'] = preg_replace($regex, $replace, $response['lazyload']);

$qp_public['api_key'] = '-private-';
$url_public = router_add_query($endpoint, $qp_public);
$qp_public = htmlspecialchars(var_export($qp_public, true));



?><pre><code>
$params = <?= $qp_public ?>;
$endpoint = http:<?= $endpoint ?>;
$url = $endpoint . '?' . http_build_query($params);
/*
$url = <?= htmlspecialchars(var_export($url_public, true)) ?>;
*/
$response = json_decode(file_get_contents($url), true);
var_export($response);
/*
$response = <?= htmlspecialchars(var_export($response, true)) ?>;
*/
</code></pre>
<?php echo $img;



