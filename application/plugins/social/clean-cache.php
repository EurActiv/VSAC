<?php

/**
 * Clean the plugin cache, should be run every hour or so
 */

namespace VSAC;

response_send_json(array(
    'cal'=> cal_clean(),
    'kval' => kval_clean(),
));





