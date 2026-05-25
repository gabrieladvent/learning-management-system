<?php

return [
    'material_completion' => [
        'words_per_minute' => 210,
        'minimum_seconds' => 60,
        'active_ratio' => 0.80,
        'file_counts_as_done' => true,
    ],

    'risk_thresholds' => [
        'window' => 'rolling_7d',
        'class_avg_low_pct' => 50,
        'class_avg_critical_pct' => 25,
        'overdue_critical_count' => 2,
    ],

    'retention' => [
        'events_days' => 90,
    ],

    'session' => [
        'timeout_minutes' => 5,
        'max_active_gap_ms' => 60_000,
    ],

    'validation' => [
        'max_clock_drift_minutes' => 10,
        'max_events_per_request' => 50,
        'max_payload_kb' => 32,
    ],

    'export' => [
        'pseudo_secret_env' => 'LEARNING_PROGRESS_PSEUDO_SECRET',
    ],

    'monitoring' => [
        'log_path' => storage_path('logs/progress-metrics.log'),
    ],
];
