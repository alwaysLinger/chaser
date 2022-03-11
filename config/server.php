<?php

return [
    'tcp' => [
        'host' => '0.0.0.0',
        'port' => 9527,

        'worker_num' => 2,

        // 'package_eof_check'     => false,
        // 'package_eof'           => '\n',

        'package_length_check' => true,
        'package_max_length'   => 81920,
        'package_length_type'  => 'n',
        // 'package_length_offset' => 0,
        'package_body_offset'  => 2,

        // 'event' => '',
        'debug'                => true,
        'log_path'             => BASE_PATH . '/logs',
    ]
];