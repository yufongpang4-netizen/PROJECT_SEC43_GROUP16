<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: receipt_upload_helper.php centralizes hardened receipt upload logic.
// These comments explain what each validation layer does and why receipt
// evidence must be stored safely for Staff, Finance, and Admin review.
// ============================================================================

// === SECTION 1: RECEIPT UPLOAD POLICY ===
// What: Define the only receipt file types and maximum size accepted by the claim system.
// Why: Receipts are evidence files, so the system should accept only business-appropriate PDF and image formats.
function getReceiptUploadPolicy()
{
    return [
        'max_bytes' => 5 * 1024 * 1024,
        'allowed_mime_types' => [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ],
    ];
}

// === SECTION 2: RECEIPT DIRECTORY PREPARATION ===
// What: Ensure the receipt directory exists and contains basic Apache hardening files.
// Why: Uploaded evidence should not expose directory listings or execute server-side scripts.
function ensureReceiptUploadDirectory($uploadDir)
{
    if (!is_dir($uploadDir)) {
        // BEST PRACTICE: Create the upload directory with non-executable default permissions when deploying on a fresh XAMPP environment.
        mkdir($uploadDir, 0755, true);
    }

    $htaccessPath = rtrim($uploadDir, "/\\") . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccessPath)) {
        // SECURITY: Apache rules disable directory listing and deny direct execution/access to script-like files in the upload folder.
        // Why: Even if a malicious file reaches storage, the web server should not treat it as executable application code.
        file_put_contents($htaccessPath, "Options -Indexes\nRemoveHandler .php .phtml .php3 .php4 .php5 .phar\nRemoveType .php .phtml .php3 .php4 .php5 .phar\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar|cgi|pl|asp|aspx|jsp)$\">\n    Require all denied\n</FilesMatch>\n");
    }

    $indexPath = rtrim($uploadDir, "/\\") . DIRECTORY_SEPARATOR . 'index.html';
    if (!file_exists($indexPath)) {
        // SECURITY: A blank index file reduces accidental directory exposure on servers that ignore .htaccess rules.
        file_put_contents($indexPath, '');
    }
}

// === SECTION 3: RECEIPT FILE VALIDATION ===
// What: Validate one uploaded receipt using PHP upload status, file size, MIME detection, and content checks.
// Why: Browser-provided filenames and MIME types are untrusted and must not decide what enters receipt storage.
function validateReceiptUpload($file, &$errorMessage)
{
    $policy = getReceiptUploadPolicy();

    if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errorMessage = 'Receipt upload failed. Please choose the file again.';
        return false;
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        // SECURITY: is_uploaded_file() confirms the file came through PHP's upload mechanism.
        $errorMessage = 'Invalid receipt upload source.';
        return false;
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > $policy['max_bytes']) {
        $errorMessage = 'Receipt file size must be 5MB or below.';
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);

    if (!array_key_exists($detectedMime, $policy['allowed_mime_types'])) {
        // SECURITY: Server-side MIME detection blocks renamed scripts and unsupported files.
        $errorMessage = 'Invalid receipt file type. Only PDF, JPG, and PNG files are allowed.';
        return false;
    }

    if (in_array($detectedMime, ['image/jpeg', 'image/png'], true) && getimagesize($file['tmp_name']) === false) {
        // SECURITY: getimagesize() confirms image receipts are structurally valid images, not merely renamed files.
        $errorMessage = 'Invalid image receipt. Please upload a valid JPG or PNG file.';
        return false;
    }

    if ($detectedMime === 'application/pdf') {
        $handle = fopen($file['tmp_name'], 'rb');
        $header = $handle ? fread($handle, 4) : '';
        if ($handle) {
            fclose($handle);
        }

        if ($header !== '%PDF') {
            // SECURITY: A basic PDF signature check rejects obvious non-PDF files with a forged MIME result.
            $errorMessage = 'Invalid PDF receipt. Please upload a valid PDF file.';
            return false;
        }
    }

    return [
        'mime_type' => $detectedMime,
        'extension' => $policy['allowed_mime_types'][$detectedMime],
    ];
}

// === SECTION 4: RECEIPT STORAGE ===
// What: Store a validated receipt using a random server-generated filename.
// Why: Random filenames prevent path manipulation, filename collisions, and disclosure of user-supplied file names.
function saveReceiptUpload($file, $uploadDir, &$errorMessage)
{
    $validation = validateReceiptUpload($file, $errorMessage);

    if ($validation === null || $validation === false) {
        return $validation;
    }

    ensureReceiptUploadDirectory($uploadDir);

    // SECURITY: random_bytes() prevents attackers from predicting receipt filenames.
    $safeFilename = bin2hex(random_bytes(16)) . '.' . $validation['extension'];
    $targetPath = rtrim($uploadDir, "/\\") . DIRECTORY_SEPARATOR . $safeFilename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $errorMessage = 'Failed to save the uploaded receipt.';
        return false;
    }

    chmod($targetPath, 0644);
    return $safeFilename;
}

// === SECTION 5: OLD RECEIPT CLEANUP ===
// What: Delete a previous receipt only when the filename is safe and located inside the receipt directory.
// Why: Replacing receipt evidence should not allow path traversal or deletion outside the upload folder.
function deleteReceiptFile($uploadDir, $filename)
{
    if (empty($filename)) {
        return;
    }

    $basename = basename($filename);
    if ($basename !== $filename) {
        return;
    }

    $targetPath = rtrim($uploadDir, "/\\") . DIRECTORY_SEPARATOR . $basename;
    $realUploadDir = realpath($uploadDir);
    $realTargetPath = realpath($targetPath);

    if ($realUploadDir && $realTargetPath && str_starts_with($realTargetPath, $realUploadDir) && is_file($realTargetPath)) {
        unlink($realTargetPath);
    }
}
