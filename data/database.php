<?php
    /* Database connection and helpers */
    $hostName = "localhost";
    $dbUsername = "root";
    $dbPassword = "";
    $dbName = "tradesa_db";

    /* Creates connection using PDO */
    try {
        $pdo = new PDO("mysql:host=$hostName;dbname=$dbName", $dbUsername, $dbPassword);
        
        /* Ensures errors are reported */
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
    /* Optional: echo "Connected successfully"; */
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

    /* Audit logging helper */
    function logAudit(PDO $pdo, ?int $userId, string $action, string $auditTitle, string $auditDescription): void {
        try {
            $pdo->prepare("
                INSERT INTO audits (userID, action, auditTitle, auditDescription, auditDate)
                VALUES (:uid, :act, :title, :desc, NOW())
            ")->execute([
                ':uid'   => $userId,
                ':act'   => $action,
                ':title' => $auditTitle,
                ':desc'  => $auditDescription
            ]);
        } catch (PDOException $e) {
            /* Silently fail - don't break the main action if audit logging fails */
            error_log("Audit logging error: " . $e->getMessage());
        }
    }
?>