<?php
session_start();

require_once __DIR__ . '/../src/actions/getToken.php';
require_once __DIR__ . '/../src/actions/uploadFile.php';
require_once __DIR__ . '/../src/actions/getFolder.php';
require_once __DIR__ . '/../src/actions/deleteFile.php';
require_once __DIR__ . '/../src/helpers/dotenv.php';

try {
    loadEnv();
} catch (Exception $e) {
    error_log("Error loading environment variables: " . $e->getMessage());
}

try {
    $tokenData = fetchToken();
    $token = $tokenData['access_token'] ?? null;
    if (!$token) {
        throw new Exception("Token not received. Check fetchToken() logic.");
    }
} catch (Exception $e) {
    $token = null;
    error_log("Error fetching token: " . $e->getMessage());
}


$folderContents = [];
if ($token) {
    try {
        $folderContents = getFolder($token, 'php');
    } catch (Exception $e) {
        error_log("Error fetching folder contents: " . $e->getMessage());
    }
}


// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deleteFileName']) && $token) {
    $deleteFileName = $_POST['deleteFileName'];
    try {
        deleteFile($token, $deleteFileName);
        $_SESSION['deleteMessage'] = "File '$deleteFileName' deleted successfully!";
    } catch (Exception $e) {
        $_SESSION['deleteMessage'] = "Error deleting file: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$deleteMessage = $_SESSION['deleteMessage'] ?? null;
unset($_SESSION['deleteMessage']);


$uploadMessage = "";
$uploadSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$token) {
        $uploadMessage = "Cannot upload file without a valid token.";
        $uploadSuccess = false;
    } elseif (!isset($_FILES['fileToUpload'])) {
        $uploadMessage = "No file selected for upload.";
        $uploadSuccess = false;
    } else {
        $file = $_FILES['fileToUpload'];

        if ($file['error'] === UPLOAD_ERR_OK) {
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFileName = "myfiles." . $fileExtension;

            $fileTmpPath = $file['tmp_name'];

            try {
                $fileContent = file_get_contents($fileTmpPath);
                if ($fileContent === false) {
                    throw new Exception("Failed to read file contents.");
                }

                try {
                    uploadFile($token, $newFileName, $fileContent);
                    $uploadSuccess = true;
                    $uploadMessage = "File '{$newFileName}' uploaded successfully!";
                } catch (Exception $e) {
                    $uploadMessage = "Upload failed. Please try again.";
                    $uploadSuccess = false;
                    error_log("Upload error: " . $e->getMessage());
                }
            } catch (Exception $e) {
                $uploadMessage = "Error processing the file. Please try again.";
                $uploadSuccess = false;
                error_log("File processing error: " . $e->getMessage());
            } finally {
                unset($fileContent, $fileTmpPath);
            }
        } else {
            $uploadMessage = "Error uploading file.";
            $uploadSuccess = false;
            error_log("File upload error code: " . $file['error']);
        }
    }

    $_SESSION['uploadMessage'] = $uploadMessage;
    $_SESSION['uploadSuccess'] = $uploadSuccess;

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_SESSION['uploadMessage'])) {
    $uploadMessage = $_SESSION['uploadMessage'];
    $uploadSuccess = $_SESSION['uploadSuccess'];

    unset($_SESSION['uploadMessage']);
    unset($_SESSION['uploadSuccess']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload</title>
    <link rel="stylesheet" href="../public/css/styles.css">
    <script>
        function hideUploadStatus() {
            setTimeout(function() {
                document.getElementById("uploadStatus").style.display = "none";
            }, 5000);
        }

        function updateFileName(input) {
            const fileName = input.files.length > 0 ? input.files[0].name : "No file selected";
            document.getElementById('fileName').textContent = fileName;
        }

        function handleDrop(event) {
            event.preventDefault();
            const input = document.getElementById('fileToUpload');
            input.files = event.dataTransfer.files;
            updateFileName(input);
        }

        function handleDragOver(event) {
            event.preventDefault();
            event.stopPropagation();
            document.getElementById('file-input-wrapper').classList.add('drag-over');
        }

        function handleDragLeave(event) {
            event.preventDefault();
            event.stopPropagation();
            document.getElementById('file-input-wrapper').classList.remove('drag-over');
        }

        function showLoader() {
            document.getElementById('uploadLoader').style.display = 'block';
        }

        function toggleSection() {
            var container = document.querySelector('.file-list-container');
            var button = document.querySelector('.expand-toggle');

            if (container.style.display === 'none') {
                container.style.display = 'block';
                button.textContent = 'Hide Uploads';
            } else {
                container.style.display = 'none';
                button.textContent = 'Show Uploads';
            }
        }
    </script>
</head>

<body>
    <div style="display: flex; flex-direction: column; align-items: center; padding: 20px; justify-content: center;">
        <h1>Upload a File to SharePoint</h1>

        <form class="form" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data" onsubmit="showLoader();">
            <div class="file-input-wrapper"
                id="file-input-wrapper"
                onclick="document.getElementById('fileToUpload').click();"
                ondrop="handleDrop(event)"
                ondragover="handleDragOver(event)"
                ondragleave="handleDragLeave(event)">
                <i class="fa fa-cloud-upload" style="font-size: 24px; color: #4CAF50;"></i>
                <p>Click or drag to upload a file</p>
                <input type="file" name="fileToUpload" id="fileToUpload" required onchange="updateFileName(this)">
                <span id="fileName">No file selected</span>
            </div>
            <button type="submit">Upload File</button>

            <div id="uploadLoader" class="spinner"></div>
        </form>

        <div style="display: flex; flex-direction: column; justify-content: center; align-items: center;" class="expandable-section">
            <button class="expand-toggle" onclick="toggleSection()">Show Uploads</button>
            <div class="file-list-container" style="display: none; margin-right: 35px;">
                <ul style="display: flex; flex-direction: column; gap: 10px; justify-content: center; align-items: center;" class="file-list">
                    <?php if (!empty($folderContents)): ?>
                        <?php foreach ($folderContents['value'] as $file): ?>
                            <li style="display: flex;  gap: 7px; border: solid 1px grey; border-radius: 5px; padding: 10px; align-items: center; justify-content: center;" class="file-item">
                                <strong><?= htmlspecialchars($file['name']); ?></strong><br>
                                <?php if (!empty($file['@microsoft.graph.downloadUrl'])): ?>
                                    <div>

                                        <a href="<?= htmlspecialchars($file['@microsoft.graph.downloadUrl']); ?>" target="_blank" class="file-link">Preview</a>
                                    </div>
                                <?php else: ?>
                                    <div>

                                        <span class="no-link">No download link available</span>
                                    </div>
                                <?php endif; ?>
                                <div style="margin-top: -10px;">

                                    <!-- Delete icon -->
                                    <form class="c-form" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                                        <input type="hidden" name="deleteFileName" value="<?= htmlspecialchars($file['name']); ?>">
                                        <button type="submit" title="Delete File">
                                            <i class="fa fa-trash" style="font-size: 18px; color: red;">Delete</i>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No files found in the folder.</p>
                    <?php endif; ?>
                </ul>
            </div>

        </div>



        <?php if (isset($uploadMessage) && $uploadMessage && $uploadSuccess): ?>
            <h2 id="uploadStatus" class="upload-success">
                <?= htmlspecialchars($uploadMessage); ?>
            </h2>
            <script>
                hideUploadStatus();
            </script>
        <?php elseif (isset($uploadMessage) && $uploadMessage && !$uploadSuccess): ?>
            <h2 id="uploadStatus" class="upload-error">
                <?= htmlspecialchars($uploadMessage); ?>
            </h2>
            <script>
                hideUploadStatus();
            </script>
        <?php endif; ?>
    </div>

</body>

</html>