<?php
session_start();
require 'db.php';

if (isset($_POST['post_id']) && isset($_SESSION['user_id'])) {
    $postId = intval($_POST['post_id']);
    $userId = $_SESSION['user_id'];

    $query = "SELECT status, image, video FROM posts WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $result = $stmt->get_result();
    $post = $result->fetch_assoc();

    if ($post) {
        $query = "INSERT INTO posts (user_id, shared_post_id, shared_by_user_id, created_at) 
                  VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iii', $userId, $postId, $userId);
        $stmt->execute();
        
        echo "Post shared successfully."; 
    } else {
        echo "Post not found.";
    }
} else {
    echo "Unauthorized access.";
}
?>