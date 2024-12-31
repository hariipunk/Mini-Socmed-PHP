<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$contact_id = isset($_GET['contact_id']) ? intval($_GET['contact_id']) : 0;

$user_id = $_SESSION['user_id'];
$query_conversation = "
    SELECT messages.*, 
           sender.username AS sender_username, 
           receiver.username AS receiver_username, 
           sender.photo AS sender_photo, 
           receiver.photo AS receiver_photo
    FROM messages
    JOIN users AS sender ON messages.sender_id = sender.id
    JOIN users AS receiver ON messages.receiver_id = receiver.id
    WHERE (messages.sender_id = ? AND messages.receiver_id = ?) 
       OR (messages.sender_id = ? AND messages.receiver_id = ?)
    ORDER BY created_at ASC
";
$stmt = $conn->prepare($query_conversation);
$stmt->bind_param('iiii', $user_id, $contact_id, $contact_id, $user_id);
$stmt->execute();
$result_conversation = $stmt->get_result();

if ($contact_id) {
   $check_receiver_query = "SELECT id, status FROM messages 
                         WHERE sender_id = ? AND receiver_id = ? AND status = 0";
$stmt = $conn->prepare($check_receiver_query);
$stmt->bind_param('ii', $contact_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $message = $result->fetch_assoc();

        if ($message['status'] == 0) {
            $update_query = "UPDATE messages SET status = 1 
                             WHERE sender_id = ? AND receiver_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('ii', $contact_id, $user_id);
            $stmt->execute();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = htmlspecialchars(trim($_POST['message'])); 

    if (!empty($message)) {
        $query_send_message = "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
        $stmt_send_message = $conn->prepare($query_send_message);
        $stmt_send_message->bind_param('iis', $user_id, $contact_id, $message);

        if ($stmt_send_message->execute()) {
            $query_sender = "SELECT photo, username FROM users WHERE id = ?";
            $stmt_sender = $conn->prepare($query_sender);
            $stmt_sender->bind_param('i', $user_id);
            $stmt_sender->execute();
            $result_sender = $stmt_sender->get_result();
            $sender = $result_sender->fetch_assoc();

            echo json_encode([
                'status' => 'success',
                'message' => 'Message sent successfully',
                'sender_photo' => $sender['photo'],
                'sender_username' => $sender['username'],
                'message' => $message,
                'timestamp' => date('Y-m-d H:i'),
                'message_id' => $stmt_send_message->insert_id
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
        }
        exit;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
        exit;
    }
}

if (isset($_POST['delete_message_id'])) {
    $message_id = intval($_POST['delete_message_id']);
    
    $query_delete_message = "DELETE FROM messages WHERE id = ? AND sender_id = ?";
    $stmt_delete_message = $conn->prepare($query_delete_message);
    $stmt_delete_message->bind_param('ii', $message_id, $user_id);
    
    if ($stmt_delete_message->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Message deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete message']);
    }
    exit;
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $currentTime = time();
    $timeDifference = $currentTime - $time;

    $seconds = $timeDifference;
    $minutes = round($timeDifference / 60);
    $hours = round($timeDifference / 3600);
    $days = round($timeDifference / 86400);
    $weeks = round($timeDifference / 604800);
    $months = round($timeDifference / 2629440);
    $years = round($timeDifference / 31553280);

    if ($seconds < 60) {
        return "$seconds detik lalu";
    } elseif ($minutes < 60) {
        return "$minutes menit lalu";
    } elseif ($hours < 24) {
        return "$hours jam lalu";
    } elseif ($days < 7) {
        return "$days hari lalu";
    } elseif ($weeks < 4) {
        return "$weeks minggu lalu";
    } elseif ($months < 12) {
        return "$months bulan lalu";
    } else {
        return date('d M Y', $time);
    }
}
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
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="myprofile.php"><i class="fas fa-user"></i> My Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log Out</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<div class="container">
    <div class="conversation">
        <?php while ($message = $result_conversation->fetch_assoc()): ?>
            <div class="message <?= $message['sender_id'] == $user_id ? 'sender' : 'receiver' ?>" id="message-<?= $message['id'] ?>">
<?php 
    $sender_photo = isset($message['sender_photo']) && !empty($message['sender_photo']) ? $message['sender_photo'] : null;
?>
<?php if ($sender_photo): ?>
    <img src="<?= htmlspecialchars($sender_photo) ?>" alt="<?= htmlspecialchars($message['sender_username']) ?>'s profile" class="user-photo">
<?php else: ?>
    <i class="fas fa-user-circle user-photo"></i>
<?php endif; ?>
                <div class="message-details">
                    <strong><?= htmlspecialchars($message['sender_username']) ?></strong>
                    <br><?= nl2br(htmlspecialchars($message['message'])) ?></br>
                    <div class="message-footer">
                        <span><?= htmlspecialchars(timeAgo($message['created_at'])) ?></span>
                        <?php if ($message['sender_id'] == $user_id): ?>
                            <button class="delete-message" data-message-id="<?= $message['id'] ?>"><i class="fas fa-trash-alt"></i></button>
                        <?php endif; ?>
                    </div>
                       <div class="message-statusc"> <?php if ($message['status'] == 0): ?>
                            <i class="fa-solid fa-circle-check"></i> Unread
                        <?php elseif ($message['status'] == 1): ?>
                            <i class="fa-solid fa-check-double"></i> Read
                        <?php endif; ?>
                        </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <div class="new-message">
        <form method="POST" action="">
            <textarea name="message" id="message" rows="5" required></textarea>
            <button type="submit" class="button">Send Message</button>
        </form>
    </div>
</div>

<script>
    document.querySelectorAll('.delete-message').forEach(button => {
        button.addEventListener('click', function() {
            const messageId = this.getAttribute('data-message-id');
            
            fetch('conversation.php?contact_id=<?= $contact_id ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'delete_message_id=' + messageId
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('message-' + messageId).remove();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        });
    });

document.querySelector('form').addEventListener('submit', function(event) {
    event.preventDefault(); 

    var message = document.getElementById('message').value;
    var formData = new FormData();
    formData.append('message', message);

    // Kirim permintaan AJAX
    fetch('conversation.php?contact_id=<?= $contact_id ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json()) 
    .then(data => {
        if (data.status === 'success') {
            var conversation = document.querySelector('.conversation');
            var newMessage = document.createElement('div');
            newMessage.classList.add('message', 'sender');

            var userPhoto = data.sender_photo && data.sender_photo.trim() !== "" ? data.sender_photo : 'path/to/default/icon.png';  
            var userPhotoHTML = userPhoto !== 'path/to/default/icon.png' ? 
                `<img src="${userPhoto}" alt="${data.sender_username}'s profile" class="user-photo">` :
                `<i class="fas fa-user-circle user-photo"></i>`; 

            newMessage.innerHTML = `
                ${userPhotoHTML}
                <div class="message-details">
                    <strong>${data.sender_username}</strong><br>
                    ${data.message}
                    <div class="message-footer">
                        <span class="timestamp">${data.timestamp}</span>
                        <button class="delete-message" data-message-id="${data.message_id}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            `;
            conversation.appendChild(newMessage);

            var timestampElement = newMessage.querySelector('.timestamp');
            timestampElement.textContent = timeAgo(data.timestamp);

            document.getElementById('message').value = '';
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
});

function timeAgo(datetime) {
    const time = new Date(datetime).getTime();
    const currentTime = Date.now();
    const timeDifference = currentTime - time;

    const seconds = timeDifference / 1000;
    const minutes = seconds / 60;
    const hours = minutes / 60;
    const days = hours / 24;
    const weeks = days / 7;
    const months = days / 30;
    const years = days / 365;

    if (seconds < 60) {
        return `${Math.floor(seconds)} detik lalu`;
    } else if (minutes < 60) {
        return `${Math.floor(minutes)} menit lalu`;
    } else if (hours < 24) {
        return `${Math.floor(hours)} jam lalu`;
    } else if (days < 7) {
        return `${Math.floor(days)} hari lalu`;
    } else if (weeks < 4) {
        return `${Math.floor(weeks)} minggu lalu`;
    } else if (months < 12) {
        return `${Math.floor(months)} bulan lalu`;
    } else {
        return `${Math.floor(years)} tahun lalu`;
    }
}
</script>
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
</body>
</html>