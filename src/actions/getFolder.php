<?php
require_once __DIR__ . '/../helpers/dotenv.php';

loadEnv();


function getFolder($token, $folderName)
{
    $driveId = getEnvVar('MICROSOFT_DRIVE_ID');
    $USERS = getEnvVar('USERS');
    if (!$driveId) {
        throw new Exception("Drive ID is missing in environment variables.");
    }

    $url = "https://graph.microsoft.com/v1.0/drives/{$driveId}/root:/{$USERS}/{$folderName}:/children";

    $headers = [
        "Authorization: Bearer {$token}",
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        throw new Exception('Error retrieving folder contents: ' . curl_error($ch));
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($statusCode == 200) {
        return json_decode($response, true);
    } else {
        throw new Exception('Error retrieving folder contents: ' . $response);
    }
}
