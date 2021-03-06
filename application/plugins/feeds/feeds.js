/*jslint browser */
/*global window, jQuery*/

(function (global) {
    'use strict';
    // get the base url
    var base_url = (function () {
        var url = '';
        var calculate = function () {
            var regex = /(.*\/)feeds\/feeds(-min)?\.js(\?.*)?$/;
            $('script').each(function () {
                if (this.src && regex.test(this.src)) {
                    url = this.src.match(regex)[1];
                    return false;
                }
            });
            return url;
        };
        return function () {
            return url || calculate();
        };
    }());


    global.VSAC = global.VSAC || {};
    global.VSAC.feeds = function (feed_url, callback, count, fields, strip_tags) {
        var data = {feed: feed_url};
        if (count) {
            data.count = count;
        }
        if (fields) {
            data.fields = fields.join(',');
        }
        if (undefined !== strip_tags) {
            data.strip_tags = strip_tags;
        }
        $.ajax({
            url: base_url() + 'feeds/feed.php',
            jsonp: "callback",
            dataType: "jsonp",
            data: data,
            success: callback
        });    
    }
    // backwards compatability with an earlier naming convention
    global.VGMH = global.VGMH || {};
    global.VGMH.feeds = global.VSAC.feeds;

}(window));
