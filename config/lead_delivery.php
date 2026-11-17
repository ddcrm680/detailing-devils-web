<?php

return [
    'enabled' => (bool) env('LEAD_DELIVERY_ENABLED', false),

    // Confirm that 200/201 mean acceptance and agree retry/idempotency behavior first.
    'contract_confirmed' => (bool) env('LEAD_DELIVERY_CONTRACT_CONFIRMED', false),
    'franchise_sources_confirmed' => (bool) env('LEAD_DELIVERY_FRANCHISE_SOURCES_CONFIRMED', false),
    'endpoint' => env('LEAD_DELIVERY_WEBHOOK_URL'),
    'token' => env('LEAD_DELIVERY_TOKEN'),

    'auth_header' => 'Authorization',
    'auth_scheme' => 'Bearer',
    'reference_header' => 'Idempotency-Key',
    'accepted_statuses' => [200, 201],
    'connect_timeout' => 3,
    'timeout' => 10,
    'max_attempts' => 8,
    'retry_delays' => [60, 300, 900, 3600, 21600],
    'run_seconds' => 45,
];
