<?php
    /* Image Helper: compression and DB storage */
    /* Usage: compressAndStoreImage($pdo, $tmpFile, $mime, $userID = 0) */

    /* Detect MIME type for an uploaded file with safe fallbacks */
    function detectMime(string $tmpFile, ?string $fallback = null): ?string {
        // Prefer fileinfo if available
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f !== false) {
                $m = finfo_file($f, $tmpFile);
                finfo_close($f);
                if ($m !== false && $m !== '') return $m;
            }
        }

        // Fallback to mime_content_type if present
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($tmpFile);
            if ($m !== false && $m !== '') return $m;
        }

        // Use provided fallback (from $_FILES['type']) or null
        return $fallback ?: null;
    }

    /**
     * Compress image to max 512x512 and save to database
     * @param PDO $pdo - Database connection
     * @param string $tmpFile - Path to temporary uploaded file
     * @param string $mime - MIME type (e.g., 'image/jpeg' or 'image/gif')
     * @return int|null - Image ID if successful, null on failure
     */

    function storeRawImage(PDO $pdo, string $tmpFile, int $userID = 0): ?int {
        $data = file_get_contents($tmpFile);
        if ($data === false) {
            return null;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO images (userID, imgData) VALUES (:uid, :data)');
            $stmt->execute([':uid' => $userID, ':data' => $data]);
            return (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('Raw image save error: ' . $e->getMessage());
            return null;
        }
    }

    function compressAndStoreImage(PDO $pdo, string $tmpFile, string $mime, int $userID = 0): ?int {
        try {
            // If PHP GD is not available, save the original upload instead.
            if (!function_exists('imagecreatetruecolor')) {
                return storeRawImage($pdo, $tmpFile, $userID);
            }

            // Load the image
            $image = null;
            switch ($mime) {
                case 'image/jpeg':
                    if (function_exists('imagecreatefromjpeg')) {
                        $image = imagecreatefromjpeg($tmpFile);
                    }
                    break;
                case 'image/png':
                    if (function_exists('imagecreatefrompng')) {
                        $image = imagecreatefrompng($tmpFile);
                    }
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $image = imagecreatefromwebp($tmpFile);
                    }
                    break;
                case 'image/gif':
                    if (function_exists('imagecreatefromgif')) {
                        $image = imagecreatefromgif($tmpFile);
                    }
                    break;
                default:
                    return null;
            }

            if (!$image) {
                return storeRawImage($pdo, $tmpFile, $userID);
            }

            // Get current dimensions
            $origWidth = imagesx($image);
            $origHeight = imagesy($image);

            // Calculate new dimensions (maintain aspect ratio, max 512x512)
            $maxSize = 512;
            $ratio = min($maxSize / $origWidth, $maxSize / $origHeight);
            $newWidth = (int)($origWidth * $ratio);
            $newHeight = (int)($origHeight * $ratio);

            // Create new image with transparent background for PNG
            $compressed = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG
            if ($mime === 'image/png') {
                imagesavealpha($compressed, true);
                $transparent = imagecolorallocatealpha($compressed, 255, 255, 255, 127);
                imagefill($compressed, 0, 0, $transparent);
            }

            // Resize image
            imagecopyresampled($compressed, $image, 0, 0, 0, 0, 
                            $newWidth, $newHeight, $origWidth, $origHeight);

            // Capture output buffer
            ob_start();
            
            // Save compressed image in appropriate format
            if ($mime === 'image/jpeg') {
                imagejpeg($compressed, null, 85); // 85% quality
            } elseif ($mime === 'image/png') {
                imagepng($compressed, null, 8); // Max compression
            } elseif ($mime === 'image/webp') {
                imagewebp($compressed, null, 85);
            } elseif ($mime === 'image/gif') {
                imagegif($compressed);
            }

            $imageData = ob_get_clean();
            imagedestroy($image);
            imagedestroy($compressed);

            if (empty($imageData)) return null;

            // Save to database
            $stmt = $pdo->prepare('INSERT INTO images (userID, imgData) VALUES (:uid, :data)');
            $stmt->execute([':uid' => $userID, ':data' => $imageData]);
            
            return (int)$pdo->lastInsertId();

        } catch (Exception $e) {
            error_log('Image compression error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get average review score for a seller
     * @param PDO $pdo - Database connection
     * @param int $userID - The Seller's User ID
     * @return float|null - Average score or null if no reviews
     */
    function getAverageReviewScore(PDO $pdo, int $userID): ?float {
        try {
            // We join the reviews table to the items table using itemID.
            // Then we find all reviews left on items listed by this seller (i.userID).
            $stmt = $pdo->prepare('
                SELECT AVG(r.reviewScore) as avgScore 
                FROM reviews r
                INNER JOIN items i ON r.itemID = i.itemID
                WHERE i.userID = :uid
            ');
            $stmt->execute([':uid' => $userID]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $avg = $row['avgScore'] ?? null;
            return $avg !== null ? (float)$avg : null;
        } catch (Exception $e) {
            error_log('Average review error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get review count for a seller
     * @param PDO $pdo - Database connection
     * @param int $userID - The Seller's User ID
     * @return int - Number of reviews
     */
    function getReviewCount(PDO $pdo, int $userID): int {
        try {
            // Count all reviews left on items listed by this seller (i.userID).
            $stmt = $pdo->prepare('
                SELECT COUNT(r.reviewID) as cnt 
                FROM reviews r
                INNER JOIN items i ON r.itemID = i.itemID
                WHERE i.userID = :uid
            ');
            $stmt->execute([':uid' => $userID]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($row['cnt'] ?? 0);
        } catch (Exception $e) {
            error_log('Review count error: ' . $e->getMessage());
            return 0;
        }
    }
?>
