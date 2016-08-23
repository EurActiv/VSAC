/*jslint browser, this, multivar, single */
/*global jQuery, window */

(function (global, $) {
    'use strict';

    var find_api_base_url = function () {
        var url = false, regex = /(.*\/)vsac-social(-min)?\.js(\?.*)?$/;
        $('script').each(function () {
            var src = this.src;
            if (src && regex.test(src)) {
                url = src.match(regex)[1];
                return false;
            }
        });
        if (url && url.substring(0, 2) === '//') {
            url = global.location.protocol + url;
        }
        return url;
    };

    var dbg = function (msg) {
        global.console.log(msg);
    };

    var cache = (function () {
        var cached = {};
        var normalize_key = function (key) {
            return key.replace(/[^a-z]/g, '_');
        };

        var get = function (key, is_cached_cb, not_cached_cb) {
            key = normalize_key(key);
            if (cached.hasOwnProperty(key)) {
                is_cached_cb(cached[key]);
            } else {
                not_cached_cb();
            }
        };
        var set = function (key, value) {
            key = normalize_key(key);
            cached[key] = value;
        };
        return {get: get, set: set};
    }());

    // make a jsonp callback to the server
    var jsonp = function (url, data, success, error_data) {
        if (!url.match(/^https?:\/\//)) {
            url = find_api_base_url() + url;
        }
        $.ajax({
            url: url,
            jsonp: "callback",
            dataType: "jsonp",
            data: data,
            cache: true,
            success: success,
            error: function (jqXHR, textStatus, errorThrown) {
                dbg('social error: ' + url + '?' + $.param(data));
                dbg([jqXHR, textStatus, errorThrown]);
                success(error_data || {});
            }
        });
    };

    // format numbers for display
    var format_num = function (num) {
        num = parseInt(num, 10);
        if (isNaN(num)) {
            return '';
        }
        if (num >= 1000000) {
            num = Math.round(num / 1000000);
            return num + 'M';
        }
        if (num >= 1000) {
            num = Math.round(num / 1000);
            return num + 'K';
        }
        if (num > 0) {
            return num + '';
        }
        return '';
    };

    // default button formatter
    var create_button = function (service) {
        var link = $('<a />'),
            icon = $('<i />'),
            label = $('<span />'),
            count = $('<b />');
        link.attr('href', service.url)
            .attr('target', '_blank')
            .addClass(service.link + ' btn btn-sm');
        icon.addClass(service.icon)
            .appendTo(link);
        label.addClass('hidden-xs hidden-sm')
            .text(service.label)
            .appendTo(link);
        service.shares = format_num(service.shares);
        if (service.shares) {
            count.text(service.shares)
                .addClass('hidden-xs')
                .appendTo(link);
        }
        return link;
    };

    // fetch the facebook share count, see facebook.php
    var facebook = (function () {

        // store shares to the server
        var store = function (url, key, shares) {
            jsonp('facebook.php', {
                url: url,
                key: key,
                shares: shares
            }, function (response) {
                if (response.error) {
                    dbg('Error storing facebook shares: ' + response.error);
                } else if (response.cached) {
                    dbg('Shares already stored ' + response.shares);
                } else {
                    dbg('Stored Facebook shares ' + response.shares);
                }
            }, {error: 'server error'});
        };

        // fetch facebook count from facebook, store to server
        var graph = function (url, key, callback) {
            jsonp('https://graph.facebook.com/', {
                id: url
            }, function (response) {
                var shares = 0;
                if (response.hasOwnProperty('shares')) {
                    shares = parseInt(response.shares || 0, 10);
                } else if (response.hasOwnProperty('share')) {
                    shares = parseInt(response.share.share_count || 0, 10);
                }
                cache.set('facebook_' + url, shares);
                callback(shares);
                if (undefined !== key && response.id === url) {
                    store(url, key, shares);
                }
            });
        };

        return function (url, callback) {
            cache.get('facebook_' + url, callback, function () {
                jsonp('facebook.php', {url: url}, function (response) {
                    if (response.hasOwnProperty('shares')) {
                        cache.set('facebook_' + url, response.shares);
                        callback(response.shares);
                        return;
                    }
                    graph(url, response.key, callback);
                });
            });
        };
    }());

    // fetch the share buttons from the server
    var fetch_buttons = function (url, title, callback) {
        var key = url + '__' + title;
        cache.get(key, callback, function () {
            jsonp('buttons.php', {
                url: url,
                title: title
            }, function (response) {
                cache.set(key, response);
                callback(response);
            });
        });
    };

    // fetch everything
    var fetch = function (url, title, callback) {
        fetch_buttons(url, title, function (buttons) {
            facebook(url, function (shares) {
                buttons.facebook.shares = shares;
                callback(buttons);
            });
        });
    };

    // the queue of share buttons to load
    var queue = [];
    // load enqueued share buttons
    var process_queue = function process() {
        var container, url, title;
        if (!queue.length) {
            return;
        }
        container = $(queue.shift());
        url = container.data('social-url') || global.location.href;
        title = container.data('social-title') || $('head title').first().text();
        fetch(url, title, function (buttons) {
            $.each(buttons, function () {
                container.append(create_button(this));
                container.append(global.document.createTextNode(' '));
            });
            process();
        });
    };


    global.VSAC = global.VSAC || {};
    global.VSAC.social = function (selector) {
        var launch_process = queue.length < 1;
        queue = queue.concat($(selector).get());
        if (launch_process) {
            process_queue();
        }
    };


    global.VSAC.social.btn_formatter = (function () {
        var default_cbtn;
        return function (fn) {
            if (!default_cbtn) {
                default_cbtn = create_button;
            }
            if (fn) {
                create_button = fn;
            } else {
                create_button = default_cbtn;
            }
        };
    }());

    // load the stylesheet
    $('<link />', {
        rel: "stylesheet",
        type: "text/css",
        href: find_api_base_url() + "vsac-social-min.css",
        media: 'none',
        onload: "if(media!='all')media='all'"
    }).appendTo("head");


    // bootstrap on .vsac-social by default
    $(function () {
        global.VSAC.social('.vsac-social');
    });

}(window, jQuery));
