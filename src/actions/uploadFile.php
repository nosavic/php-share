<?php
require_once __DIR__ . '/../helpers/dotenv.php';

loadEnv();

// Debug: Uncomment this line to verify if .env exists
// var_dump(file_exists(__DIR__ . '/../../.env')); // Should return true

// Print environment variables for debugging (uncomment if needed)
// print_r($_ENV);

// Function to upload a file to Microsoft OneDrive
function uploadFile($token, $newFileName, $fileContent)
{
    $driveId = getEnvVar('MICROSOFT_DRIVE_ID');
    $users = getEnvVar('USERS');
    $user = getEnvVar('USER');

    if (!$driveId || !$users || !$user) {
        throw new Exception('Missing required environment variables.');
    }

    $url = "https://graph.microsoft.com/v1.0/drives/{$driveId}/root:/{$users}/{$user}/{$newFileName}:/content";

    $headers = [
        "Authorization: Bearer {$token}",
        "Content-Type: application/octet-stream",
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);

    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("Upload response: " . $response);

    if ($statusCode === 200 || $statusCode === 201) {
        $responseData = json_decode($response, true);

        if (isset($responseData['id']) && isset($responseData['name'])) {
            error_log("File uploaded successfully. File ID: {$responseData['id']}, File Name: {$responseData['name']}");
            return $responseData;
        } else {
            throw new Exception("Upload succeeded but no 'id' or 'name' returned in response.");
        }
    } else {
        error_log("Upload failed. Status Code: {$statusCode}. Response: {$response}");
        throw new Exception("Upload failed. Status Code: {$statusCode}. Response: {$response}");
    }
}
