<?php
require_once __DIR__ . '/../helpers/dotenv.php';

loadEnv();

function fetchToken()
{
    $tenantId = getEnvVar('MICROSOFT_TENANT_ID');
    $clientId = getEnvVar('MICROSOFT_CLIENT_ID');
    $clientSecret = getEnvVar('MICROSOFT_CLIENT_SECRET');
    $scope = 'https://graph.microsoft.com/.default';
    $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

    $data = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'scope' => $scope,
        'grant_type' => 'client_credentials'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception('An error occurred while communicating with the authentication server.');
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($statusCode == 200) {
        return json_decode($response, true);
    } else {
        throw new Exception('Failed to fetch the token. Please check your environment variables and network connectivity.');
    }
}
