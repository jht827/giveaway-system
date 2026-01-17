<?php
/**
 * track_api.php
 * Provider loader for tracking APIs.
 */

function get_tracking_events($logistics_no) {
    $provider = $GLOBALS['gsTrackingProvider'] ?? '17track';
    $provider = preg_replace('/[^a-z0-9_-]/i', '', $provider);
    $provider_file = __DIR__ . '/track_api/' . $provider . '.php';

    if (!is_file($provider_file)) {
        return [
            'status' => 'provider_missing',
            'data' => [],
            'raw' => 'Provider file not found: ' . $provider
        ];
    }

    require_once $provider_file;

    $handler = 'track_api_' . $provider . '_events';
    if (!function_exists($handler)) {
        return [
            'status' => 'provider_invalid',
            'data' => [],
            'raw' => 'Provider handler missing: ' . $handler
        ];
    }

    return $handler($logistics_no);
}
