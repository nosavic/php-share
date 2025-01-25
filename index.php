<?php
session_start();

require_once __DIR__ . '/src/actions/getToken.php';
require_once __DIR__ . '/src/actions/uploadFile.php';
require_once __DIR__ . '/src/actions/getFolder.php';
require_once __DIR__ . '/src/actions/getFile.php';
require_once __DIR__ . '/src/actions/deleteFile.php';
require_once __DIR__ . '/src/helpers/dotenv.php';

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


$mainFolderContents = [];
$allFiles = [];
if ($token) {
    try {
        // Get all subfolders in the main folder
        $mainFolderContents = getFolder($token);
        foreach ($mainFolderContents['value'] as $item) {
            if ($item['name']) { // Ensure it's a folder
                $folderName = $item['name'];
                $filesInSubfolder = getFile($token, "{$folderName}");
                $allFiles[$folderName] = $filesInSubfolder['value'];
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching contents: " . $e->getMessage());
        // echo "<p>Error fetching contents: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    // echo "<p>No token provided. Unable to fetch folder contents.</p>";
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
        $customFileName = $_POST['customFileName'] ?? null;
        $fileFolder = $_POST['fileFolder'] ?? null;

        if ($file['error'] === UPLOAD_ERR_OK && $customFileName && $fileFolder) {
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFileName = $customFileName . "." . $fileExtension;
            $fileTmpPath = $file['tmp_name'];

            try {
                $fileContent = file_get_contents($fileTmpPath);
                if ($fileContent === false) {
                    throw new Exception("Failed to read file contents.");
                }

                try {
                    $fullPath = $fileFolder . '/' . $newFileName;
                    uploadFile($token, $fullPath, $fileContent);
                    $uploadSuccess = true;
                    $uploadMessage = "File '{$newFileName}' uploaded successfully to folder '{$fileFolder}'!";
                } catch (Exception $e) {
                    $uploadMessage = "Upload failed. Please try again.";
                    $uploadSuccess = false;
                    error_log("Upload error: " . $e->getMessage());
                }
            } catch (Exception $e) {
                $uploadMessage = "Error processing the file. Please try again.";
                $uploadSuccess = false;
                error_log("File processing error: " . $e->getMessage());
            }
        } else {
            $uploadMessage = "Error: Missing file name or folder.";
            $uploadSuccess = false;
        }

        $_SESSION['uploadMessage'] = $uploadMessage;
        $_SESSION['uploadSuccess'] = $uploadSuccess;

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
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
    <link rel="stylesheet" href="public/css/styles.css">
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
            var container = document.querySelector('.folder-container');
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

            <!-- New fields for file name and folder -->
            <div class="form-section">

                <div>
                    <label for="customFileName">File Name:</label>
                    <input type="text" name="customFileName" id="customFileName" placeholder="Enter file name" required>
                </div>
                <div>
                    <label for="fileFolder">Folder Name:</label>
                    <input type="text" name="fileFolder" id="fileFolder" placeholder="Enter folder name" required>
                </div>

                <button type="submit">Upload File</button>
                <div style="margin-top: 10px;" id="uploadLoader" class="spinner"></div>
            </div>
        </form>

        <div class="expandable-section">
            <button class="expand-toggle" onclick="toggleSection()">Show Uploads</button>
            <div style="display: none; height: 500px; overflow-y: scroll; " class="folder-container">
                <h2>Subfolders and Files</h2>
                <?php if (!empty($allFiles)): ?>
                    <?php foreach ($allFiles as $subfolder => $files): ?>
                        <div style="margin-bottom: 20px;" class="folder">
                            <h3>Folder: <?= htmlspecialchars($subfolder); ?></h3>
                            <ul>
                                <?php foreach ($files as $file): ?>
                                    <li style="display: flex; align-items: center;">
                                        <strong style="color: grey;"><?= htmlspecialchars($file['name']); ?></strong>

                                        <div style="display: flex; align-items: center;  ">
                                            <div>
                                                <?php if (!empty($file['@microsoft.graph.downloadUrl'])): ?>
                                                    <div style="background-color: black; color: white; padding: 5px; border-radius: 5px; text-align: center;">
                                                        <a style="text-decoration: none; color: white;" href="<?= htmlspecialchars($file['@microsoft.graph.downloadUrl']); ?>" target="_blank" class="file-link">Preview</a>
                                                    </div>
                                                <?php else: ?>
                                                    <div>
                                                        <span class="no-link">No link</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div style="padding-bottom: 18px; padding-left: 7px;">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="deleteFileName" value="<?= htmlspecialchars($file['name']); ?>">
                                                    <button type="submit" class="delete-btn" title="Delete">
                                                        &#x2716;
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No subfolders or files found.</p>
                <?php endif; ?>
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