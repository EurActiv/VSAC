/*jslint browser */
/*global window, jQuery*/

(function (global) {
    'use strict';

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
            url: '//assets.euractiv.com/feeds/feed.php', // TODO: remove hard-coded url
            jsonp: "callback",
            dataType: "jsonp",
            data: data,
            success: callback
        });    
    }

}(window));
