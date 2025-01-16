<?php
require_once __DIR__ . '/../helpers/dotenv.php';

loadEnv();

function deleteFile($accessToken, $filename)
{
    try {
        if (empty($filename)) {
            http_response_code(400);
            echo json_encode(["message" => "File name is required"]);
            return;
        }

        // Retrieve environment variables
        $driveId = getEnvVar('MICROSOFT_DRIVE_ID');
        $users = getEnvVar('USERS') ?: "";
        $user = getEnvVar('USER') ?: "";

        if (empty($driveId)) {
            http_response_code(500);
            echo json_encode(["message" => "Microsoft Drive ID is not configured"]);
            return;
        }

        $url = "https://graph.microsoft.com/v1.0/drives/$driveId/root:/{$users}/{$user}/{$filename}";

        if (empty($accessToken)) {
            http_response_code(401);
            echo json_encode(["message" => "Authorization token missing or invalid"]);
            return;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $accessToken"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 204) {
            http_response_code(200);
            echo json_encode(["message" => "File deleted successfully"]);
        } else {
            http_response_code($httpCode);
            echo json_encode([
                "message" => "Error deleting file",
                "error" => $response
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "message" => "An unexpected error occurred",
            "error" => $e->getMessage()
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);

    $filename = $input['filename'] ?? null;
    $authHeader = getallheaders()['Authorization'] ?? null;
    $accessToken = str_replace('Bearer ', '', $authHeader);

    deleteFile($filename, $accessToken);
}
