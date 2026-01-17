# Tracking API Adaptation Guide

English | [中文](api-adaptation.zh.md)

This guide explains how to add your own tracking API provider for shipment status updates and the requirements the adapter must meet. The tracking adapters live in `track_api/` and are loaded by `track_api.php` based on the configured provider name.

## Requirements

Your provider adapter **must**:

1. **Live in `track_api/`** with the file name matching the provider key (e.g. `mycarrier.php`).
2. **Expose a handler function** named `track_api_<provider>_events` that accepts:
   - `$logistics_no` (string, tracking number)
   - `$send_way` (string or null, shipping method)
3. **Return an array** with the following keys:
   - `status` (string): `ok`, `no_data`, or `untrackable` for user-facing states.
   - `data` (array): tracking event list (empty when `status` is not `ok`).
   - `raw` (optional string): raw response or debug info for admins.
4. **Provide events in normalized structure**:
   ```php
   [
     [
       'time' => '2024-01-01 12:34:56',
       'desc' => 'Shipment picked up',
       'loc'  => 'Shanghai, CN' // optional
     ],
     ...
   ]
   ```
5. **Avoid `exit`/`die`** inside the provider. Return `status` + `raw` instead so the caller can decide how to display errors.

`track_api.php` sanitizes the provider name and validates the handler before calling it, so keep the function name and file name consistent.

## Status semantics

- `ok`: tracking data is available and returned in `data`.
- `no_data`: the API responded but no usable events were found (or a remote error occurred).
- `untrackable`: the shipment type cannot be tracked publicly (e.g., unregistered letters).
- `provider_missing` and `provider_invalid` are returned by `track_api.php` if the file or handler is missing.

## Step-by-step: add a new provider

1. **Create the provider file**: `track_api/mycarrier.php`.
2. **Implement the handler**:
   ```php
   <?php
   function track_api_mycarrier_events($logistics_no, $send_way = null) {
       $logistics_no = trim((string)$logistics_no);
       $send_way = strtolower(trim((string)$send_way));

       if ($logistics_no === '') {
           return ['status' => 'no_data', 'data' => [], 'raw' => 'Missing tracking number.'];
       }

       // Call your API here, then normalize events.
       $events = [];

       return [
           'status' => empty($events) ? 'no_data' : 'ok',
           'data' => $events,
           'raw' => ''
       ];
   }
   ```
3. **Configure the provider in `config.php`**:
   ```php
   $gsTrackingProvider = 'mycarrier';
   // Add any provider-specific API key here as a new config variable.
   ```
4. **Test on an order**: open the order status page and confirm the timeline renders.

## Recommended adapter practices

- **Normalize timestamps** to `Y-m-d H:i:s` where possible.
- **Return localized descriptions** if your provider supports them.
- **Use timeouts** for remote requests (cURL timeout is a good default).
- **Keep API keys in `config.php`**, not in the provider file.
- **Log raw responses** in the `raw` key for admin debugging.

## Reference: existing provider

The `track_api/17track.php` adapter is the canonical example. It handles API key checks, a register-then-fetch flow, response parsing, and event normalization. Use it as a starting point when building your own provider.
