<?php

/**
 * Manipulate images
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function image_depends()
{
    return array();
}


/** @see example_module_config_items() */
function image_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function image_sysconfig()
{
    if (!class_exists('Imagick')) {
        return 'PHP Imagick extension is not installed';
    }
    if (!function_exists('getimagesize')) {
        return 'PHP Function getimagesize is not available. Maybe install gd?';
    }
    return true;
}

/** @see example_module_test() */
function image_test()
{
    return true;
}


//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//

/**
 * Similar to getimagesize, but also works on blobs. Always returns array, if
 * there was an error, elements are set to 0 or empty string
 *
 * @param string $image either the absolute path to the image on disk or the
 * blob
 *
 * @return array['width'=>int,'height'=>int,'mime'=>string]
 */
function image_info($image)
{
    // http://stackoverflow.com/a/2756441/1459873
    $is_path = preg_match('#^(\w+/){1,2}\w+\.\w+$#', $image);
    if ($is_path && false !== ($info = getimagesize($image))) {
        return array(
            'width' => $info[0],
            'height' => $info[1],
            'mime' => $info['mime'],
            'type' => 'file',
        );
    }
    $im = new \Imagick();
    if ($im->readImageBlob($image)) {
        return array(
            'width'  => $im->getImageWidth(),
            'height' => $im->getImageHeight(),
            'mime'   => $im->getImageMimeType(),
            'type'   => 'blob',
        );
    }
    return array('width' => 0, 'height' => 0, 'mime'=>'', 'type' => '');

}

/**
 * Get a data uri for an image
 *
 * @param string $image either the path to to the image on disk or an image blob
 */
function image_data_uri($image, $max_width = 0)
{
    $info = image_info($image);
    if (!$info['type']) {
        return $image;
    }
    if ($info['type'] == 'file') {
        $image = file_get_contents($image);
    }
    if ($max_width && $max_width < $info['width']) {
        $image = image_scale_blob($image, $info, $max_width, 'crop');
        $info = image_info($image);
    }
    return sprintf('data:%s;base64,%s', $info['mime'], base64_encode($image));
}

/**
 * Use ImageMagick to make a sprite.
 *
 * @param string $sprite_abspath the absolute path to save the sprite at
 * @param array $icons_abspaths the absolute paths to the icons to concatenate
 * @param string $orientation either 'portrait' to stack the icons or
 *        'landscape' to put them side-by-side
 * @param integer $padding the number of pixels of padding to place around icons
 *
 * @return bool|array this function will calculate a "box" size for the icons
 *       based on the height and width of the largest icons. Each icon will be
 *       at the top left corner of in a box, plus padding. The function returns
 *       an array with this box size, including padding.
 *       If there was an error, returns false.
 */
function image_sprite($sprite_abspath, $icons_abspaths, $orientation = 'portrait', $padding = 5)
{

    // open all the images, create teardown function
    $sprite = new \Imagick();
    $icons = array();

    foreach($icons_abspaths as $icon_abspath) {
        $icons[] = new \Imagick($icon_abspath);
    }

    $tear_down = function ($return) use (&$sprite, $icons) {
        foreach($icons as $icon) {
            $icon->clear();
            $icon->destroy();
        }
        $sprite->clear();
        $sprite->destroy();
        return $return;
    };

    // calculate dimensions
    $max_width = $max_height = 0;
    foreach($icons as $icon) {
        $max_height = max($max_height, $icon->getImageHeight());
        $max_width = max($max_width, $icon->getImageWidth());
    }
    $sprite_width = $max_width + ($padding * 2);
    $sprite_height = $max_height + ($padding * 2);
    if ($orientation == 'landscape') {
        $sprite_width *= count($icons);
    } else {
        $sprite_height *= count($icons);
    }

    // check if we really need to do this
    $sprite_mtime = file_exists($sprite_abspath) ? filemtime($sprite_abspath):0;
    $icons_mtime = 0;

    foreach($icons_abspaths as $icon_abspath) {
        $icons_mtime = max($icons_mtime, filemtime($icon_abspath));
    }

    if ($sprite_mtime >= $icons_mtime) {
        $sprite_size = getimagesize($sprite_abspath);
        if ($sprite_size[0] == $sprite_width && $sprite_size[1] == $sprite_height) {
            return $tear_down(array(
                'box_height'    => $max_height,
                'box_width'     => $max_width,
                'sprite_height' => $sprite_height,
                'sprite_width'  => $sprite_width,
            ));
        }
    }

    // compose the sprite
    $sprite->newImage($sprite_width, $sprite_height, new \ImagickPixel('transparent'));

    foreach($icons as $n => $icon) {
        $x = $y = $padding;
        $x += round(($max_height - $icon->getImageHeight())/2);
        $y += round(($max_width - $icon->getImageWidth())/2);
        $cumulative_padding = $n * $padding * 2;
        if ($orientation == 'landscape') {
            $x += ($n * $max_width) + $cumulative_padding;
        } else {
            $y += ($n * $max_height) + $cumulative_padding;
        }
        $sprite->compositeImage($icon, \Imagick::COMPOSITE_COPY, $x, $y);
    }
    $sprite->setImageCompressionQuality(100);
    $sprite->writeImage($sprite_abspath);

    return $tear_down(array(
        'box_height'    => $max_height + ($padding * 2),
        'box_width'     => $max_width + ($padding * 2),
        'icon_height'   => $max_height,
        'icon_width'    => $max_width,
        'sprite_height' => $sprite_height,
        'sprite_width'  => $sprite_width,
    ));

}


/**
 * Scale a locally stored image file, save it as a progresive jpeg
 *
 * @param string $src_abspath the absolute path to the image to scale
 * @param string $dest_abspath the absolute path to save the scaled image
 * @param array $aspect @see image_scale_im_obj()
 * @param integer $width @see image_scale_im_obj()
 * @param string  @see image_scale_im_obj()
 *
 * @return true on success, false on failure
 */
function image_scale($src_abspath, $dest_abspath, $aspect, $width, $strategy)
{
    $im = new \Imagick($src_abspath);
    $im = image_scale_im_obj($im, $aspect, $width, $strategy);
    return image_return_write($im, $dest_abspath);
}

/**
 * Scale an image blob, convert it to progressive JPEG
 *
 * @param string $blob the absolute path to the image to scale
 * @param array $aspect @see image_scale_im_obj()
 * @param integer $width @see image_scale_im_obj()
 * @param string  @see image_scale_im_obj()
 *
 * @return string the scaled blob, ready to save or stream
 */
function image_scale_blob($blob, $aspect, $width, $strategy)
{
    $im = new \Imagick();
    $im->readImageBlob($blob);
    $im = image_scale_im_obj($im, $aspect, $width, $strategy);
    return image_return_blob($im);
}


/**
 * Check that a hex color is properly formatted.
 *
 * @param string $color hex string to check, eg '#abc', 'abc', '#abcdef'
 *
 * @return the hex color formatted for HTML/CSS, if it could be generated
 */
function image_hex_color($color)
{
    $c = strtoupper($color);
    if (strpos($c, '#') !== 0) {
        $c = '#'.$c;
    }
    if (!preg_match('/^#([A-F0-9]{3}){1,2}$/', $c)) {
        err('invalid hex color '.$color);
    }
    return $c;

}

//-- SVG direct manipulation functions: Imagick's support for manipulating ---//
//-- SVGs is pretty poor, so thesefunctions  use regexes on the SVG markup ---//
//-- string to hack around it. -----------------------------------------------//

/**
 * Convert a source SVG to a PNG icon
 *
 * @param string $svg_abspath the path to the svg on disk
 * @param string $icon_abspath the path to write the icon to
 * @param string &$height @see image_scale_svg()
 * @param string &$width @see image_scale_svg()
 * @param string $color @see image_color_svg();
 */
function image_svg_to_icon($svg_abspath, $icon_abspath, &$height = 0, &$width = 0, $color = false)
{
    $svg_string = file_get_contents($svg_abspath);
    $im = image_svg_string_to_icon_obj($svg_string, $height, $width, $color);
    return image_return_write($im, $icon_abspath);
}

/**
 * Convert an SVG string to a PNG image string
 *
 * @param string $svg_abspath the path to the svg on disk
 * @param string $icon_abspath the path to write the icon to
 * @param string &$height @see image_scale_svg()
 * @param string &$width @see image_scale_svg()
 * @param string $color @see image_color_svg()
 *
 * @return string the PNG image as a binary blob
 */
function image_svg_to_icon_blob($svg_string, &$height = 0, &$width = 0, $color = false)
{
    $im = image_svg_string_to_icon_obj($svg_string, $height, $width, $color);
    return image_return_blob($im);
}

/**
 * Change all of the colors in an SVG to a given color. Useful for single-color
 * SVGs that are supposed to become icons.
 *
 * @param string $svg_string the svg markup string
 * @param string $color the new color, hex format
 *
 * @return the new svg markup string
 */
function image_color_svg($svg_string, $color)
{
    $color = image_hex_color($color);
    return preg_replace('/fill="[^"]*"/', 'fill="'.$color.'"', $svg_string);
}

/**
 * Scale an SVG image
 *
 * @param string $svg_string the SVG string content
 * @param int &$height the icon height; if set to zero, will be calculated based
 * on the $width parameter and the SVG's aspect ratio and stored back in the
 * $height parameter.
 * @param int &$width the icon height; if set to zero, will be calculated based
 * on the $height parameter and the SVG's aspect ratio and stored back in the
 * $width parameter.
 *
 * @return string the scaled SVG markup
 */
function image_scale_svg($svg_string, &$height = 0, &$width = 0)
{
    if (!$height && !$width) {
        return $svg_string;
    }

    $regex_width = '/(.*<svg[^>]* width=")([\d]+%?)(.*)/si';
    $regex_height = '/(.*<svg[^>]* height=")([\d]+%?)(.*)/si';

    preg_match($regex_width, $svg_string, $svg_width);
    preg_match($regex_height, $svg_string, $svg_height);

    $svg_width = (int) $svg_width[2];
    $svg_height = (int) $svg_height[2];

    if (!$svg_width || !$svg_height) {
        return $svg_string;
    }
    // scale to make width and height big enough
    if (!$width) {
        $width = round($height * ($svg_width / $svg_height));
    } elseif (!$height) {
        $height = round($width * ($svg_height / $svg_width));
    }

    $svg_string = preg_replace($regex_width, "\${1}{$width}\${3}", $svg_string);
    $svg_string = preg_replace($regex_height, "\${1}{$height}\${3}", $svg_string);

    return $svg_string;
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//


/**
 * Scale a locally stored image file, save it as a progresive jpeg
 *
 * @private
 *
 * @param string $src_abspath the absolute path to the image to scale
 * @param string $dest_abspath the absolute path to save the scaled image
 * @param array $aspect the aspect ratio the final image should have, format
 *        array('width' => integer, 'height' => integer)
 * @param integer $width the width the scaled image should have, in pixels
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
function image_scale_im_obj(\Imagick $im, $aspect, $width, $strategy)
{

    $height = round(($width/$aspect['width'])*$aspect['height']);

    $source_w = $im->getImageWidth();
    $source_h = $im->getImageHeight();
    $source_a = $source_h / $source_w;
    $target_a = $aspect['height'] / $aspect['width'];

    switch ($strategy) {
        case 'resize':
            $im->scaleImage($width, $height);
            break;
        case 'crop-top':
            if ($target_a < $source_a) {
                $im->scaleImage($width, 0);
                $im->cropImage($width, $height, 0, 0);
            } else {
                $im->scaleImage(0, $height);
                $im->cropImage($width, $height, 0, 0);
            }
            break;
        case 'crop-bottom':
            if ($target_a < $source_a) {
                $im->scaleImage($width, 0);
                $im->cropImage($width, $height, 0, $im->getImageHeight() - $height);
            } else {
                $im->scaleImage(0, $height);
                $im->cropImage($width, $height, $im->getImageWidth() - $width, 0);
            }
            break; 
        case 'crop':
        default:
            $im->cropThumbnailImage($width, $height);
            break;       
    }
    $im->setImageFormat('jpeg');
    $im->gaussianBlurImage(0.05, 0.5);
    $im->setImageCompressionQuality(75);
    $im->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
    $im->stripImage();
    return $im;
}


/**
 * Convert a source SVG to a PNG icon, without writing it anywhere
 *
 * @param string $svg_abspath the path to the svg on disk
 * @param string $icon_abspath the path to write the icon to
 * @param string &$height @see image_scale_svg()
 * @param string &$width @see image_scale_svg()
 * @param string $color @see image_color_svg();
 *
 * @return \Imagick the Imagick object
 */
function image_svg_string_to_icon_obj($svg_string, &$height = 0, &$width = 0, $color = false)
{

    $svg_string = image_scale_svg($svg_string, $height, $width);
    if ($color) {
        $svg_string = image_color_svg($svg_string, $color);
    }
    if (strpos($svg_string, '<?xml') !== 0) {
        $svg_string = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . $svg_string;
    }

    $im = new \Imagick();
    $im->setBackgroundColor(new \ImagickPixel('transparent'));
    $im->readImageBlob($svg_string);
    $svg_width = $im->getImageWidth();
    $svg_height = $im->getImageHeight();

    $im->setImageFormat('png32');
    $im->setImageCompressionQuality(100);
    return $im;

}

/**
 * Take an imagick object, get the binary blob contents and destroy it.
 *
 * @private
 *
 * @return string the image as a blob
 */
function image_return_blob($im)
{
    $return = $im->getImageBlob();
    $im->clear();
    $im->destroy();
    return $return;
}

/**
 * Take an imagick object, write it to disk and destroy it.
 *
 * @private
 *
 * @return bool the status of the write operation
 */
function image_return_write($im, $write_to_abspath)
{
    $return = $im->writeImage($write_to_abspath);
    $im->clear();
    $im->destroy();
    return $return;
}
