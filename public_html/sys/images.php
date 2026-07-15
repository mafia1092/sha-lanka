<?php
// images.php — GD-based image processing for admin uploads.
// Produces the same two sizes the old Node/sharp pipeline made:
//   thumb: max 700px wide,  JPEG quality 72   -> <base>.jpg
//   large: max 1600px wide, JPEG quality 80   -> <base>-lg.jpg

const IMG_MAX_PIXELS = 40000000; // ~40 MP cap — GD needs ~5 bytes per pixel

/**
 * Validate an uploaded file and return [ok, error, type, width, height].
 * Accepts JPEG / PNG / WebP. HEIC (iPhone default) is NOT decodable by GD —
 * callers show a friendly "export as JPEG first" message.
 */
function img_validate(array $file) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [false, 'Upload failed — the file may be larger than the server allows.', '', 0, 0];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if ($mime === 'image/heic' || $mime === 'image/heif') {
        return [false, 'iPhone HEIC photos aren\'t supported — export/convert to JPEG first, then upload.', '', 0, 0];
    }
    $map = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($map[$mime])) {
        return [false, 'Unsupported file type (' . $mime . '). Please upload a JPEG, PNG or WebP image.', '', 0, 0];
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info) {
        return [false, 'That file doesn\'t look like a valid image.', '', 0, 0];
    }
    [$w, $h] = $info;
    if ($w * $h > IMG_MAX_PIXELS) {
        return [false, 'Image is too large (' . round($w * $h / 1000000) . ' MP). Please resize below 40 MP and try again.', '', 0, 0];
    }
    return [true, '', $map[$mime], $w, $h];
}

// Decode to a GD image, honouring JPEG EXIF rotation (phone photos)
function img_load($path, $type) {
    switch ($type) {
        case 'jpeg': $im = imagecreatefromjpeg($path); break;
        case 'png':  $im = imagecreatefrompng($path);  break;
        case 'webp': $im = imagecreatefromwebp($path); break;
        default: return false;
    }
    if ($im && $type === 'jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        switch ((int)($exif['Orientation'] ?? 1)) {
            case 3: $im = imagerotate($im, 180, 0); break;
            case 6: $im = imagerotate($im, -90, 0); break;
            case 8: $im = imagerotate($im, 90, 0);  break;
        }
    }
    return $im;
}

// Save a resized JPEG copy (never upscales). ALWAYS composites onto a white
// canvas so PNG/WebP transparency flattens consistently — JPEG has no alpha,
// and skipping this in the no-resize path would give a black background.
function img_save_resized($im, $dest, $maxWidth, $quality) {
    $w = imagesx($im);
    $h = imagesy($im);
    $nw = min($w, $maxWidth);
    $nh = ($nw === $w) ? $h : max(1, (int)round($h * ($nw / $w)));
    $out = imagecreatetruecolor($nw, $nh);
    $white = imagecolorallocate($out, 255, 255, 255);
    imagefill($out, 0, 0, $white);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return imagejpeg($out, $dest, $quality);
}

/**
 * Full gallery pipeline: validate -> decode -> write thumb + large.
 * Returns [ok, errorOrBase, orientation]. $destDir must exist and be writable.
 */
function img_process_gallery(array $file, $destDir, $fileBase) {
    ini_set('memory_limit', '512M'); // big phone photos decode to raw bitmaps

    [$ok, $err, $type] = img_validate($file);
    if (!$ok) return [false, $err, ''];

    $im = img_load($file['tmp_name'], $type);
    if (!$im) return [false, 'Could not read the image data. Try re-saving it as JPEG.', ''];

    $orientation = imagesx($im) >= imagesy($im) ? 'land' : 'port';

    $thumbOk = img_save_resized($im, rtrim($destDir, '/') . '/' . $fileBase . '.jpg', 700, 72);
    $largeOk = img_save_resized($im, rtrim($destDir, '/') . '/' . $fileBase . '-lg.jpg', 1600, 80);

    if (!$thumbOk || !$largeOk) {
        @unlink(rtrim($destDir, '/') . '/' . $fileBase . '.jpg');
        @unlink(rtrim($destDir, '/') . '/' . $fileBase . '-lg.jpg');
        return [false, 'Could not write the processed images (check folder permissions).', ''];
    }
    return [true, $fileBase, $orientation];
}

/**
 * Replace one service-card slide in place (assets/img/slides/<slug>/{1,2,3}.jpg).
 * Slides are single-size: max 1600px wide, quality 80.
 */
function img_process_slide(array $file, $destPath) {
    ini_set('memory_limit', '512M');

    [$ok, $err, $type] = img_validate($file);
    if (!$ok) return [false, $err];

    $im = img_load($file['tmp_name'], $type);
    if (!$im) return [false, 'Could not read the image data. Try re-saving it as JPEG.'];

    // Write to a temp file first and only replace the live slide once the new
    // file is verified — a failed/partial write must never destroy the
    // existing good image that is showing on the site right now.
    $tmp = $destPath . '.tmp';
    $saved = img_save_resized($im, $tmp, 1600, 80);
    $info  = $saved ? @getimagesize($tmp) : false;
    if (!$saved || !$info || $info[0] < 1) {
        @unlink($tmp);
        return [false, 'Could not write the image (check folder permissions / disk space).'];
    }
    if (!rename($tmp, $destPath)) {
        @unlink($tmp);
        return [false, 'Could not replace the existing image (check folder permissions).'];
    }
    return [true, ''];
}
