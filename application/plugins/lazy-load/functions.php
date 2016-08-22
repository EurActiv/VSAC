<?php

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework functions                                                    --//
//----------------------------------------------------------------------------//

/** @see plugins/example-plugin/example_plugin_config_items() */
function lazy_load_config_items()
{
    return array(
        [
            'aspect_ratios',
            array(),
            'An array of allowed aspect ratios, format <code>[width]x[height]</code>
            (for example, <code>16x9</code>). If the requested aspect ratio
            is not one of these, the <strong>first</strong> in the array will
            be used. Exists to prevent the disk from filling up.'
        ], [
            'api_key',
            '',
            'The key for using the api.',
            true
        ],
    );

}

/** @see plugins/example-plugin/example_plugin_bootstrap() */
function lazy_load_bootstrap()
{
    use_module('image');
    use_module('cal');
    use_module('http');
    use_module('apikey');
}


//----------------------------------------------------------------------------//
//-- Plugin functions                                                       --//
//----------------------------------------------------------------------------//


//----------------------------------------------------------------------------//
//-- Utilities                                                              --//
//----------------------------------------------------------------------------//
function lazy_load_placeholder_url($aspect_ratio)
{
    if (is_array($aspect_ratio)) {
        $aspect_ratio = $aspect_ratio['width'] . 'x' . $aspect_ratio['height'];
    }
    return router_use_rewriting()
         ? router_plugin_url('placeholder/'.$aspect_ratio.'.png')
         : router_plugin_url('placeholder.php?aspect='.$aspect_ratio)
         ;
}

function lazy_load_image_url($strategy, $aspect, $width, $image)
{
    $params = array(
        'strategy' => $strategy,
        'aspect'   => $aspect,
        'width'    => $width,
        'image'    => $image,
    );
    if (is_array($params['aspect'])) {
        $params['aspect'] = $params['aspect']['width'] . 'x' . $params['aspect']['height'];
    }
    return router_use_rewriting()
         ? router_plugin_url('img/'.implode('/', $params))
         : router_plugin_url('img.php?'.http_build_query($params))
         ;
}






//----------------------------------------------------------------------------//
//-- Fetch and validate user-provied parameters                             --//
//----------------------------------------------------------------------------//

/**
 * Parse an aspect ratio
 *
 * @param string $ar the unparsed aspect ratio, in format "[Width]x[Height]"
 * @return array the parsed aspect ratio, in format
 *         array(
 *            'width'=>int,
 *            'height' => int
 *         )
 */
function lazy_load_parse_aspect_ratio($ar)
{
    $aspect = explode('x', $ar, 2);
    $return = array();
    $return['width'] = (int) array_shift($aspect);
    $return['height'] = (int) array_shift($aspect);

    // Get the greatest common denominator of aspect ratio and reduce it
    // @see http://stackoverflow.com/a/17497617/1459873
    $gcd = function ($a, $b) use (&$gcd) {
        return $b ? $gcd($b, $a % $b) : $a;
    };
    $aspect_gcd = $gcd($return['width'], $return['height']);
    if ($aspect_gcd > 1) {
        $return['width']  /= $aspect_gcd;
        $return['height'] /= $aspect_gcd;
    }
    return $return;
}

/**
 * Extract parameters from $_GET['path'] if URL rewriting is being used.
 *
 * @return array
 */
function lazy_load_get_path_parameters()
{
    if (!($path = request_query('path'))) {
        return array();
    }
    $path = explode('/', $path, 4);
    $return = array();
    $return['strategy'] = array_shift($path);
    $return['aspect']   = array_shift($path);
    $return['width']    = array_shift($path);
    $return['image']    = array_shift($path);
    return array_filter($return);
}


/**
 * Get the parameters from $_GET if URL rewriting is not being used.
 *
 * @return array
 */
function lazy_load_get_query_parameters()
{
    return array_filter(array(
        'strategy' => request_query('strategy'),
        'aspect'   => request_query('aspect'),
        'width'    => request_query('width'),
        'image'    => request_query('image'),
    ));

}

function lazy_load_get_check_image_url($url, &$error = '')
{
    // repair url encoding
    $url = urlencode($url);
    $url = str_replace(array('%3A', '%2F', '%25'), array(':', '/', '%'), $url);
    // repair protocol if it was not properly URL encoded
    $url = preg_replace('#^(https?:)/+#', '$1//', $url);
    if (!($url = http_uri_is_authorized($url, $error))) {
        $error = $error ? $error : 'Image url not authorized';
        $error .= urlencode($url);
        return '';
    }
    return $url;
}


/**
 * Find and validate parameters sent to application
 *
 * @param $error the error message, if any
 * @return array(
 * )
 */
function lazy_load_get_parameters(&$error = '', $check_image_url = true)
{
    $error = '';
    $return = array();
    $requested = array_merge(
        array('strategy'=>'','aspect'=>'','width'=>'','image'=>''),
        lazy_load_get_path_parameters(),
        lazy_load_get_query_parameters()
    );
    
    $aspects = config('aspect_ratios', array());
    $aspect = lazy_load_parse_aspect_ratio($requested['aspect']);
    if (!in_array($aspect['width'].'x'.$aspect['height'], $aspects)) {
        $aspect = lazy_load_parse_aspect_ratio(array_shift($aspects));
    }
    $requested['aspect'] = $aspect;


    $strategies = array('resize', 'crop', 'crop-top', 'crop-bottom');
    if (!in_array($requested['strategy'], $strategies)) {
        $error = 'Invalid strategy';
        $requested['strategy'] = 'crop';
    }
    $requested['width'] = (int) $requested['width'];
    if ($requested['width'] < 1) {
        $error = 'Invalid strategy';
        $requested['width'] = 300;
    }
    // round to up intervals of 25 to keep the combinations of images down
    $requested['width'] = ceil($requested['width']/25) * 25;
    
    if (!$requested['image']) {
        $error = 'Image not set';
    } elseif ($check_image_url) {
        $requested['image'] = lazy_load_get_check_image_url($requested['image'], $error);
    }
    return $requested;
}



//----------------------------------------------------------------------------//
//-- Image processing                                                       --//
//----------------------------------------------------------------------------//


/**
 * Scale a locally stored image file, save it.
 *
 * @param string $aspect the aspect ratio the final image should have, format
 *        "{$width}x{$height}"
 * @param string $aspect the maximium size the final image should have, format
 *        "{$width}x{$height}", set either to 0 for infinity
 * @param string $strategy the scaling strategy, one of:
 *        - 'resize': resize the image to fit the aspect ratio exactly
 *        - 'crop': resize to match the largest of aspect vs max, then crop in
 *          middle to fit to aspect ratio
 *        - 'crop-top': resize as in 'crop', then crop from the top/right (eg,
 *          cut off the bottom/left)
 *        - 'crop-bottom': resize as in 'crop', then crop from the bottom/left
 *          (eg, cut off the top/right)
 * @return true on success, false on failure
 */
function lazy_load_scale($url, $aspect, $width, $strategy, &$error = '')
{
    return cal_get_permutation(
        $url,
        function () use ($url) {
            if (http_get($url, $body)) {
                return $body;
            }
            return null;
        },
        implode('x', $aspect) . '.' . $width . '.' . $strategy,
        function ($original) use ($url, $aspect, $width, $strategy) {
            $blob = image_scale_blob($original, $aspect, $width, $strategy);
            return $blob;
        }
    );

    return $scaled;
}


/**
 * Create a transparent png to an aspect ratio and stream it to the browser.
 * Surprisingly, this is just as fast as saving the image and serving it with
 * apache.
 */
function lazy_load_placeholder($size)
{
    $headers = array(
        'Last-Modified' => filemtime(__FILE__),
        'Cache-Control' => 'max-age=31536000',
        'Content-Type'  => 'image/png',
    );


    $im = imagecreatetruecolor($size['width'], $size['height']);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagecolortransparent($im, $black);
    ob_start();
    imagepng($im);
    $body = ob_get_contents();
    ob_end_clean();
    imagedestroy($im);

    response_send($body, $headers);
}


