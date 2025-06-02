<?php
return [
    'columns' => [
        'log_name' => [
            'label' => 'Typ',
        ],
        'event' => [
            'label' => 'Událost',
        ],
        'subject_type' => [
            'label' => 'Subjekt',
        ],
        'causer' => [
            'label' => 'Uživatel',
        ],
        'properties' => [
            'label' => 'Vlastnosti',
        ],
        'created_at' => [
            'label' => 'Zaznamenáno',
        ],
    ],
    'filters' => [
        'created_at' => [
            'label'         => 'Zaznamenáno',
            'created_from'  => 'Od data',
            'created_until' => 'Do data',
        ],
        'event' => [
            'label' => 'Událost',
        ],
    ],
];