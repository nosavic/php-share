<?php
require_once __DIR__ . '/../helpers/dotenv.php';

loadEnv();

function uploadFile($token, $fullPath, $fileContent)
{
    $driveId = getEnvVar('MICROSOFT_DRIVE_ID');
    $users = getEnvVar('USERS');

    if (!$driveId) {
        throw new Exception("Drive ID not configured.");
    }

    $url = "https://graph.microsoft.com/v1.0/drives/$driveId/root:/$users/$fullPath:/content";
    $headers = [
        "Authorization: Bearer $token",
        "Content-Type: application/octet-stream",
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    } else {
        throw new Exception("Error uploading file: HTTP $httpCode. Response: $response");
    }
}
