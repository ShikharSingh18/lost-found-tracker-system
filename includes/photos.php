<?php
/*
 * Photo upload handling (Phase 4, Sections 4.2 and 8.4, plus group notes:
 * up to 5 photos per item, 15 MB each).
 *
 * Uploaded files are untrusted. For every photo the server:
 *   1. checks the upload error code and size
 *   2. checks the REAL file type with finfo (not the browser's claim or the extension)
 *   3. checks it really decodes as an image, and isn't absurdly large in pixels
 *   4. RE-ENCODES it with GD as a new JPEG: resized for phones, metadata removed
 *      (EXIF can contain GPS location and camera owner - a POPIA/GDPR concern),
 *      and any hidden script inside the original file is thrown away
 *   5. saves it under a random server-generated name in uploads/item_photos/
 */

const PHOTO_MAX_FILES   = 5;
const PHOTO_MAX_BYTES   = 15 * 1024 * 1024;   // 15 MB per photo (group note: 10-15 MB)
const PHOTO_MAX_PIXELS  = 50_000_000;         // refuse "image bombs" larger than ~50 megapixels
const PHOTO_MAX_EDGE    = 1600;               // longest side after resizing, in pixels
const PHOTO_JPEG_QUALITY = 82;
const PHOTO_DIR = __DIR__ . '/../uploads/item_photos';

/**
 * Turns PHP's odd $_FILES['photos'] layout into a simple list and
 * drops empty slots. Returns [list of files, list of error messages].
 */
function collect_uploaded_photos(string $field): array
{
    $files = [];
    $errors = [];
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
        return [$files, $errors];
    }

    $f = $_FILES[$field];
    foreach ($f['name'] as $i => $name) {
        $err = $f['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $label = '"' . mb_substr((string) $name, 0, 60) . '"';
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            $errors[] = "$label is larger than the server allows. Check upload_max_filesize in php.ini (needs 15M).";
            continue;
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'][$i])) {
            $errors[] = "$label did not upload correctly. Please try again.";
            continue;
        }
        $files[] = ['name' => (string) $name, 'tmp' => $f['tmp_name'][$i], 'size' => (int) $f['size'][$i]];
    }

    if (count($files) > PHOTO_MAX_FILES) {
        $errors[] = 'You can upload at most ' . PHOTO_MAX_FILES . ' photos per item.';
    }
    return [$files, $errors];
}

/** Checks one uploaded file. Returns an error message, or null if it is acceptable. */
function validate_photo(array $file): ?string
{
    $label = '"' . mb_substr($file['name'], 0, 60) . '"';

    if ($file['size'] <= 0 || $file['size'] > PHOTO_MAX_BYTES) {
        return "$label must be between 1 byte and 15 MB.";
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp']);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        return "$label is not a JPG or PNG image.";
    }

    $info = @getimagesize($file['tmp']);
    if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        return "$label could not be read as an image.";
    }
    if ($info[0] * $info[1] > PHOTO_MAX_PIXELS) {
        return "$label has too many pixels. Please use a smaller photo.";
    }
    return null;
}

/**
 * Re-encodes one validated photo and saves it with a random name.
 * Returns the storage key (file name) or throws RuntimeException.
 */
function store_photo(array $file): string
{
    if (!is_dir(PHOTO_DIR) && !mkdir(PHOTO_DIR, 0750, true)) {
        throw new RuntimeException('Photo folder could not be created.');
    }

    $type = getimagesize($file['tmp'])[2];
    $src = $type === IMAGETYPE_PNG ? @imagecreatefrompng($file['tmp']) : @imagecreatefromjpeg($file['tmp']);
    if ($src === false) {
        throw new RuntimeException('Photo could not be decoded.');
    }

    // Phones often store photos sideways with an EXIF "orientation" flag. Apply it.
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp']);
        $rotate = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($rotate !== 0) {
            $rotated = imagerotate($src, $rotate, 0);
            imagedestroy($src);
            $src = $rotated;
        }
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, PHOTO_MAX_EDGE / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));

    // New canvas with a white background (so transparent PNGs don't turn black).
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    $key = bin2hex(random_bytes(16)) . '.jpg';   // server-generated name
    $ok = imagejpeg($dst, PHOTO_DIR . '/' . $key, PHOTO_JPEG_QUALITY);
    imagedestroy($dst);
    if (!$ok) {
        throw new RuntimeException('Photo could not be saved.');
    }
    return $key;
}

/** Deletes stored photo files, e.g. when the database insert fails afterwards. */
function delete_stored_photos(array $keys): void
{
    foreach ($keys as $key) {
        if (preg_match('/^[a-f0-9]{32}\.jpg$/', $key)) {
            @unlink(PHOTO_DIR . '/' . $key);
        }
    }
}
