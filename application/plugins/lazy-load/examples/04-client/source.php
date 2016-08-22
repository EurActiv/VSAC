<pre><code>
// A WordPress plugin that will use the lazy load

add_action('wp_enqueue_scripts', function () {
	wp_enqueue_script(
	    'lazy-load',
	    '//assets.euractiv.com/lazy-load/lazy-load.js',
	    array(),
	    '1.0.0',
	    true
    );
});



add_filter('wp_get_attachment_image_attributes', function ($attr, $attachment, $size) {
    $attr['data-src'] = preg_replace('/\-\d+x\d+\./', '.', $attr['src']);
    $attr['data-strategy'] = 'crop';
    $attr['src'] = '//assets.euractiv.com/lazy-load/placeholder/4x3.png';
    $attr['class'] .= ' lazy-load ';
    return $attr;
}, 10, 3);
</code></pre>
