<?php
require_once "config.php";
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (isset($_POST['action'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];

    // Debug log
   // error_log("AJAX Action: $action on ID $id");

    if ($action === "toggle_receive") {
        $receive = intval($_POST['receive']);
        $stmt = $pdo->prepare("UPDATE users SET receive = :receive WHERE id = :id");
        $stmt->execute([':receive' => $receive, ':id' => $id]);
        echo "Rows affected: " . $stmt->rowCount();
    }

    if ($action === "remove_user") {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo "Rows affected: " . $stmt->rowCount();
    }
}

