<?php

namespace VSAC;

$url = router_plugin_url('feeds-min.js', true);

?><script src="<?= $url ?>"></script>
<div id='example-container'>
  <p>Below you should see a list of the latest five links
      from feedforall.com's sample feed:</p>
</div>
<script>(function () {
  var feed_url = 'http://www.feedforall.com/sample.xml',
      callback = function (links) {
          console.log(links);
          var ul = $('<ul />'), li, a, i;
          for (i = 0; i < links.length; i += 1) {
              li = $('<li />');
              a = $('<a />');
              a.attr('href', links[i].link);
              a.text(links[i].title);
              li.text(': ' + links[i].description);
              li.prepend(a);
              ul.append(li);
          }
          $('#example-container').append(ul);
      },
      fields = ['link', 'title', 'description'],
      count = 5;
  window.VSAC.feeds(feed_url, callback, count, fields);
}());</script>
