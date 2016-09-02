<?php

namespace VSAC;


/**
 * Get the share URLs for various services.
 *
 * @param string $service one of "facebook", "linkedin", "gplus", "whatsapp", 
 * "email", "twitter"
 * @param string $url the url to share, will be converted to short url
 * @param string $title the title of the piece, for generating share texts
 *
 * @return string the share url
 */
function social_get_share_url($service, $url, $title)
{

    $links = array();
    $surl = shortener_shorten($url);
    if ($surl != $url) {
        $url = $surl;
        callmap_log($url);
    }

    switch ($service) {
        case 'facebook':
            $base = 'https://www.facebook.com/sharer/sharer.php';
            $query = array('u' => $url, 't' => $title);
            break;
        case 'linkedin':
            $base = 'https://www.linkedin.com/shareArticle';
            $query = array('url'=> $url, 'title'=>$title);
            break;
        case 'gplus':
            $base = 'https://plus.google.com/share';
            $query = array('url'=>$url);
            break;
        case 'whatsapp':
            $base = 'whatsapp://send';
            $query = array('text'=> $title . ' - ' . $url);
            break;
        case 'email':
            $base = 'mailto:';
            $query = array('subject'=> $title, 'body' => $title . "\n" . $url);
            break;
        case 'twitter': 
            $search_replace = config('twitter_search_replace', array());
            foreach ($search_replace as $search => $replace) {
                $title = str_replace($search, $replace, $title);
            }
            $base = 'https://twitter.com/intent/tweet';
            $query = array('url'=>$url, 'text'=>$title);
            break;
        default:
            return '';
    }
    return router_add_query($base, $query);
}


/**
 * Get the share count for a url on a  service
 *
 * @param string $service the service to fetch, only "linkedin" and "gplus" work,
 * the others return 0. See facebook.php for handling facebook shares.
 * @param string $url the URL that was shared
 *
 * @return integer
 */
function social_get_share_count($service, $url)
{
    switch ($service) {
        case 'linkedin':
            $get = router_add_query(
                'https://www.linkedin.com/countserv/count/share',
                array('url' =>  $url, 'format' => 'json')
            );
            callmap_log($get);
            $response = http_get($get);
            $body = json_decode($response['body'], true);
            return empty($body['count']) ? 0 : (int) $body['count'];
        case 'gplus':
            $get = router_add_query(
                'https://plusone.google.com/_/+1/fastbutton',
                array('url' => $url)
            );
            callmap_log($get);
            $response = http_get($get);
            $regex = '/id="aggregateCount"[^>]*>([^<]+)</';
            if (preg_match($regex, $response['body'], $matches)) {
                $count = strtolower($matches[1]);
                $count = str_replace(['k', 'm'], ['000', '000000'], $count);
                $count = preg_replace('/[^\d]/', '', $count);
                return (int) $count;
            }
            return 0;
        default:
            return 0;
    }
}


/**
 * Build the button objects.
 *
 * @return array format array(
 *     'service_name' => array(
 *         'link'   => [string] the css class to add to the share link
 *         'icon'   => [string] the font awesome classes for the link icon
 *         'label'  => [string] the text to label the button with
 *     )
 * );
 */
function social_build_buttons()
{
    $icon_classes = array(
        'facebook' => 'fa fa-facebook',
        'linkedin' => 'fa fa-linkedin',
        'gplus'    => 'fa fa-google-plus',
        'twitter'  => 'fa fa-twitter',
        'whatsapp' => 'fa fa-whatsapp',
        'email'    => 'fa fa-envelope',
    );
    $link_classes = array(
        'facebook' => 'vsac-social-facebook',
        'linkedin' => 'vsac-social-linkedin',
        'gplus'    => 'vsac-social-google',
        'twitter'  => 'vsac-social-twitter',
        'whatsapp' => 'vsac-social-whatsapp hidden-lg hidden-md',
        'email'    => 'vsac-social-email',
    );
    $labels = array(
        'facebook' => 'Facebook',
        'linkedin' => 'LinkedIn',
        'gplus'    => 'Google +',
        'twitter'  => 'Twitter',
        'whatsapp' => 'WhatsApp',
        'email'    => 'Email',
    );
    $buttons = array();
    foreach (array_keys($icon_classes) as $service) {
        $buttons[$service] = array(
            'link'    => $link_classes[$service],
            'icon'    => $icon_classes[$service],
            'label'   => $labels[$service],
        );
    }
    return $buttons;
}



/**
 * Get the share buttons
 *
 * @param string $url the url to share
 * @param string $title the title for building share texts
 */
function get_buttons($url, $title)
{
    return cal_get_permutation(
        $url,
        function () use ($url) {
            $buttons = social_build_buttons();
            foreach (array_keys($buttons) as $service) {
                $buttons[$service]['shares'] = social_get_share_count($service, $url);
            }
            return $buttons;
        },
        $title,
        function ($buttons) use ($url, $title) {
            foreach (array_keys($buttons) as $service) {
                $buttons[$service]['url'] = social_get_share_url($service, $url, $title);
            }
            return $buttons;
        }
    );
}

// not an authorized URL. Go away.
if (!($url = request_query('url'))) {
    response_send_error(400, 'URL not specified');
}
if (!($url = http_uri_is_authorized($url))) {
    response_send_error(400, 'Invalid or unauthorized URL');
}

$title = request_query('title', '');

response_send_json(get_buttons($url, $title));

