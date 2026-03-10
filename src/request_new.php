<?php
session_start();
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/csrf.php';

// 1) Must be logged in to access
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// CSRF Protection: Verify token on POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken()) {
        $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
        header('Location: request_new.php');
        exit;
    }
}

// Configuration for file uploads
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

$errors = [];
$subject = '';
$description = '';
$contactPreference = 'email';

// Function to validate uploaded file
function validateUploadedFile($file) {
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => false, 'errors' => []]; // File is optional
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload error occurred.';
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = 'File size exceeds maximum allowed size (5MB).';
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        $errors[] = 'Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.';
    }
    
    // Check MIME type (more secure than extension check)
    // Use finfo if available, otherwise fall back to mime_content_type or $_FILES['type']
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $mimeType = mime_content_type($file['tmp_name']);
    } else {
        // Fallback to $_FILES type (less secure but better than nothing)
        $mimeType = $file['type'] ?? 'application/octet-stream';
    }
    
    if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
        $errors[] = 'Invalid file type detected (' . $mimeType . '). Only image files are allowed.';
    }
    
    // Verify it's actually an image by checking image dimensions
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        $errors[] = 'File is not a valid image.';
    }
    
    return ['valid' => empty($errors), 'errors' => $errors, 'extension' => $extension, 'mimeType' => $mimeType];
}

// Function to securely save uploaded file
function saveUploadedFile($file, $userId) {
    $validation = validateUploadedFile($file);
    
    if (!$validation['valid']) {
        return ['success' => false, 'errors' => $validation['errors']];
    }
    
    // Generate secure filename: timestamp_userid_randomhash.extension
    $extension = $validation['extension'];
    $randomHash = bin2hex(random_bytes(16));
    $filename = time() . '_' . $userId . '_' . $randomHash . '.' . $extension;
    $filepath = UPLOAD_DIR . $filename;
    
    // Move uploaded file to secure location
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'errors' => ['Failed to save uploaded file.']];
    }
    
    // Return relative path for database storage
    return ['success' => true, 'path' => 'uploads/' . $filename];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2) Read form fields
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $contactPreference = isset($_POST['contact_preference']) ? trim($_POST['contact_preference']) : 'email';

    // 3) Basic validation
    if ($subject === '') {
        $errors['subject'] = 'Subject is required';
    }

    if ($description === '') {
        $errors['description'] = 'Description is required';
    }

    // Optional: Add length limit
    if ($description !== '' && strlen($description) < 20) {
        $errors['description'] = 'Please provide a bit more detail (at least 20 characters).';
    }
    
    // Validate contact preference
    if (!in_array($contactPreference, ['phone', 'email'])) {
        $errors['contact_preference'] = 'Invalid contact preference selected.';
    }

    // Handle file upload (optional)
    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Debug: Check upload error code
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE form directive',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $errors['photo'] = 'Upload error: ' . ($uploadErrors[$_FILES['photo']['error']] ?? 'Unknown error');
        } else {
            $uploadResult = saveUploadedFile($_FILES['photo'], $_SESSION['user_id']);
            if (!$uploadResult['success']) {
                $errors['photo'] = implode(' ', $uploadResult['errors']);
            } else {
                $photoPath = $uploadResult['path'];
                // Debug: Log successful upload (remove in production)
                error_log("Photo uploaded successfully: " . $photoPath);
            }
        }
    }

    // 4) Insert into database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO requests (user_id, subject, description, contact_preference, photo_path, status, created_at, updated_at)
                 VALUES (:user_id, :subject, :description, :contact_preference, :photo_path, :status, NOW(), NOW())'
            );

            $stmt->execute([
                'user_id'            => $_SESSION['user_id'],
                'subject'            => $subject,
                'description'        => $description,
                'contact_preference' => $contactPreference,
                'photo_path'         => $photoPath, // Can be null if no photo uploaded
                'status'             => 'pending',
            ]);

            // Debug: Log database insert (remove in production)
            error_log("Request created with photo_path: " . ($photoPath ?? 'NULL'));

            // 5) Set flash message and redirect to list page
            $_SESSION['flash_success'] = 'Your request has been created successfully.';

            header('Location: request_list.php');
            exit;
        } catch (PDOException $e) {
            $errors['database'] = 'Failed to save request: ' . $e->getMessage();
            error_log("Database error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Request - Lovejoy App</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg,rgb(32, 70, 240) 0%,rgba(3, 28, 252, 0.71) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .request-card {
            width: 100%;
            max-width: 600px;
            background: #fff;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,.2);
        }

        h1 {
            font-size: 26px;
            text-align: center;
            color: #333;
            margin-bottom: 25px;
        }

        .form-group { margin-bottom: 18px; }
        label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 6px;
        }

        input, textarea, select {
            width: 100%;
            padding: 11px 14px;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
            font-size: 14px;
            transition: border-color .2s;
            font-family: 'Segoe UI', Tahoma, sans-serif;
        }

        select {
            background-color: #fff;
            cursor: pointer;
        }

        input[type="file"] {
            padding: 8px;
            cursor: pointer;
        }

        input[type="file"]::-webkit-file-upload-button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background: #f3f4ff;
            color: #667eea;
            font-weight: 600;
            cursor: pointer;
            margin-right: 10px;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        input:focus, textarea:focus, select:focus {
            border-color: #6a73e6;
            outline: none;
        }

        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            cursor: pointer;
            transition: transform .2s, box-shadow .2s;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(102, 126, 234, .35);
        }

        .field-error {
            color: #c33;
            font-size: 13px;
            margin-top: 5px;
            display: block;
        }

        input.error, textarea.error, select.error {
            border-color: #c33;
        }

        .back-link {
            text-align: center;
            margin-top: 16px;
            font-size: 14px;
            color: #666;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="request-card">
        <h1>Create New Request</h1>

        <?php if (!empty($errors)): ?>
            <div style="background: #fee; border: 1px solid #fcc; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px;">
                <strong style="color: #c33;">Please fix the following errors:</strong>
                <ul style="margin: 8px 0 0 20px; color: #c33;">
                    <?php foreach ($errors as $error): ?>
                        <?php if (is_array($error)): ?>
                            <?php foreach ($error as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endif; ?>
                <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="request_new.php" enctype="multipart/form-data">
            <div class="form-group">
                <label for="subject">Subject</label>
                <input
                    type="text"
                    id="subject"
                    name="subject"
                    value="<?php echo htmlspecialchars($subject); ?>"
                    class="<?php echo isset($errors['subject']) ? 'error' : ''; ?>"
                    required
                >
                <?php if (isset($errors['subject'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['subject']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="5"
                    class="<?php echo isset($errors['description']) ? 'error' : ''; ?>"
                    required
                ><?php echo htmlspecialchars($description); ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['description']); ?></span>
                <?php else: ?>
                    <div class="hint" style="font-size: 12px; color: #888; margin-top: 4px;">Please provide at least 20 characters</div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="contact_preference">Preferred Contact Method</label>
                <select
                    id="contact_preference"
                    name="contact_preference"
                    class="<?php echo isset($errors['contact_preference']) ? 'error' : ''; ?>"
                    required
                >
                    <option value="email" <?php echo $contactPreference === 'email' ? 'selected' : ''; ?>>Email</option>
                    <option value="phone" <?php echo $contactPreference === 'phone' ? 'selected' : ''; ?>>Phone</option>
                </select>
                <?php if (isset($errors['contact_preference'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['contact_preference']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="photo">Photo of Object (Optional)</label>
                <input
                    type="file"
                    id="photo"
                    name="photo"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    class="<?php echo isset($errors['photo']) ? 'error' : ''; ?>"
                >
                <?php if (isset($errors['photo'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['photo']); ?></span>
                <?php else: ?>
                    <div class="hint" style="font-size: 12px; color: #888; margin-top: 4px;">Maximum file size: 5MB. Allowed formats: JPG, PNG, GIF, WEBP</div>
                <?php endif; ?>
            </div>

            <?php echo csrfField(); ?>

            <button type="submit">Submit Request</button>
        </form>

        <div class="back-link">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>

