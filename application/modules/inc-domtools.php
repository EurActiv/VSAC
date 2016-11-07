<?php

/**
 * This module provides some tools for working with (x|ht)ml documents.
 */

namespace VSAC;

//---------------------------------------------------------------------------//
//-- Framework required functions                                          --//
//---------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function domtools_depends()
{
    return array('http', 'image', 'request');
}

/** @see example_module_sysconfig() */
function domtools_sysconfig()
{
    if (!class_exists('\tidy')) {
        return 'tidy not installed';
    }
    return true;
}

/** @see example_module_config_options() */
function domtools_config_options()
{
    return array();
}

/** @see example_module_test() */
function domtools_test()
{
    $html = '<div>';
    $line = '<p class="p-%s p-%d">'
          . '<span id="ps-%d">ps%d</span>'
          . '</p>'
          . '<span id="ds-%d">ds%d</span>';
    for ($i = 0; $i < 10; $i += 1) {
        $html .= sprintf($line, $i%2?'odd':'even', $i, $i, $i, $i, $i);
    }
    domtools_load($html);
    $test_xpath = function ($query, $expected) {
        $result = array();
        domtools_loop($query, function ($el) use (&$result) {
            $result[] = trim($el->nodeValue);
        });
        $result = implode(' ', $result);
        if ($result == $expected) {
            return true;
        }
        $xpath = domtools_selectors_to_xpaths($query);
        $msg = 'Query: "%s" (xpath: %s); expected: "%s", got: "%s"';
        return sprintf($msg, $query, $xpath, $expected, $result);
    };
    $tests = [
        'p'                        => 'ps0 ps1 ps2 ps3 ps4 ps5 ps6 ps7 ps8 ps9',
        '#ps-3'                    => 'ps3',
        'p span:not(#ps-3)'        => 'ps0 ps1 ps2 ps4 ps5 ps6 ps7 ps8 ps9',
        '.p-odd'                   => 'ps1 ps3 ps5 ps7 ps9',
        '.p-odd, .p-even'          => 'ps0 ps1 ps2 ps3 ps4 ps5 ps6 ps7 ps8 ps9',
        'div p'                    => 'ps0 ps1 ps2 ps3 ps4 ps5 ps6 ps7 ps8 ps9',
        '.p-3 + span'              => 'ds3',
        '.p-3 ~ span'              => 'ds2',
        'div > span'               => 'ds0 ds1 ds2 ds3 ds4 ds5 ds6 ds7 ds8 ds9',
        'div > :first-child'       => 'ps0',
        'div p:not(:first-child)'  => 'ps1 ps2 ps3 ps4 ps5 ps6 ps7 ps8 ps9',
        'div > :last-child'        => 'ds9',
        'div > :nth-child(3)'      => 'ps1',
        'div > :nth-last-child(3)' => 'ds8',
    ];
    foreach ($tests as $selector => $node_value) {
        if (true !== ($result = $test_xpath($selector, $node_value))) {
            return $result;
        }
    }
    return true;
}


//---------------------------------------------------------------------------//
//--  Public API                                                           --//
//---------------------------------------------------------------------------//

/**
 * Load a DOM document from a URL
 *
 * @param string $url the URL to load from
 *
 * @return bool true if it loaded, or false
 */
function domtools_load_url($url)
{
    $response = http_get($url);
    if ($response['error']) {
        return false;
    }
    $mime = empty($response['headers']['Content-Type'])
          ? 'text/html'
          : $response['headers']['Content-Type'];
    $is_xml = strpos($mime, 'xml') !== false;
    domtools_load($response['body'], $is_xml);
    domtools_base_url(dirname($url));
    return true;
}

/**
 * Load some markup (xml or html) to manipulate.
 *
 * @param string the markup to load
 * @param bool $is_xml treat as xml instead of html
 *
 * @return void
 */
function domtools_load($markup, $is_xml = false)
{
    domtools_collector($markup, $is_xml);
}

/**
 * Unload the current document
 *
 * @return string the html of the current document
 */
function domtools_unload()
{
    $loaded = domtools_collector();
    $fn = $loaded['is_xml'] ? 'saveXML' : 'saveHTML';
    $ret = $loaded['doc']->$fn();
    domtools_collector(false);
    domtools_base_url(false);
    return $ret;
}

/**
 * Set the url base for the currently loaded document, used for in-lining assets
 * and checking URLs
 *
 * @param string $url
 *
 * @return string
 */
function domtools_base_url($url = null)
{
    static $base_url;
    if ($url) {
        $base_url = $url;
        if (substr($url, -1) != '/') {
            $base_url .= '/';
        }
    } elseif (!is_null($url)) {
        $base_url = null;
    }
    return $base_url;
}

/**
 * Query the loaded document, return matched elements. If the query is a CSS
 * selector, it will be converted to xpath before executing.  The converter
 * covers all of CSS2 except for :link, :visited, :active, :hover, :focus,
 * :lang(c). Covers some common CSS3 selectors: :not(), :nth-child(n),
 * :last-child, :nth-last-child(n)
 *
 * @param string $query the query to run against the document. If it contains a
 * forward slash ('/'-, will be treated as xpath, otherwise it will be passed to$
 * the CSS selector to XPath converter.
 *
 * @return array[\DOMNode]
 */
function domtools_query($query)
{
    if (strpos($query, '/') === false) {
        $query = domtools_selectors_to_xpaths($query);
    }
    $loaded = domtools_collector();
    $elements = $loaded['xpath']->query($query);
    $elements_array = array();
    if (!is_null($elements)) {
        $idx = 0;
        while ($element = $elements->item($idx)) {
            $elements_array[] = $element;
            $idx += 1;
        }
    }
    return $elements_array;
}

/**
 * Run a callback on all matched elements in an array.
 *
 * @param string $query @see domtools_query
 * @param callable callback the callback to run
 *
 * @return array the return values of each iteration of the callback
 */
function domtools_loop($query, callable $callback, $reverse = false)
{
    $return = array();
    $elements = domtools_query($query);
    foreach ($elements as $element) {
        $return[] = call_user_func($callback, $element);
    }
    return $return;
}

/**
 * Get the inner content of the first matched element in a query
 *
 * @param string $query @see domtools_query(), or false for the entire document
 *
 * @return string
 */
function domtools_content($query = false)
{
    $loaded = domtools_collector();
    extract($loaded);
    $save_fn = $is_xml ? 'saveXML' : 'saveHTML';
    if (!$query) {
        return $doc->$save_fn();
    }
    $content = '';
    $elements = domtools_query($query);
    if ($element = array_shift($elements)) {
        foreach ($element->childNodes as $child) { 
            $content .= $doc->$save_fn($child);
        }
    }
    return $content; 
}

/**
 * Set the inner html of an element
 *
 * @param string $query
 * @param string $inner_html the html to set
 */
function domtools_set_content($query, $inner_html)
{
    $elements = domtools_query($query);
    $element = array_shift($elements);
    if (!$element) {
        return false;
    }
    $loaded = domtools_collector();
    $inner_doc = domtools_create_domdoc($inner_html, false);
    $inner_xml = '';
    $inner_children = $inner_doc->getElementsByTagName('body')->item(0)->childNodes;
    foreach ($inner_children as $inner_child) {
        $inner_xml .= $inner_doc->saveXML($inner_child);
    }
    $fragment = $element->ownerDocument->createDocumentFragment();
    $fragment->appendXML($inner_xml);
    $clone = $element->cloneNode();
    $clone->appendChild($fragment);
    $element->parentNode->replaceChild($clone, $element);
    return $clone;
}

/**
 * Rebase relative urls in a document.
 *
 * @param string $query the css/xpath query to match elements to inline. Defaults
 * to any element with an href or src attribute
 *
 * @return void
 */
function domtools_rebase($query = '//*[@href]|//*[@src]', $url_attrs = ['href', 'src'])
{
    domtools_loop($query, function ($el) use ($url_attrs) {
        foreach ($url_attrs as $attr) {
            if ($url = $el->getAttribute($attr)) {
                $fqurl = domtools_fqurl($url);
                if ($fqurl && $fqurl != $url) {
                    $el->setAttribute($attr, $fqurl);
                }
            }
        }
    });
}

/**
 * Inline images, scripts and CSS in the current document.
 *
 * @param string $query the css/xpath query to match elements to inline. Defaults
 * to all images, scripts and linked stylesheets
 * @param int $image_maxw maximum width of inline images, 0 not to resize
 *
 * @return void
 */
function domtools_inline($query = false, $image_maxw = 500)
{
    // only run the modification callback if the http request worked
    $fetch = function ($el, $url_attr, $callback) {
        if (!($url = $el->getAttribute($url_attr))) {
            return;
        }
        $url = domtools_fqurl($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }
        $response = http_get($url);
        if (!empty($response['error'])) {
            return;
        }
        $mime = empty($response['headers']['Content-Type'])
              ? false
              : $response['headers']['Content-Type']
              ;
        $callback($response['body'], $mime);
    };
    if (!$query) {
        $query = '//script[@src]|//link[@rel="stylesheet" and @href]|//img[@src]';
    }
    domtools_loop($query, function ($el) use ($fetch, $image_maxw) {
        switch (strtolower($el->tagName)) {
            case 'script':
                $fetch($el, 'src', function ($content) use ($el) {
                    $el->removeAttribute('src');
                    $cdata = $sel->ownerDocument->createCDATASection($content);
                    $el->appendChild($cdata);
                });
                break;
            case 'link':
                $fetch($el, 'href', function ($content) use ($el) {
                    $p = $el->parentNode;
                    $s = $el->ownerDocument->createElement('style', $content);
                    $p->insertBefore($s, $el);
                    $p->removeChild($el);
                });
                break;
            case 'img':
            default:
                $fetch($el, 'src', function ($content, $mime) use ($el, $image_maxw) {
                    if (strpos($mime, 'image/') === 0 && $image_maxw) {
                        $src = image_data_uri($content, $image_maxw);
                    } else {
                        $src = sprintf('data:%s;base64,%s', $mime, base64_encode($content));
                    }
                    $el->setAttribute('src', $src);
                    if ($el->hasAttribute('srcset')) {
                        $el->removeAttribute('srcset');
                    }
                });
                break;
        }
    });
}

/**
 * Remove elements from the currently loaded document.
 *
 * @param query @see domtools_query
 *
 * @return an array of associative arrays in format ['element' => \DOMElement the
 * element, 'parent' => \DOMElement the parent element, 'next_sibling' \DOMElement
 * the next sibling]
 *
 */
function domtools_remove_elements($query)
{
    $elements = array();
    domtools_loop($query, function ($element) use (&$elements) {
        $next_sibling = $element->nextSibling;
        $parent = $element->parentNode;
        $parent->removeChild($element);
        $elements[] = compact('element', 'parent', 'next_sibling');
    });
    return $elements;
}

/**
 * Add elements to the currently loaded document, basically the reverse of
 * domtools_remove_elements().
 *
 * Three caveats regarding removed elements:
 *
 *   1. if the element parent has been removed the element won't be appended,
 *   2. if the next sibling has been removed, the element might not be in the
 *      right place. 
 *   3. in order to minimize the effects of the above, you might want to run
 *      array_reverse on the elements array before passing it here.
 *
 *
 * @param array $elements An array of arrays as returned by domtools_remove_elements()
 *
 * @return void
 */
function domtools_add_elements($elements)
{
    foreach ($elements as $el) {
        try {
            if ($el['next_sibling']) {
                try {
                    $el['parent']->insertBefore($el['element'], $el['next_sibling']);
                } catch (\Exception $e) {
                    $el['parent']->appendChild($el['element']);
                }
            } else {
                $el['parent']->appendChild($el['element']);
            }
        } catch (\Exception $e) {
            trigger_error('could not append element: ' . $e->getMessage());
        }
    }
}

/**
 * Remove attributes from elements.
 *
 * @param string $match a regular expression for attribute names to match
 * @param string $query elements to remove from
 *
 * @return an array of associative arrays in format ['element' => \DOMElement the
 * element, 'name' => the attribute name, 'value' => the attribute value
 */
function domtools_remove_attributes($match, $query = '//*')
{
    $attributes = array();
    domtools_loop($query, function ($el) use ($match, &$attributes) {
        $remove = array();
        foreach ($element->attributes as $attr) {
            if (preg_match($match, $attr->name())) {
                $remove[] = $attr->name();
            }
        }
        foreach ($remove as $name) {
            $value = $el->getAttribute($name);
            $el->removeAttribute($name);
            $attributes[] = compact('element', 'name', 'value'); 
        }
    });
    return $attributes;
}

/**
 * Add attributes to elements, basically the reverse of domtools_remove_attributes()
 *
 * @param array $attributes an array of arrays matching the format of
 * domtools_remove_attributes()
 *
 * @return void
 */
function domtools_add_attributes($attributes)
{
    foreach ($attributes as $attr) {
        $attr['element']->setAttribute($attr['name'], $attr['value']);
    }
}

/**
 * Sanitize the currently loaded document, removing all code injection
 * possibilities, embeds, stylesheets, meta tags... .  It's pretty aggressive.
 *
 * @return array $removed an associative array with two offsets: 'elements':
 * see domtools_remove_elements(); 'attributes': see domtools_remove_attributes()
 */
function domtools_sanitize()
{
    $remove_attributes = array(
        'archive', 'action', 'background', 'cite', 'classid', 'codebase',
        'data', 'formaction', 'href', 'icon', 'longdesc', 'manifest',
        'poster', 'profile', 'src', 'srcset', 'style', 'usemap', 'on.*',
    );
    $remove_attributes = '/^(' . implode('|', $remove_attributes) . ')$/i';
    $attributes = domtools_remove_attributes($remove_attributes);

    $remove_elements = array(
        // form elements
        'button', 'input', 'textarea', 'select', 'form',
        // embeds
        'source', 'track', 'audio', 'applet', 'embed', 'frame', 'iframe', 'object', 'video',
        // other
         'command', 'link', 'meta', 'script', 'style'
    );
    $remove_elements = '\\' . implode('|\\', $elements);
    $elements = domtools_remove_elements($remove_elements);

    return compact('attributes', 'elements');
}

/**
 * Re-add attributes and elements removed with the domtools_sanitize()
 * function, passing them through a filter first.
 *
 * For example:
 *
 *     $removed = domtools_sanitize();
 *     $removed = domtools_unsanitize(
 *         $removed,
 *         __NAMESPACE__ . '\domtools_greylist_attr',
 *         __NAMESPACE__ . '\domtools_greylist_element',
 *     );
 *
 *

 *
 * @param array $filtered The return of domtools_sanitize()
 * @param callable $filter_attrs The function to filter attributes.  Will
 * receive an array containing three offsets: 'element': the filtered element;
 * 'name': the removed attribute name; 'value': the attribute value. Should
 * return the (potentially modified) array if it is to be added, or null if not.
 * @param callable $filter_elements The function to filter elements. Will receive
 * an array with the offsets: 'element': the removed element, 'parent': the
 * removed element's parent; 'next': the removed element's next sibling. Should
 * return the (potentially modified) array if it is to be added, or null if not.
 *
 * @return the elements that are still removed
 */ 
function domtools_unsanitize(
    $removed,
    callable $filter_attrs,
    callable $filter_elements
) {
    $filter = function ($array, $filter_cb) {
        $add = array();
        $remove = array();
        foreach ($array as $value) {
            if ($v = call_user_func($filter_cb, $value)) {
                $add[] = $v;
            } else {
                $remove[] = $value;
            }
        }
        return array($add, $remove);
    };

    $_removed = array();
    
    $filtered_attributes = $filter($removed['attributes'], $filter_attrs);
    $attributes = array_shift($filtered_attributes);
    $_removed['attributes'] = array_shift($filtered_attributes);

    $filtered_elements = $filter($removed['elements'], $filter_elements);
    $elements = array_shift($filtered_elements);
    $_removed['elements'] = array_shift($filtered_elements);
    
    domtools_add_elements($elements);



    return $_removed;
}



/**
 * A safe-ish filter for domtools_unsanitize::$filter_attrs that re-adds
 * attributes that represent a fairly low threat. Use if the source is
 * basically trustworthy.
 *
 * @param array $attribute
 *
 * @return array|null
 */
function domtools_greylist_attr($attribute)
{
    $tagname = strtolower($attribute['element']->tagName);
    $name = strtolower($attribute['name']);
    $val = $attribute['value'];
    if (in_array($name, ['href', 'cite', 'longdesc'])) {
        if (preg_match('/^\s*javascript\s*:/i', $val)) {
            $attribute['value'] = '#' . uniqid();
        }
        return $attribute;
    }
    if ($name == 'poster') {
        return $attibute;
    }
    $src_tags = array('img', 'video', 'audio', 'source', 'track');
    if ($name == 'src' && in_array($tagname, $src_tags)) {
        return $attribute;
    }
    return null;
}

/**
 * A safe-ish filter for domtools_unsanitize::$filter_elements that re-adds
 * elements that represent a fairly low threat. Use if the source is
 * basically trustworthy.
 *
 * @param array $attribute
 *
 * @return array|null
 */
function domtools_greylist_element($element)
{
    $tags = array('source', 'track', 'audio', 'video');
    $tagname = strtolower($element['element']->tagName);
    if (in_array($tagname, $tags)) {
        return $element;
    }
    return null;
}


//---------------------------------------------------------------------------//
//-- Private methods                                                       --//
//---------------------------------------------------------------------------//

/**
 * A container for the currently loaded document
 *
 * @param false|string $html the document, null to simply retrieve or 
 * false to unload.
 * @param bool $as_xml treat the loaded document as XML
 *
 * @return array['doc'=>\DOMDocument|null, 'xpath'=> \DOMXpath|null]
 */
function domtools_collector($markup = null, $as_xml = false)
{
    static $doc, $xpath, $is_xml;

    if ($markup) {
        $is_xml = (bool) $as_xml;
        $doc = domtools_create_domdoc($markup, $is_xml);
        $xpath = new \DOMXpath($doc);
    } elseif (false === $markup) {
        $doc = $xpath = $is_xml = null;
    } elseif (is_null($doc)) {
        trigger_error('No document loaded', E_USER_ERROR);
    }
    return compact('doc', 'xpath', 'is_xml');
}

function domtools_create_domdoc($markup, $is_xml)
{
    $config = $is_xml
            ? array('add-xml-decl'=>true, 'output-xml' => true)
            : array(
                'output-html'           => true,
                'new-blocklevel-tags'   => 'menu,article,header,footer,section,nav',
                'new-inline-tags'       => 'video,audio,canvas,ruby,rt,rp',
            );
    $domdoc_fn = $is_xml ? 'loadXML' : 'loadHTML';

    $encoding = mb_detect_encoding($markup);    
    $markup = htmlspecialchars_decode(@htmlentities(
        $markup,
        ENT_COMPAT|ENT_HTML401,
        $encoding == 'ASCII' ? 'utf-8' : $encoding
    ));
    $tidy = tidy_parse_string($markup, $config);
    $tidy->cleanRepair();
    $doc = new \DOMDocument();
    $doc->$domdoc_fn($tidy);
    return $doc;
}

/**
 * Convert a relative URL in a document to a fully qualified one based on the
 * documents base url
 */
function domtools_fqurl($uri)
{
    if (strpos($uri, '#') === 0) {
        return $uri;
    }
    return router_rebase_url($uri, domtools_base_url(), true);
}

/**
 * Converts css selectors to xpath queries. Covers all of CSS2 except for :link,
 * :visited, :active, :hover, :focus, :lang(c). Covers some common CSS3
 * selectors: :not(), :nth-child(n), :last-child, :nth-last-child(n)
 *
 * @param string $selector The CSS selector
 *
 * @return string the xpath selector
 */
function domtools_selectors_to_xpaths($selector)
{
    // multiple selectors
    if (strpos($selector, ',') !== false) {
        return implode('|', array_map(
            __NAMESPACE__ . '\domtools_selector_to_xpath',
            explode(',', $selector)
        ));
    }
    // single selector
    return domtools_selector_to_xpath($selector);
}

function domtools_selector_to_xpath($selector)
{
    // initial normalization
    $selector = preg_replace('/\s+/', ' ', $selector);
    $selector = preg_replace('/ ?([>\+~]) ?/', '$1', $selector);
    $selector = ' ' . trim($selector);

    // prevent collision between [att~=val] and E~F
    $selector = str_replace('~=', '°=', $selector);
    return preg_replace_callback(
        // breaks into individual node selectors 
        '/([ >\+~])([^ >\+~]*)/',
        function ($matches) {
            $prefix = str_replace(
                [' ',  '>', '+',                               '~'                              ],
                ['//', '/', '/following-sibling::*[1]/self::', '/preceding-sibling::*[1]/self::'],
                $matches[1]
            );
            $tag = preg_split('/[:\[\.#]/', $matches[2], 2);
            if ($tag = array_shift($tag)) {
                $attrs = substr($matches[2], strlen($tag));
            } else {
                $tag = '*';
                $attrs = $matches[2];
            }
            $attrs = str_replace('°=', '~=', $attrs); // reverse collision prevention
            $predicates = domtools_css_pseudo_class_to_predicate($attrs);
            $predicates .= domtools_css_attr_to_xpath_predicate($attrs);
            return $prefix . $tag . $predicates;
        },
        $selector
    );
}

/**
 * Convert css attribute selectors (id="", class="", [att=val]) to the
 * equivalent xpath predicate ([ @att="val" ]). Will leave pseudo selectors
 * untouched.
 *
 * @param string $attr the string of css attributes (eg, #my-id.my-class[href])
 *
 * @return string the attributes with xpath predicates inserted:
 */
function domtools_css_attr_to_xpath_predicate($attrs)
{
    $attrs = preg_replace('/#([^:\[\.#]*)/', '[id="$1"]', $attrs);
    $attrs = preg_replace('/\.([^:\[\.#]*)/', '[class~="$1"]', $attrs);
    $attrs = str_replace('[', '[[', $attrs);
    $attrs = preg_replace('/:not\( *\[\[([^\)]+)\)/', '![$1', $attrs);
    $predicates = '';
    $attrs = preg_replace_callback(
        // regex should turn [att=val] to array($neg, $att, $comp, $val)
        '/(\[\[|!\[) *([a-z_:][a-z0-9_:\.\-]*)([\|~\^\$\*]?=)?([^\]]*)?\]( *\))?/',
        function ($match) use (&$predicates) {
            $orig = array_shift($match);
            $neg = array_shift($match) == '![';
            $att = trim(array_shift($match));
            $comp = (string) array_shift($match);
            $val = trim(trim((string) array_shift($match)), '\'"');
            switch ($comp) {
                case '':
                    $predicate = sprintf('[ @%s ]', $att);
                    break;
                case '|=':
                    $str = '[@%s="%s" or starts-with(@%s, concat("%s", "-"))]';
                    $predicate = sprintf($str, $att, $val, $att, $val);
                    break;
                case '~=':
                    $str = '[contains(concat(" ",@%s," "), concat(" ","%s"," "))]';
                    $predicate = sprintf($str, $att, $val);
                    break;
                 case '^=':
                    $str = '[@%s="%s" or starts-with(@%s, "%s")]';
                    $predicate = sprintf($str, $att, $val, $att, $val);
                    break;
                 case '$=':
                    $str = '[@%s="%s" or ends-with(@%s, "%s")]';
                    $predicate = sprintf($str, $att, $val, $att, $val);
                    break;
                 case '*=':
                    $str = '[contains(@%s, "%s")]';
                    $predicate = sprintf($str, $att, $val);
                    break;
                 case '=':
                 default:
                     $str = '[ @%s = "%s" ]';
                     $predicate = sprintf($str, $att, $val);
                     break;
            }
            if ($neg) {
              $predicate = '[not(' . substr($predicate, 1, -1) . ')]';
            }
            $predicates .= $predicate;
            return '';
        },
        $attrs
    );
    return $predicates;
}

/**
 * Extract pseudo selectors from the css selector attributes (eg, :first-child
 * in .my-class:first-child) and convert them to a prefix for the xpath tag
 * selector. Supports first-child, nth-child, last-child, last-nth-child.
 *
 * @param string $attrs
 * @return string
 */
function domtools_css_pseudo_class_to_predicate(&$attrs)
{
    $expr = [];
    // normalize out first- and last-child
    $attrs = str_replace(
        [':first-child',  ':last-child'],
        [':nth-child(1)', ':nth-last-child(1)'],
        $attrs
    );
    // wrapped in :not()
    $attrs = preg_replace(
         '/:not\( *:(nth\-last\-child|nth\-child)(\( *\d+ *\))/',
         '!$1$2',
         $attrs
    );

    $attrs = preg_replace_callback(
        '/([:\!])(nth\-child|nth\-last\-child)\( *([\d]+) *\)/',
        function ($match) use (&$expr) {
            $sel = $match[1] . $match[2];
            $idx = $match[3];
            switch($sel) {
                case ':nth-child':
                    $expr[] = 'position() = ' . $idx;
                    break;
                case '!nth-child':
                    $expr[] = 'not(position() = ' . $idx . ')';
                    break;
                case ':nth-last-child':
                    $expr[] = 'position() = last()-' . ($idx - 1);
                    break;
                case '!nth-last-child':
                    $expr[] = 'not(position() = last()-' . ($idx - 1) . ')';
                    break;
            }
        },
        $attrs
    );
    if (!empty($expr)) {
        return '[ ' . implode(' and ', $expr)  . ' ]';
    }
    return '';
}

