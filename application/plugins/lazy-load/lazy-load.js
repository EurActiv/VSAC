/*jslint browser, multivar, single */
/*global window, jQuery */

/**
 * The script that manages image lazy loading.  See the index file in this
 * directory for use.
 */
(function (global, $) {
    'use strict';

    //------------------------------------------------------------------------//
    //-- utilities                                                          --//
    //------------------------------------------------------------------------//

    /**
     * check if a variable is a jquery object. JSLint doesn't like instanceof
     * @param mixed obj the variable to check
     * @return bool
     */
    var is_jquery = function (obj) {
        return obj && obj.hasOwnProperty('jquery');
    };

    /**
     * If this then that, because JSLint doesn't like ternary operations
     * @param bool check
     * @param mixed yep return if check is true
     * @param mixed nope return if check is false
     * @return mixed
     */
    var iftt = function (check, yup, nope) {
        if (check) {
            return yup;
        }
        return nope;
    };

    /**
     * For events that fire in rapid succession (scroll, resize), wait for the
     * successtion to end and then fire the event
     */
    var stack_event = function (jq_obj, event, wait, callback) {
        var timeout;
        jq_obj.on(event + '.lazyload', function () {
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                callback();
            }, wait || 250);
        });
    };

    /**
     * A wrapper around $.each that flips the order of the parameters so that
     * we don't have to use this or the unused index
     *
     * @param subject same as $.each
     * @param callback the callback to run
     * @return undefined
     */
    var each = function (subject, callback) {
        $.each(subject, function (idx, element) {
            return callback(element, idx);
        });
    };

    /**
     * Check that a url has the scheme, prepend it if it's not there
     *
     * @param string url the URL to check
     * @param string scheme the scheme to prepend, defaults to current document
     */
    var check_scheme = function (url, scheme) {
        if (url && url.substring(0, 2) === '//') {
            scheme = scheme || global.location.protocol;
            url = scheme + url;
        }
        return url;
    };

    /**
     * Get the base URL for calling back to the lazy loader. If it's not
     * explicitly set, try to locate this script file and use it.
     *
     * @return string the base URL
     */
    var base_url = (function () {
        var url = check_scheme(global.vsac_lazyload_api_url) || '';
        var calculate = function () {
            var regex = /(.*\/)lazy-load\d*(-min)?\.js(\?.*)?$/;
            each($('script'), function (script) {
                if (script.src && regex.test(script.src)) {
                    url = script.src.match(regex)[1];
                    return false;
                }
            });
            url = check_scheme(url);
            return url;
        };
        return function () {
            return url || calculate();
        };
    }());


    //------------------------------------------------------------------------//
    //-- Viewport: track size separately, don't recalculate for each image  --//
    //------------------------------------------------------------------------//

    var viewport = {top: 0, bottom: 500};
    var calculate_viewport = (function () {
        var w = $(global);
        return function () {
            viewport.top = w.scrollTop();
            viewport.bottom = viewport.top + w.height();
        };
    }());

    $(function () {
        calculate_viewport();
        stack_event($(global), 'scroll', 100, calculate_viewport);
    });

    //------------------------------------------------------------------------//
    //-- Manage individual images                                           --//
    //------------------------------------------------------------------------//
    /**
     * Constructor around a lazyload image. Public api:
     *
     *   obj.is_error_state (bool) the object is in an error state
     *   obj.uid (string) a unique id for the object
     *   obj.calculate_is_visible (function) recalculate the visibility of the image
     *   obj.calculate_on_screen (function) recalculate if the image is in the viewport
     *   obj.calculate_needs_upscale (function) check if the image needs upscaling
     *   obj.do_load check if the image needs to be loaded and load if so
     */
    var LazyLoadImage = function (image) {

        var that = {};
        var $img = iftt(is_jquery(image), image, $(image));
        var img = $img[0];

        // class attributes
        that.uid = Math.floor((1 + Math.random()) * 0x10000).toString(16);

        // image attributes

        var last_width = 0;

        var strategy = function () {
            return $img.data('strategy') || 'resize';
        };

        var pretty_urls = (function () {
            var pretty = $img.data('pretty-urls');
            if (!pretty) {
                pretty = !((/(\?|&)aspect=(\d+x\d+)$/).test(img.src || ''));
            }
            return function () {
                return pretty;
            };
        }());

        var aspect = (function () {
            var calculated, explicit = $img.data('aspect'), regex, matches;
            if (!explicit) {
                regex = iftt(pretty_urls(), /\/(\d+x\d+)\.png$/, /aspect=(\d+x\d+)$/);
                matches = img.src.match(regex);
                if (matches) {
                    explicit = matches[1];
                }
            }
            return function () {
                if (explicit) {
                    return explicit;
                }
                if (calculated) {
                    return calculated;
                }
                var w = $img.width();
                var h = $img.height();
                if (h && w) {
                    calculated = w + 'x' + h;
                    return calculated;
                }
                return '16x9';
            };
        }());

        // error handling
        var placeholder_url = img.src || '';
        $img.on('error.lazyload', function () {
            var err_url = $img.attr('src');
            $img.off('error.lazyload');
            $img.off('load.lazyload');
            img.src = placeholder_url;
            that.is_error_state = true;
            global.console.log('Error loading url: ' + err_url);
        });

        // onload events
        var has_faded = false;
        $img.on('load.lazyload', function () {
            if (!has_faded) {
                has_faded = true;
                $img.css({opacity: 0});
                $img.animate({opacity: 1});
            }
            placeholder_url = img.src || '';
            last_width = $img.width() + 10;
        });

        var is_visible = false;
        that.calculate_is_visible = function () {
            if (!is_visible) {
                is_visible = $img.is(':visible');
            }
        };

        // image status
        var on_screen = false;
        that.calculate_on_screen = function () {
            var top, bottom;
            if (!on_screen && is_visible) {
                top = $img.offset().top;
                bottom = top + $img.height();
                on_screen = (bottom >= viewport.top) && (top <= viewport.bottom);
            }
        };

        var needs_upscale = false;
        that.calculate_needs_upscale = function () {
            if (!needs_upscale) {
                needs_upscale = $img.width() > last_width;
            }
        };

        var get_img_src = function () {
            var p = pretty_urls(),
                a = aspect(),
                s = strategy(),
                w = $img.width() + 50,
                i = check_scheme($img.data('src')),
                b = base_url(),
                uri;
            if (!b) {
                return i;
            }
            if (p) {
                uri = 'img/' + s + '/' + a + '/' + w + '/' + i;
            } else {
                uri = {strategy: s, aspect: a, width: w, image: i};
                uri = 'img.php?' + $.param(uri);
            }
            return b + uri;
        };

        var has_loaded = false;
        that.do_load = function () {
            if (!on_screen || !is_visible) {
                return;
            }
            if (has_loaded && !needs_upscale) {
                return;
            }
            on_screen = false;
            img.src = get_img_src();
        };
        return that;
    };

    //------------------------------------------------------------------------//
    //-- Manage all images                                                  --//
    //------------------------------------------------------------------------//

    /** @var hash table all attached images, format {uid: LazyLoadImage} */
    var lazyload_images = {};

    /**
     * Loop over all attached images and run a callback if they're not in an
     * error state
     *
     * @param callback the callback to run, will receive the LazyLoadImage object
     * @return undefined
     */
    var loop_images = function (callback) {
        each(lazyload_images, function (lli) {
            if (!lli.is_error_state) {
                callback(lli);
            }
        });
    };

    /**
     * Attach the lazyloader to image(s)
     *
     * @param mixed images the image DOM object, a jQuery collection of images
     * or the css selector for the images
     * @return undefined
     */
    var attach_images = function (images) {
        if (!is_jquery(images)) {
            images = $(images);
        }
        each(images.filter('img:not(.lazy-load-attached)'), function (image) {
            var lli = new LazyLoadImage(image);
            $(image).addClass('lazy-load-attached');
            lazyload_images[lli.uid] = lli;
            lli.calculate_is_visible();
            lli.calculate_on_screen();
            lli.do_load();
        });
    };

    //------------------------------------------------------------------------//
    //-- Bootstrapping                                                      --//
    //------------------------------------------------------------------------//
    $(function () {
        var w = $(window);
        // images with lazy-load class are automatically attached
        attach_images('img.lazy-load');

        // on scrolling, check for images that have come into view
        stack_event(w, 'scroll', 150, function () {
            loop_images(function (lli) {
                lli.calculate_is_visible();
                lli.calculate_on_screen();
                lli.do_load();
            });
        });

        // on resize, check for images that are now visible or now in view
        stack_event(w, 'resize', 150, function () {
            loop_images(function (lli) {
                lli.calculate_on_screen();
                lli.calculate_is_visible();
                lli.calculate_needs_upscale();
                lli.do_load();
            });
        });

        // poll for images created after document load
        setInterval(function () {
            attach_images('img.lazy-load');
        }, 2500);
    });

}(window, jQuery));
