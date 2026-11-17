<?php

return [
    'enabled' => (bool) env('LEAD_DELIVERY_ENABLED', false),

    // Confirm the provider's acknowledgement AND idempotency contract before enabling.
    'contract_confirmed' => (bool) env('LEAD_DELIVERY_CONTRACT_CONFIRMED', false),
    'franchise_sources_confirmed' => (bool) env('LEAD_DELIVERY_FRANCHISE_SOURCES_CONFIRMED', false),
    'endpoint' => env('LEAD_DELIVERY_WEBHOOK_URL'),
    'auth_header' => env('LEAD_DELIVERY_AUTH_HEADER', 'Authorization'),
    'auth_scheme' => env('LEAD_DELIVERY_AUTH_SCHEME', 'Bearer'),
    'token' => env('LEAD_DELIVERY_TOKEN'),
    'reference_header' => env('LEAD_DELIVERY_REFERENCE_HEADER', 'Idempotency-Key'),

    // No default acknowledgement: HTTP 200 alone never confirms delivery.
    'ack_statuses' => array_map(
        'intval',
        explode(',', (string) env('LEAD_DELIVERY_ACK_STATUSES', '200,201'))
    ),
    'ack_path' => env('LEAD_DELIVERY_ACK_PATH'),
    'ack_type' => env('LEAD_DELIVERY_ACK_TYPE', 'boolean'),
    'ack_value' => env('LEAD_DELIVERY_ACK_VALUE'),
    'ack_reference_path' => env('LEAD_DELIVERY_ACK_REFERENCE_PATH'),

    'connect_timeout' => (int) env('LEAD_DELIVERY_CONNECT_TIMEOUT', 3),
    'timeout' => (int) env('LEAD_DELIVERY_TIMEOUT', 10),
    'max_attempts' => (int) env('LEAD_DELIVERY_MAX_ATTEMPTS', 8),
    'retry_delays' => array_map(
        'intval',
        explode(',', (string) env('LEAD_DELIVERY_RETRY_DELAYS', '60,300,900,3600,21600'))
    ),
    'run_seconds' => 45,
];
