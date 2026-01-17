<?php
/**
 * Provider: 17track (placeholder)
 * NOTE: Configure your 17track API key below.
 */

$gs17TrackApiKey = '';

function track_api_17track_events($logistics_no) {
    if (empty($logistics_no) || $logistics_no[0] == '7') {
        return ['status' => 'untrackable', 'data' => []];
    }

    return [
        'status' => 'no_data',
        'data' => [],
        'raw' => '17track placeholder provider is not implemented yet.'
    ];
}
