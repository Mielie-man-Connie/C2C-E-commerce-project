<?php
    /* Get Image: serve raw image BLOB by imageID */
    require_once __DIR__ . '/database.php';
    
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$id || $id < 1) {
        http_response_code(400);
        exit('Invalid image ID.');
    }
    
    try {
        $stmt = $pdo->prepare('SELECT imgData FROM images WHERE imageID = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('DB error.');
    }
    
    if (!$row || !$row['imgData']) {
        http_response_code(404);
        exit('Image not found.');
    }
    
    /* Detect MIME type from the raw bytes (finfo preferred, fallback to getimagesizefromstring) */
    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $m = finfo_buffer($finfo, $row['imgData']);
            finfo_close($finfo);
            if ($m !== false && $m !== '') $mime = $m;
        }
    }
    if ($mime === null && function_exists('getimagesizefromstring')) {
        $info = @getimagesizefromstring($row['imgData']);
        $mime = $info['mime'] ?? null;
    }
    
    /* Only serve actual image types */
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed, true)) {
        http_response_code(415);
        exit('Unsupported media type.');
    }
    
    /* Cache for 7 days — images are immutable once stored */
    header('Content-Type: '  . $mime);
    header('Cache-Control: public, max-age=604800, immutable');
    header('Content-Length: ' . strlen($row['imgData']));
    echo $row['imgData'];
?>