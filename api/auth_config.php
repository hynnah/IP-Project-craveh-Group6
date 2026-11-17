<?php
// Public front-end settings.
//
// The Google client ID is not a secret — it ships inside the page and is
// visible to anyone. It lives in config.php anyway so each developer can
// point at their own Google project without editing tracked files.
//
// Nothing secret belongs in this response.

require_once __DIR__ . '/bootstrap.php';

global $google_config;
$clientId = (string) ($google_config['client_id'] ?? '');

// An unconfigured install returns an empty string, and the page simply does
// not show the Google button rather than rendering one that cannot work.
if (str_starts_with($clientId, 'YOUR_CLIENT_ID')) {
    $clientId = '';
}

respond(['success' => true, 'googleClientId' => $clientId]);
