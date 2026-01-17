<?php
/**
 * track_api.php
 * Fixed Path Logic for 17track v2 JSON Response
 */

function get_17track_events($logistics_no) {
    $api_key = '';
    $events = [];

    // Rule: Skip if empty or internal '7' prefix post
    if (empty($logistics_no) || $logistics_no[0] == '7') {
        return ['status' => 'untrackable', 'data' => []];
    }

    $post_data = json_encode([["number" => $logistics_no]]);
    
    $ch = curl_init('https://api.17track.net/track/v2/gettrackinfo');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        '17token: ' . $api_key
    ]);
    
    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);

    // --- ACCURATE DRILLING LOGIC ---
    // We look into: data -> accepted[0] -> tracking -> providers[0] -> events
    if (isset($result['data']['accepted'][0]['tracking']['providers'][0]['events'])) {
        $raw_events = $result['data']['accepted'][0]['tracking']['providers'][0]['events'];
        
        foreach ($raw_events as $e) {
            $events[] = [
                'time' => str_replace('T', ' ', substr($e['time_iso'], 0, 19)),
                'desc' => $e['description'],
                'loc'  => $e['location'] ?? ''
            ];
        }
        return ['status' => 'success', 'data' => $events, 'raw' => $response];
    }

    // If we reach here, the structure didn't match
    return ['status' => 'no_data', 'data' => [], 'raw' => $response];
}
