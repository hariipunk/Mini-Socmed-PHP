<?php
session_start();
include('db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_data = json_decode(file_get_contents('php://input'), true);
    $action = $input_data['action'] ?? '';

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    if ($action === 'unlock') {
        $query_check_key = "SELECT * FROM `key` WHERE `user_id` = ?";
        $stmt_check_key = $conn->prepare($query_check_key);
        $stmt_check_key->bind_param("i", $user_id);
        $stmt_check_key->execute();
        $result_check_key = $stmt_check_key->get_result();

        if ($result_check_key->num_rows > 0) {
            $query_update_key = "UPDATE `key` SET `key_unlocked` = 1, `unlocked_at` = NOW() WHERE `user_id` = ?";
            $stmt_update_key = $conn->prepare($query_update_key);
            $stmt_update_key->bind_param("i", $user_id);
            $stmt_update_key->execute();

            echo json_encode(['status' => 'success', 'message' => 'Kunci berhasil disimpan!']);
        } else {
            $query_insert_key = "INSERT INTO `key` (`user_id`, `key_unlocked`) VALUES (?, 1)";
            $stmt_insert_key = $conn->prepare($query_insert_key);
            $stmt_insert_key->bind_param("i", $user_id);
            $stmt_insert_key->execute();

            echo json_encode(['status' => 'success', 'message' => 'Kunci berhasil disimpan!']);
        }

        $stmt_check_key->close();
        $stmt_update_key->close();
        $stmt_insert_key->close();
    } elseif ($action === 'get_key') {
        $query_get_key = "SELECT * FROM `key` WHERE `user_id` = ?";
        $stmt_get_key = $conn->prepare($query_get_key);
        $stmt_get_key->bind_param("i", $user_id);
        $stmt_get_key->execute();
        $result_get_key = $stmt_get_key->get_result();

        if ($result_get_key->num_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Kunci berhasil diterima!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Kunci tidak tersedia']);
        }

        $stmt_get_key->close();
    }

    $conn->close();
}
?>