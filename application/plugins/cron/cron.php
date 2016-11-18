<?php

namespace VSAC;

// timer
$start = microtime(true);

// send a standard formatted json response
$respond = function ($response) use ($start) {
    $response = array_merge(array(
        'error'  => '',
        'status' => ''
    ), $response);
    $response['time'] = microtime(true) - $start;
    response_send_json($response);
};

// collect configuration data
$safety = config('cron_safety', 0);
$jobs = array();
foreach (config('cron_jobs', array()) as $job) {
    $jobs[md5($job)] = cron_parse_job_entry($job);
}

// validate api key
if (!apikey_is_valid()) {
    $respond(['error' => 'Invalid API Key']);
}

// this is a sub-job request call
$is_single = call_user_func(function () use ($jobs, $safety) {
    if (!($job = request_query('run_job'))) {
        return false;
    }
    log_set_id(request_query('log_id', false));

    if (!isset($jobs[$job])) {
        return array('error' => 'Invalid Job ID');
    }
    if (kval_get($job . '_run', $safety)) {
        return array('status' => 'Job Throttled');
    }
    kval_set($job . '_run', true);
    $job = $jobs[$job];
    log_log('Running Job <%s>', $job['orig']);

    // blocking, but we don't care about the response
    http_get($job['url'], false, $job['curl_options']);
    log_log('Ran job     <%s>', $job['orig']);
    return array('status' => 'Ran job: ' . $job['orig']);
});
if (is_array($is_single)) {
    $respond($is_single);
}

// top level call (from cron itself, dispatch jobs)
$status = call_user_func(function () use ($jobs, $safety, $start) {

    $log_id = log_log('Startup cron run');
    $jobs = array_filter($jobs, function ($job) use ($start) {
        return cron_is_scheduled($job, $start);
    });

    if (empty($jobs)) {
        return 'No jobs to run'; 
    }

    $statuses = array();
    foreach ($jobs as $md5 => $job) {
        if (kval_get($md5 . '_dispatch', $safety)) {
            $statuses[] = 'Job Throttled: ' . $job['orig'];
        } else {
            kval_set($md5 . '_dispatch', true);
            // if the job doesn't have any options, fire directly and forget
            if (empty($job['curl_options'])) {
                kval_set($md5 . '_run', true);
                http_forget($job['url']);
                log_log('Ran job     <%s>', $job['orig']);
            // if it does have options, fire indirectly and forget
            } else {
                $url = router_add_query(
                    router_plugin_url('cron.php', true),
                    array(
                        'api_key' => config('api_key', ''),
                        'run_job' => $md5,
                        'log_id'  => $log_id,
                    )
                );
                log_log('Dispatching <%s>', $job['orig']);
                http_forget($url);
            }
            $statuses[] = 'Job Dispatched: ' . $job['url'];
        }
    }
    return implode(' +++ ', $statuses);
});

$respond(['status' => $status]);
















