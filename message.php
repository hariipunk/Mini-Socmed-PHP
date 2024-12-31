<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$query_users = "SELECT id, username, photo FROM users WHERE id != ?";
$stmt = $conn->prepare($query_users);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result_users = $stmt->get_result();

$query_messages = "
    SELECT messages.*, 
           sender.username AS sender_username, 
           receiver.username AS receiver_username, 
           sender.photo AS sender_photo, 
           receiver.photo AS receiver_photo
    FROM messages
    JOIN users AS sender ON messages.sender_id = sender.id
    JOIN users AS receiver ON messages.receiver_id = receiver.id
    WHERE messages.sender_id = ? OR messages.receiver_id = ?
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($query_messages);
$stmt->bind_param('ii', $user_id, $user_id);
$stmt->execute();
$result_messages = $stmt->get_result();

$shown_contacts = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comment</title>
    <link rel="stylesheet" href="stylemessage.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<header>
    <h1>PojoKan Chat</h1>
    <div class="profile">
        <i class="fa-solid fa-circle-user icon"></i>
        <div class="profile-menu">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="#contact"><i class="fas fa-envelope"></i> Contact</a>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="message.php"><i class="fas fa-envelope"></i> Message</a>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="myprofile.php"><i class="fas fa-user"></i> My Profile</a>
                
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log Out</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<div class="container">
    <?php 
    include('online.php');
    ?>
    <h2>Your Messages</h2>
    <div class="messages-list">
        <?php if ($result_messages->num_rows > 0): ?>
            <?php while ($message = $result_messages->fetch_assoc()): ?>
                <?php 
                    $contact_id = $message['sender_id'] == $user_id ? $message['receiver_id'] : $message['sender_id'];
                    if (in_array($contact_id, $shown_contacts)) {
                        continue;
                    }
                    $shown_contacts[] = $contact_id;

                    $contact_query = "SELECT username, photo, role, is_verified FROM users WHERE id = ?";
                    $contact_stmt = $conn->prepare($contact_query);
                    $contact_stmt->bind_param('i', $contact_id);
                    $contact_stmt->execute();
                    $contact_result = $contact_stmt->get_result();
                    $contact = $contact_result->fetch_assoc();
                ?>
                <a href="conversation.php?contact_id=<?= $contact_id ?>" class="message-link">
                    <div class="post">
                        <?php 
                            $photo = isset($contact['photo']) && !empty($contact['photo']) ? $contact['photo'] : null;
                        ?>
                        <?php if ($photo): ?>
                            <img src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($contact['username']) ?>'s profile" class="user-photo">
                        <?php else: ?>
                            <i class="fas fa-user-circle user-photo"></i>
                        <?php endif; ?>
                        <div class="user-details">
                            <strong><?= htmlspecialchars($contact['username']) ?>
      <?php 
    if ($contact['is_verified'] == 1) {
        if ($contact['role'] == 'admin') {
            // Centang emas untuk admin
            echo ' <span class="verified admin"><i class="fas fa-check-circle"></i></span>';
        } else {
            // Centang biru untuk user biasa
            echo ' <span class="verified user"><i class="fas fa-check-circle"></i></span>';
        }
    }
        ?></strong>
                            <p class="message-preview"><?= htmlspecialchars(substr($message['message'], 0, 50)) ?>...</p>
                        </div>
                        <p class="post-footer"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($message['created_at']))) ?></p>

                       <p class="post-statusc"> <?php if ($message['status'] == 0): ?>
                            <i class="fa-solid fa-circle-check"></i> Unread
                        <?php elseif ($message['status'] == 1): ?>
                            <i class="fa-solid fa-check-double"></i> Read
                        <?php endif; ?></p>
                    </div>
                </a>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No messages yet.</p>
        <?php endif; ?>
    </div>
</div>
</body>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const profile = document.querySelector('.profile');
        const profileMenu = document.querySelector('.profile-menu');
        
        profile.addEventListener('click', function () {
            if (profileMenu.style.display === 'block') {
                profileMenu.style.display = 'none';
            } else {
                profileMenu.style.display = 'block';
            }
        });
        
        document.addEventListener('click', function (event) {
            if (!profile.contains(event.target)) {
                profileMenu.style.display = 'none';
            }
        });
    });
</script>
</html>