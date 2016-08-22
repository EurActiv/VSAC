<?php

namespace VSAC;

$aspects = config('aspect_ratios', array());
$aspect = request_query('aspect');
if (!$aspect) {
    $path = request_query('path');
    if (preg_match('/^(\d+x\d+)/', $path, $matches)) {
        $aspect = $matches[1];
    }
}
if (!$aspect || !in_array($aspect, $aspects)) {
    $aspect = array_shift($aspects);
}

$aspect = lazy_load_parse_aspect_ratio($aspect);

lazy_load_placeholder($aspect);




