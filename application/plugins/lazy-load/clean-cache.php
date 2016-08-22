<?php

// An earlier version of this API used occasional javascript callbacks to
// clean the cache. This file exists to ensure backwards compatability with
// versions of the javascript that may be cached by users. 

namespace VSAC;

require_once __DIR__.'/../../framework.php';
bootstrap(__FILE__);
lazy_load_bootstrap();

response_send_json(cal_clean());





