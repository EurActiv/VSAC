<?php

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework functions                                                    --//
//----------------------------------------------------------------------------//

/** @see plugins/example-plugin/example_plugin_config_items() */
function cron_config_items()
{
    return array(
        [
            'cron_jobs',
            array(),
            'Jobs to run'
        ]
    );
}

/** @see plugins/example-plugin/example_plugin_bootstrap() */
function cron_bootstrap()
{
    use_module('apikey');
    use_module('kval');
    use_module('log');
    use_module('http');
}

//----------------------------------------------------------------------------//
//-- Plugin functions                                                       --//
//----------------------------------------------------------------------------//

/**
 * Parse an entry in the cron file. If the entry cannot be parsed, returns a
 * string with the error. If the entry is successfully parsed, will return an
 * array with the format:
 *
 *     array(
 *         'orig' => '* 0,6,12,18 * * * callback.php {"CURLOPT_USERPWD": "user:pass"}'
 *         'url' => 'http://example.com/callback.php',
 *         'curl_options' => array(
 *             10005 => 'user:pass',
 *         ),
 *         'schedule'=> array(
 *             'minute' => array('*'),
 *             'hour'   => array('0', '6', '12', '18'),
 *             'date'   => array('*'),
 *             'month'  => array('*'),
 *             'day'    => array('*')
 *         ),
 *     );
 *
 * @param string $entry the entry from the config file
 *
 * @return string|array
 */
function cron_parse_job_entry($entry)
{
    $orig = $entry;
    $entry = explode(' ', $entry, 7);
    if (count($entry) < 6) {
        return 'malformatted';
    }
    $curl_options = array();
    $curl_opts = count($entry) == 7 ? array_pop($entry) : array();
    if ($curl_opts) {
        $curl_opts = json_decode($curl_opts, true);
        if (!is_array($curl_opts)) {
            return 'Malformated cURL options';
        }
        foreach ($curl_opts as $key => $value) {
            if (defined($key)) {
                $curl_options[constant($key)] = $value;
            }
        }
    }

    $url = array_pop($entry);
    if (strpos($url, '//') === 0) {
        $url = request_scheme() . $url;
    } elseif (strpos($url, '/') === 0) {
        $url = request_scheme() . '//' . request_host() . $url;
    } elseif (!preg_match('#^[a-z\-]+\://#', $url)) {
        $url = router_base_url(true) . $url;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return 'Invalid job URL';
    }

    $entry = array_map(function ($value) {
        return array_map('strval', explode(',', $value));
    }, $entry);
    
    list($minute, $hour, $date, $month, $day) = $entry;
    $schedule = compact('minute', 'hour', 'date', 'month', 'day');

    return compact('orig', 'url', 'curl_options', 'schedule');
}


/**
 * Check if a job is scheduled to run at a given timestamp
 *
 * @param array $job the job, as parsed by cron_parse_job_entry()
 * @param int $timestamp the timestamp to use
 *
 * @return bool
 */
function cron_is_scheduled($job, $timestamp)
{
    if (!is_array($job) || empty($job['schedule']) || !is_array($job['schedule'])) {
        return false;
    }
    extract($job['schedule'], EXTR_SKIP);

    $_minute = strval(intval(date('i', $timestamp)));
    if (!in_array('*', $minute) && !in_array($_minute, $minute)) {
        return false;
    }

    $_hour = date('G', $timestamp);
    if (!in_array('*', $hour) && !in_array($_hour, $hour)) {
        return false;
    }

    $_date = date('j', $timestamp);
    if (!in_array('*', $date) && !in_array($_date, $date)) {
        return false;
    }

    $_month = date('n', $timestamp);
    if (!in_array('*', $month) && !in_array($_month, $month)) {
        return false;
    }

    $_day = date('N', $timestamp);
    if (!in_array('*', $day) && !in_array($_day, $day)) {
        return false;
    }

    return true;
}
