<?php

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework functions                                                    --//
//----------------------------------------------------------------------------//

/** @see plugins/example-plugin/example_plugin_config_items() */
function feeds_config_items()
{
    return array(
        [
            'full_content_feeds',
            [],
            'The feeds to convert into full content. Format is an array of arrays,
            where each entry contains the offsets:<ul>
                <li><code>handle</code> (string): an internal identifier for the feed</li>
                <li><code>url</code> (string): the url to the feed
                <li><code>xpath</code> (string): the XPath selector to use when
                    scraping content from the linked items</li>
                <li><code>feed_curl_options</code> (array, optional): additional cURL
                    options to use when fetching the feed, such as Digest authentication
                    headers</li>
                <li><code>feed_curl_option</code> (array, optional): additional cURL
                    options to use when fetching items linked in the feed</li>
            </ul>',
            true
        ],
    );
}

/** @see plugins/example-plugin/example_plugin_bootstrap() */
function feeds_bootstrap()
{
    use_module('cal');
    use_module('http');
    use_module('apikey');
}

//----------------------------------------------------------------------------//
//-- Plugin functions                                                       --//
//----------------------------------------------------------------------------//

