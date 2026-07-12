<?php
// Secure upload handling for KYC documents (selfies, vehicle photos,
// ID/proof-of-address documents) and delivery signatures.
//
// Security/privacy measures applied here:
//   - Files are stored under /uploads, which is blocked from direct
//     web access (see uploads/.htaccess) — only served via
//     admin/view_document.php after an admin auth check.
//   - Filenames are replaced with random tokens (never the original
//     filename), preventing path traversal and information leakage.
//   - Real MIME type is checked via finfo (not just the extension,
//     which is trivially spoofable).
//   - Photos (selfie, vehicle photo) are re-encoded through GD, which
//     strips EXIF metadata in the process — phones often embed GPS
//     coordinates and device info in photo EXIF data, which has no
//     business being retained here and would be a privacy footgun.
//   - A maximum file size is enforced server-side (never trust a
//     client-side "max size" alone).

define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024); // 5MB

/**
 * Handle an image upload ($_FILES entry) that must be a photo
 * (selfie or vehicle photo). Re-encodes through GD to strip EXIF data.
 *
 * @return string|null Relative path (e.g. "kyc/selfies/abc123.jpg") on success, or null on failure (with $errors appended to).
 */
function handle_photo_upload(string $fieldName, string $subdir, array &$errors): ?string {
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = ucfirst(str_replace('_', ' ', $fieldName)) . ' is required.';
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'There was a problem uploading ' . str_replace('_', ' ', $fieldName) . '.';
        return null;
    }

    if ($file['size'] > UPLOAD_MAX_BYTES) {
        $errors[] = ucfirst(str_replace('_', ' ', $fieldName)) . ' must be under 5MB.';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($allowedMimes[$mime])) {
        $errors[] = ucfirst(str_replace('_', ' ', $fieldName)) . ' must be a JPG or PNG photo.';
        return null;
    }

    // Re-encode via GD — this both strips EXIF metadata and guards
    // against a file that merely has an image-like MIME type but isn't
    // actually a valid, safely-parseable image.
    $image = $mime === 'image/png' ? @imagecreatefrompng($file['tmp_name']) : @imagecreatefromjpeg($file['tmp_name']);
    if (!$image) {
        $errors[] = 'Could not process ' . str_replace('_', ' ', $fieldName) . ' — please try a different photo.';
        return null;
    }

    $ext = $allowedMimes[$mime];
    $filename = bin2hex(random_bytes(20)) . '.' . $ext;
    $relativePath = 'kyc/' . $subdir . '/' . $filename;
    $fullPath = __DIR__ . '/../uploads/' . $relativePath;

    $saved = $ext === 'png' ? imagepng($image, $fullPath) : imagejpeg($image, $fullPath, 88);
    imagedestroy($image);

    if (!$saved) {
        $errors[] = 'Could not save ' . str_replace('_', ' ', $fieldName) . '.';
        return null;
    }

    return $relativePath;
}

/**
 * Handle a verification document upload (ID card, passport, utility
 * bill) — accepts JPG/PNG (re-encoded like photos) or PDF (validated
 * but not re-encoded, since GD can't process PDFs).
 *
 * @return string|null Relative path on success, or null on failure.
 */
function handle_document_upload(string $fieldName, array &$errors): ?string {
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please upload a verification document.';
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'There was a problem uploading your verification document.';
        return null;
    }

    if ($file['size'] > UPLOAD_MAX_BYTES) {
        $errors[] = 'Verification document must be under 5MB.';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
        // Treat like a photo upload — re-encode to strip EXIF, same as selfies.
        $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        $image = $mime === 'image/png' ? @imagecreatefrompng($file['tmp_name']) : @imagecreatefromjpeg($file['tmp_name']);
        if (!$image) {
            $errors[] = 'Could not process the uploaded document — please try a different file.';
            return null;
        }
        $ext = $allowedMimes[$mime];
        $filename = bin2hex(random_bytes(20)) . '.' . $ext;
        $relativePath = 'kyc/documents/' . $filename;
        $fullPath = __DIR__ . '/../uploads/' . $relativePath;
        $saved = $ext === 'png' ? imagepng($image, $fullPath) : imagejpeg($image, $fullPath, 88);
        imagedestroy($image);
        if (!$saved) {
            $errors[] = 'Could not save the uploaded document.';
            return null;
        }
        return $relativePath;
    }

    if ($mime === 'application/pdf') {
        $filename = bin2hex(random_bytes(20)) . '.pdf';
        $relativePath = 'kyc/documents/' . $filename;
        $fullPath = __DIR__ . '/../uploads/' . $relativePath;
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            $errors[] = 'Could not save the uploaded document.';
            return null;
        }
        return $relativePath;
    }

    $errors[] = 'Verification document must be a JPG, PNG, or PDF file.';
    return null;
}

/**
 * Save a base64-encoded PNG signature (from the canvas signature pad)
 * to disk. Returns the relative path, or null on failure.
 */
function save_signature_png(string $base64Data): ?string {
    if (!preg_match('/^data:image\/png;base64,(.+)$/', $base64Data, $matches)) {
        return null;
    }

    $binary = base64_decode($matches[1], true);
    if ($binary === false || strlen($binary) < 100) {
        return null; // reject empty/near-empty "signatures"
    }

    // Re-parse through GD as a sanity check that this is really a valid PNG.
    $image = @imagecreatefromstring($binary);
    if (!$image) {
        return null;
    }

    $filename = bin2hex(random_bytes(20)) . '.png';
    $relativePath = 'signatures/' . $filename;
    $fullPath = __DIR__ . '/../uploads/' . $relativePath;
    $saved = imagepng($image, $fullPath);
    imagedestroy($image);

    return $saved ? $relativePath : null;
}
