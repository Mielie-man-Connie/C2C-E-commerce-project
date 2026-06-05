<?php
    /* Admin actions: simple AJAX CRUD for admins */
    if (session_status() === PHP_SESSION_NONE) session_start();
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'Not logged in']); exit; }

    require_once __DIR__ . '/../data/database.php';

    /* Admin guard */
    $isAdmin = false;
    try {
        $s = $pdo->prepare('SELECT isAdmin FROM accounts WHERE userID=:id LIMIT 1');
        $s->execute([':id'=>(int)$_SESSION['user_id']]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        $isAdmin = !empty($row['isAdmin']);
    } catch (PDOException $e) {
        $isAdmin = ((int)$_SESSION['user_id'] === 1); // fallback: userID 1 is admin
    }
    if (!$isAdmin) { echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }

    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
    $table  = $body['table']  ?? '';
    $pk     = $body['pk']     ?? null;
    $data   = $body['data']   ?? [];

   /* Whitelisted tables and their primary key + editable columns */
    const TABLES = [
        'accounts'  => ['pk'=>'userID',    'cols'=>['username','email','name','surname','dob','mobile','zaID','locationID','profileDescription','isVerified','isAdmin','imageID']],
        'items'     => ['pk'=>'itemID',    'cols'=>['userID','itemName','itemDescription','itemPrice','itemsAvailable','locationID','deliveryType','imageID']],
        'locations' => ['pk'=>'locationID','cols'=>['province','city','suburb']],
        'orders'    => ['pk'=>'orderID',   'cols'=>['userID','itemID','orderDate','collectionDate']],
        'escrow'    => ['pk'=>'escrowID',  'cols'=>['orderID','escrowState','stateDate']],
        'reports'   => ['pk'=>'reportID',  'cols'=>['userID','reportTitle','reportDescription','reportedUserID','reportedItemID','response']],
        'audits'    => ['pk'=>'auditID',   'cols'=>['action','auditDate','userID','auditTitle','auditDescription']],
        'images'    => ['pk'=>'imageID',   'cols'=>['userID','imgData']],
        'reviews'   => ['pk'=>'reviewID',  'cols'=>['userID','itemID','reviewScore','reviewTitle','reviewDescription','reviewDate']]
    ];

    // Helper for consistent JSON responses from adminActions.php.
    function jsonResp(bool $ok, $payload = null, string $err = ''): void {
        echo json_encode($ok ? ['ok'=>true,'data'=>$payload] : ['ok'=>false,'error'=>$err]);
    }

    try {
        switch ($action) {

            case 'update':
                // Update a row in the requested table using only whitelisted columns.
                if (!$table || !isset(TABLES[$table]) || $pk === null) { jsonResp(false,null,'Bad request'); break; }
                $tableConfig = TABLES[$table];
                $safe = array_intersect_key($data, array_flip($tableConfig['cols']));
                if (empty($safe)) { jsonResp(false,null,'No valid fields'); break; }
                
                /* Fetch old values for audit trail */
                $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `{$tableConfig['pk']}`=:pk");
                $stmt->execute([':pk'=>$pk]);
                $oldRow = $stmt->fetch(PDO::FETCH_ASSOC);
                
                /* Build audit description showing what changed */
                $changes = [];
                foreach ($safe as $col => $newVal) {
                    $oldVal = $oldRow[$col] ?? null;
                    if ($oldVal !== $newVal) {
                        $oldVal = $oldVal === null ? '(null)' : (strlen($oldVal) > 50 ? substr($oldVal,0,50).'…' : $oldVal);
                        $newVal = $newVal === null ? '(null)' : (strlen($newVal) > 50 ? substr($newVal,0,50).'…' : $newVal);
                        $changes[] = "$col: '$oldVal' → '$newVal'";
                    }
                }
                
                /* Perform the update */
                $setsParts = [];
                $params = [];
                foreach (array_keys($safe) as $c) {
                    $setsParts[] = "`$c`=:$c";
                    $params[":" . $c] = $safe[$c];
                }
                $sets = implode(', ', $setsParts);
                $params[':pk'] = $pk;
                $pdo->prepare("UPDATE `{$table}` SET {$sets} WHERE `{$tableConfig['pk']}`=:pk")->execute($params);
                
                /* Log the audit */
                if ($changes) {
                    // Function = logAudit(), Class = data/database.php
                    logAudit($pdo, (int)$_SESSION['user_id'], 'modified', "$table: {$tableConfig['pk']}=$pk", implode(' | ', $changes));
                }
                jsonResp(true);
                break;

            case 'delete':
                // Delete a row and log the action. Special-case item deletes to also remove related images.
                if (!$table || !isset(TABLES[$table]) || $pk === null) { jsonResp(false,null,'Bad request'); break; }
                $tableConfig = TABLES[$table];
                
                /* Fetch the row before deleting for audit trail */
                $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `{$tableConfig['pk']}`=:pk");
                $stmt->execute([':pk'=>$pk]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $rowInfo = $row ? json_encode($row, JSON_UNESCAPED_UNICODE) : 'unknown';
                
                /* Handle special case: deleting items also deletes associated images */
                if ($table === 'items') {
                    $s = $pdo->prepare('SELECT imageID FROM items WHERE itemID=:id LIMIT 1');
                    $s->execute([':id'=>$pk]);
                    $r = $s->fetch(PDO::FETCH_ASSOC);
                    $ids = json_decode($r['imageID']??'[]', true);
                    if (is_array($ids) && $ids) {
                        $ph = implode(',', array_fill(0,count($ids),'?'));
                        $pdo->prepare("DELETE FROM images WHERE imageID IN ($ph)")->execute($ids);
                        // Function = logAudit(), Class = data/database.php
                        logAudit($pdo, (int)$_SESSION['user_id'], 'deleted', "$table: {$tableConfig['pk']}=$pk", "Deleted item #$pk and ".count($ids)." associated image(s)");
                    } else {
                        // Function = logAudit(), Class = data/database.php
                        logAudit($pdo, (int)$_SESSION['user_id'], 'deleted', "$table: {$tableConfig['pk']}=$pk", "Deleted item #$pk");
                    }
                } else {
                    // Function = logAudit(), Class = data/database.php
                    logAudit($pdo, (int)$_SESSION['user_id'], 'deleted', "$table: {$tableConfig['pk']}=$pk", "Deleted: $rowInfo");
                }
                
                $pdo->prepare("DELETE FROM `{$table}` WHERE `{$tableConfig['pk']}`=:pk")->execute([':pk'=>$pk]);
                jsonResp(true);
                break;

            case 'insert':
                // Insert a new row into the requested table using whitelisted columns.
                if (!$table || !isset(TABLES[$table])) { jsonResp(false,null,'Bad request'); break; }
                $tableConfig = TABLES[$table];
                $safe = array_intersect_key($data, array_flip($tableConfig['cols']));
                if (empty($safe)) { jsonResp(false,null,'No valid fields'); break; }

                try {
                    $colsParts = [];
                    $valsParts = [];
                    $params = [];
                    foreach (array_keys($safe) as $c) {
                        $colsParts[] = "`$c`";
                        $valsParts[] = ":$c";
                        $params[":" . $c] = $safe[$c];
                    }
                    $cols = implode(', ', $colsParts);
                    $vals = implode(', ', $valsParts);
                    $pdo->prepare("INSERT INTO `{$table}` ($cols) VALUES ($vals)")->execute($params);
                    $newId = $pdo->lastInsertId();
                    
                    /* Log the audit */
                    $rowInfo = json_encode($safe, JSON_UNESCAPED_UNICODE);
                    // Function = logAudit(), Class = data/database.php
                    logAudit($pdo, (int)$_SESSION['user_id'], 'created', "$table: {$tableConfig['pk']}=$newId", "Created: $rowInfo");
                    jsonResp(true, ['newId' => $newId]);
                } catch (PDOException $e) {
                    jsonResp(false, null, 'Insert failed: ' . $e->getMessage());
                }
                break;

            case 'resolve_report':
                $reportId = (int)($body['reportID'] ?? 0);
                $response = trim($body['response'] ?? '');
                if (!$reportId) { jsonResp(false,null,'Bad reportID'); break; }
                try {
                    $stmt = $pdo->prepare('SELECT response FROM reports WHERE reportID=:id LIMIT 1');
                    $stmt->execute([':id'=>$reportId]);
                    $oldReport = $stmt->fetch(PDO::FETCH_ASSOC);
                    $oldResponse = $oldReport['response'] ?? '(none)';

                    $pdo->prepare('UPDATE reports SET response=:r WHERE reportID=:id')
                        ->execute([':r'=>$response,':id'=>$reportId]);
                    // Function = logAudit(), Class = data/database.php
                    logAudit($pdo, (int)$_SESSION['user_id'], 'modified', "reports: reportID=$reportId", "Updated response: '$oldResponse' → '$response'");
                    jsonResp(true);
                } catch (PDOException $e) {
                    jsonResp(false, null, 'Add the response column first: ALTER TABLE reports ADD COLUMN response TEXT NULL;');
                }
                break;

            case 'upload_image':
                $imageID = (int)($body['imageID'] ?? 0);
                $imgData = trim($body['imgData'] ?? '');
                if (!$imageID || !$imgData) { jsonResp(false,null,'Bad imageID or imgData'); break; }
                try {
                    $pdo->prepare('UPDATE images SET imgData=:d WHERE imageID=:id')
                        ->execute([':d'=>$imgData,':id'=>$imageID]);
                    // Function = logAudit(), Class = data/database.php
                    logAudit($pdo, (int)$_SESSION['user_id'], 'modified', "images: imageID=$imageID", 'Uploaded new image data');
                    jsonResp(true);
                } catch (PDOException $e) {
                    jsonResp(false, null, 'Upload failed: ' . $e->getMessage());
                }
                break;

            default:
                jsonResp(false, null, 'Unknown action');
        }
    } catch (PDOException $e) {
        error_log('adminActions error: '.$e->getMessage());
        jsonResp(false, null, $e->getMessage());
    }
?>