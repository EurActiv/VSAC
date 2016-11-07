<?php

$config = array(

    // cache layer requires different default settings than rest of 
    // application due to the size this will reach
    'cal_ttl'        => (int) (60 * 60 * 6),
    'cal_driver'     => 'fsstore',

    // plugin-specific config
    'aspect_ratios'     => array('16x9', '4x3', '3x2', '1x1'),

);




