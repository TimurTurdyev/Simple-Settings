<?php

return [
    'table_name'        => 'simple_settings',
    'cache_key_prefix'  => 'simple_settings',
    'events'            => false,
    'validation_rules'  => [],

    'audit' => [
        'enabled' => false,
        'table'   => 'simple_setting_changes',
    ],
];
