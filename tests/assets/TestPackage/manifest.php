<?php

$manifest = [
    'acceptable_sugar_versions' => [
        0 => '9.*',
        1 => '10.*',
    ],
    'acceptable_sugar_flavors' => [
        0 => 'ENT',
        1 => 'ULT',
        2 => 'PRO',
    ],
    'author' => 'David Angulo',
    'description' => 'Test package',
    'icon' => '',
    'is_uninstallable' => true,
    'name' => 'TestPackage',
    'published_date' => '2020-05-22',
    'type' => 'module',
    'version' => '1.0.0',
    'remove_tables' => '',
];

$installdefs = [
    'id' => 'TestPackage',
    'pre_execute' => [
        0 => '<basepath>/fix.php',
    ],
];