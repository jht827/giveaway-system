<?php
/**
 * Provider: 17track
 * NOTE: Configure your 17track API key in config.php.
 */

function track_api_17track_events($logistics_no) {
    $logistics_no = trim((string)$logistics_no);
    if ($logistics_no === '' || $logistics_no[0] === '7') {
        return ['status' => 'untrackable', 'data' => []];
    }

    $api_key = $GLOBALS['gs17TrackApiKey'] ?? '';
    if ($api_key === '') {
        return [
            'status' => 'no_data',
            'data' => [],
            'raw' => '17track API key not configured.'
        ];
    }

    $result = track_api_17track_fetch($logistics_no, $api_key);
    if ($result['status'] === 'needs_register') {
        $register = track_api_17track_register($logistics_no, $api_key);
        if (!$register['ok']) {
            return [
                'status' => 'no_data',
                'data' => [],
                'raw' => $register['raw']
            ];
        }
        $result = track_api_17track_fetch($logistics_no, $api_key);
    }

    return $result;
}

function track_api_17track_fetch($logistics_no, $api_key) {
    $payload = json_encode([['number' => $logistics_no]], JSON_UNESCAPED_UNICODE);
    $response = track_api_17track_post('gettrackinfo', $api_key, $payload);

    if (!$response['ok']) {
        return [
            'status' => 'no_data',
            'data' => [],
            'raw' => $response['raw']
        ];
    }

    $body = json_decode($response['body'], true);
    if (!is_array($body)) {
        return [
            'status' => 'no_data',
            'data' => [],
            'raw' => $response['body']
        ];
    }

    $rejected = $body['data']['rejected'] ?? [];
    if (!empty($rejected)) {
        $error_code = $rejected[0]['error']['code'] ?? null;
        if ($error_code === -18019902) {
            return [
                'status' => 'needs_register',
                'data' => [],
                'raw' => $response['body']
            ];
        }

        return [
            'status' => 'no_data',
            'data' => [],
            'raw' => $response['body']
        ];
    }

    $accepted = $body['data']['accepted'] ?? [];
    if (empty($accepted)) {
        return [
            'status' => 'no_data',
            'data' => [],
            'raw' => $response['body']
        ];
    }

    $track_info = $accepted[0]['track_info']['tracking']['providers'][0]['events'] ?? [];
    if (empty($track_info)) {
        return [
            'status' => 'no_data',
            'data' => [],
            'raw' => $response['body']
        ];
    }

    $events = [];
    foreach ($track_info as $event) {
        $events[] = [
            'time' => track_api_17track_format_time($event),
            'desc' => $event['description_translation']['description']
                ?? $event['description']
                ?? ($event['stage'] ?? 'Unknown'),
            'loc' => $event['location']
                ?? track_api_17track_format_location($event['address'] ?? [])
        ];
    }

    return [
        'status' => 'ok',
        'data' => $events,
        'raw' => $response['body']
    ];
}

function track_api_17track_register($logistics_no, $api_key) {
    $payload = json_encode([['number' => $logistics_no]], JSON_UNESCAPED_UNICODE);
    $response = track_api_17track_post('register', $api_key, $payload);

    if (!$response['ok']) {
        return ['ok' => false, 'raw' => $response['raw']];
    }

    $body = json_decode($response['body'], true);
    if (!is_array($body)) {
        return ['ok' => false, 'raw' => $response['body']];
    }

    $accepted = $body['data']['accepted'] ?? [];
    if (!empty($accepted)) {
        return ['ok' => true, 'raw' => $response['body']];
    }

    return ['ok' => false, 'raw' => $response['body']];
}

function track_api_17track_post($endpoint, $api_key, $payload) {
    $url = 'https://api.17track.net/track/v2.4/' . $endpoint;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            '17token: ' . $api_key,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 20
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 300) {
        $raw = $error ?: 'HTTP ' . $status;
        return ['ok' => false, 'raw' => $raw, 'body' => $body ?: ''];
    }

    return ['ok' => true, 'raw' => '', 'body' => $body];
}

function track_api_17track_format_time(array $event) {
    $time_iso = $event['time_iso'] ?? null;
    if ($time_iso) {
        $time = new DateTime($time_iso);
        return $time->format('Y-m-d H:i:s');
    }

    $time_utc = $event['time_utc'] ?? null;
    if ($time_utc) {
        $time = new DateTime($time_utc);
        return $time->format('Y-m-d H:i:s');
    }

    $raw = $event['time_raw'] ?? [];
    $date = $raw['date'] ?? null;
    $time = $raw['time'] ?? null;
    if ($date && $time) {
        return $date . ' ' . $time;
    }
    if ($date) {
        return $date;
    }

    return 'Unknown time';
}

function track_api_17track_format_location(array $address) {
    $parts = [];
    foreach (['city', 'state', 'country'] as $field) {
        if (!empty($address[$field])) {
            $parts[] = $address[$field];
        }
    }
    if (!empty($parts)) {
        return implode(', ', $parts);
    }
    return null;
}
