<?php
session_start();
include('db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_status'])) {
    $post_id = $_POST['post_id'];

    $check_query = "SELECT * FROM posts WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('ii', $post_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $post = $result->fetch_assoc();

        if (!empty($post['image']) && file_exists($post['image'])) {
            unlink($post['image']);
        }

        if (!empty($post['video']) && file_exists($post['video'])) {
            unlink($post['video']);
        }
        
        if (!empty($post['audio']) && file_exists($post['audio'])) {
            unlink($post['audio']);
        }

        $delete_replylikes_query = "
            DELETE rl 
            FROM replylikes rl
            INNER JOIN comments c ON rl.comment_id = c.id
            WHERE c.post_id = ?";
        $stmt = $conn->prepare($delete_replylikes_query);
        $stmt->bind_param('i', $post_id);
        $stmt->execute();

        $delete_comments_query = "DELETE FROM comments WHERE post_id = ?";
        $stmt = $conn->prepare($delete_comments_query);
        $stmt->bind_param('i', $post_id);
        $stmt->execute();

        $delete_notifications_query = "DELETE FROM notifications WHERE post_id = ?";
        $stmt = $conn->prepare($delete_notifications_query);
        $stmt->bind_param('i', $post_id);
        $stmt->execute();

        $delete_post_query = "DELETE FROM posts WHERE id = ?";
        $stmt = $conn->prepare($delete_post_query);
        $stmt->bind_param('i', $post_id);
        $stmt->execute();

        echo "Status, image, video, comments, replylikes, and related notifications successfully deleted.";
    } else {
        echo "You are not authorized to delete this post.";
    }
} 

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_status'])) {
        $post_id = $_POST['post_id'];
        $status = $_POST['status'];
        $query = "UPDATE posts SET status = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sii', $status, $post_id, $_SESSION['user_id']);
        $stmt->execute();
        header('Location: index.php');
        exit;
    }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_status'])) {
    
        $status = htmlspecialchars(trim($_POST['status']));
        $user_id = $_SESSION['user_id'];
        $visibility = $_POST['visibility'];
        $image_path = null;
        $video_path = null;
        $audio_path = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];

            if (in_array($file['type'], $allowed_types)) {
                $username = basename(getUsernameById($user_id, $conn));
                $upload_dir = "uploads/{$username}/photo/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $image_filename = uniqid("photo_", true) . rand(1000, 9999) . '.' . $extension;
                $image_path = $upload_dir . $image_filename;

                if (!move_uploaded_file($file['tmp_name'], $image_path)) {
                    die("File upload gagal.");
                }
            }
        }

        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['video'];
            $allowed_video_types = ['video/mp4', 'video/webm', 'video/ogg'];

            if (in_array($file['type'], $allowed_video_types)) {
                $username = basename(getUsernameById($user_id, $conn));
                $upload_dir = "uploads/{$username}/video/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $video_filename = uniqid("video_", true) . rand(1000, 9999) . '.' . $extension;
                $video_path = $upload_dir . $video_filename;

                if (!move_uploaded_file($file['tmp_name'], $video_path)) {
                    die("Gagal mengunggah video.");
                }
            }
        }

        if (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['audio'];
            $allowed_audio_types = ['audio/mpeg', 'audio/mp3', 'audio/wav'];

            if (in_array($file['type'], $allowed_audio_types)) {
                $username = basename(getUsernameById($user_id, $conn));
                $upload_dir = "uploads/{$username}/audio/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $audio_filename = uniqid("audio_", true) . rand(1000, 9999) . '.' . $extension;
                $audio_path = $upload_dir . $audio_filename;

                if (!move_uploaded_file($file['tmp_name'], $audio_path)) {
                    die("Gagal mengunggah audio.");
                }
            } else {
                die("Tipe file audio tidak valid.");
            }
        }

        $query = "INSERT INTO posts (user_id, status, visibility, image, video, audio) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('isssss', $user_id, $status, $visibility, $image_path, $video_path, $audio_path);
        $stmt->execute();
        
        $post_id = $stmt->insert_id;
        
$query_check_auto_like = "SELECT auto_like FROM users WHERE id = ?";
$stmt_check_auto_like = $conn->prepare($query_check_auto_like);
$stmt_check_auto_like->bind_param("i", $user_id);
$stmt_check_auto_like->execute();
$result_check = $stmt_check_auto_like->get_result();
$user_data = $result_check->fetch_assoc();

if ($user_data['auto_like']) {
    $query_all_users = "SELECT id FROM users";
    $stmt_all_users = $conn->prepare($query_all_users);
    $stmt_all_users->execute();
    $result_all_users = $stmt_all_users->get_result();

    while ($target_user = $result_all_users->fetch_assoc()) {
        $target_user_id = $target_user['id'];

        $check_like_query = "SELECT * FROM likes WHERE user_id = ? AND post_id = ?";
        $stmt_check_like = $conn->prepare($check_like_query);
        $stmt_check_like->bind_param("ii", $target_user_id, $post_id);
        $stmt_check_like->execute();
        $like_result = $stmt_check_like->get_result();
        if ($like_result->num_rows === 0) {
            $like_query = "INSERT INTO likes (user_id, post_id) VALUES (?, ?)";
            $stmt_like = $conn->prepare($like_query);
            $stmt_like->bind_param("ii", $target_user_id, $post_id);
            $stmt_like->execute();
        }
    }
}
        header('Location: index.php');
        exit;
    }
}


if (isset($_POST['following_id'])) {
    header('Content-Type: application/json');

    if (!is_numeric($_POST['following_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid following_id']);
        exit;
    }

    $follower_id = $_SESSION['user_id'];
    $following_id = (int)$_POST['following_id'];

    if ($follower_id === $following_id) {
        echo json_encode(['status' => 'error', 'message' => 'Anda tidak dapat mengikuti diri sendiri']);
        exit;
    }

    $check_query = "SELECT * FROM follows WHERE follower_id = ? AND following_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('ii', $follower_id, $following_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $insert_query = "INSERT INTO follows (follower_id, following_id) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('ii', $follower_id, $following_id);

        if ($stmt->execute()) {
            $status = 'followed';
  
            $followed_user_query = "SELECT username FROM users WHERE id = ?";
            $stmt = $conn->prepare($followed_user_query);
            $stmt->bind_param('i', $following_id);
            $stmt->execute();
            $followed_result = $stmt->get_result();
            $followed_user = $followed_result->fetch_assoc();
            $followed_user_username = $followed_user['username'];

            $liker_query = "SELECT username FROM users WHERE id = ?";
            $stmt = $conn->prepare($liker_query);
            $stmt->bind_param('i', $follower_id);
            $stmt->execute();
            $liker_result = $stmt->get_result();
            $liker = $liker_result->fetch_assoc();
            $liker_username = $liker['username'];

            $notification_message = "{$liker_username} mengikuti Anda.";

            $insert_notification = "INSERT INTO notifications (recipient_id, sender_id, message) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_notification);
            $stmt->bind_param('iis', $following_id, $follower_id, $notification_message);
            $stmt->execute();

        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to follow']);
            exit;
        }
    } else {
        $delete_query = "DELETE FROM follows WHERE follower_id = ? AND following_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('ii', $follower_id, $following_id);

        if ($stmt->execute()) {
            $status = 'unfollowed';
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to unfollow']);
            exit;
        }
    }

    $count_query = "SELECT COUNT(*) AS total_followers FROM follows WHERE following_id = ?";
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param('i', $following_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $follower_count = $row['total_followers'];

    echo json_encode([
        'status' => $status,
        'follower_count' => $follower_count
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['like_post'])) {
    $post_id = $_POST['post_id'];
    $user_id = $_SESSION['user_id'];

    $check_query = "SELECT * FROM likes WHERE user_id = ? AND post_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('ii', $user_id, $post_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
    	
        $post_owner_query = "SELECT posts.user_id, users.username 
                             FROM posts 
                             JOIN users ON posts.user_id = users.id 
                             WHERE posts.id = ?";
        $stmt = $conn->prepare($post_owner_query);
        $stmt->bind_param('i', $post_id);
        $stmt->execute();
        $post_owner_result = $stmt->get_result();
        $post_owner = $post_owner_result->fetch_assoc();
        
        $post_owner_username = $post_owner['username'];

        $liker_query = "SELECT username FROM users WHERE id = ?";
        $stmt = $conn->prepare($liker_query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $liker_result = $stmt->get_result();
        $liker = $liker_result->fetch_assoc();
        
        $liker_username = $liker['username']; 
        
        if ($post_owner['user_id'] != $user_id) { 
            $notification_message = "{$liker_username} menyukai status Anda.";
            $insert_notification = "INSERT INTO notifications (recipient_id, sender_id, post_id, message) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_notification);
            $stmt->bind_param('iiis', $post_owner['user_id'], $user_id, $post_id, $notification_message);
            $stmt->execute();
        }
    
        $insert_query = "INSERT INTO likes (user_id, post_id) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('ii', $user_id, $post_id);
        $stmt->execute();
        $status = 'liked';
    } else {
        $delete_query = "DELETE FROM likes WHERE user_id = ? AND post_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('ii', $user_id, $post_id);
        $stmt->execute();
        $status = 'unliked';
    }

    $like_count = getLikeCount($post_id, $conn);

    echo json_encode([
        'status' => $status,
        'like_count' => $like_count
    ]);
    exit;
}

    $notifications_query= "SELECT * FROM notifications WHERE recipient_id = ? AND is_read = 0 ORDER BY created_at DESC";
    $stmt = $conn->prepare($notifications_query);
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $notifications_result = $stmt->get_result();
    $notifications = $notifications_result->fetch_all(MYSQLI_ASSOC);

function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes      = round($seconds / 60);           
    $hours        = round($seconds / 3600);     
    $days         = round($seconds / 86400);       
    $weeks        = round($seconds / 604800);  
    $months       = round($seconds / 2629440);
    $years        = round($seconds / 31553280);   
    
    if ($seconds <= 60) {
        return "Just Now";
    } else if ($minutes <= 60) {
        return "$minutes min";
    } else if ($hours <= 24) {
        return "$hours hours";
    } else if ($days <= 7) {
        return "$days days";
    } else if ($weeks <= 4.3) {
        return "$weeks weeks";
    } else if ($months <= 12) {
        return "$months months";
    } else {
        return "$years years";
    }
}

function getLikeCount($post_id, $conn) {
    $query = "SELECT COUNT(*) AS like_count FROM likes WHERE post_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['like_count'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_notifications_read'])) {
    $mark_read_query = "UPDATE notifications SET is_read = 1 WHERE recipient_id = ?";
    $stmt = $conn->prepare($mark_read_query);
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();

    echo json_encode(['status' => 'success']);
    exit;
}

function isLiked($post_id, $user_id, $conn) {
    $query = "SELECT * FROM likes WHERE post_id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $post_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

function getCommentCount($post_id, $conn) {
    $query = "SELECT COUNT(*) AS comment_count FROM comments WHERE post_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['comment_count'];
}

function isFollowing($follower_id, $following_id, $conn) {
    $query = "SELECT * FROM follows WHERE follower_id = ? AND following_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $follower_id, $following_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

function getFollowerCount($userId, $conn) {
    $query = "SELECT COUNT(*) AS follower_count FROM follows WHERE following_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['follower_count'];
}

function potong_teks($teks, $panjang = 200) {
    if (strlen($teks) <= $panjang) {
        return $teks;
    }
    return substr($teks, 0, $panjang) . '... <a href="#" class="baca-selengkapnya">Baca Selengkapnya</a>';
}

function getUsernameById($user_id, $conn) {
    $query = "SELECT username FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['username'] ?? 'unknown';
}

$user_id = $_SESSION['user_id']; 
$query = "SELECT COUNT(*) AS new_messages FROM messages WHERE receiver_id = ? AND status = 0";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$new_messages = $data['new_messages'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PojoKan</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

</head>
<body>
<header>
    <h1>PojoKan</h1>
    <div class="right-icons">
        <div class="mobile-icons">
        	<a href="music.php" id="toggle-link" class="mobile-link-v">
    <i class="fa-solid" id="toggle-icon"></i> 
    <span id="toggle-text">SONG</span>
            </a>
            
<?php if (isset($_SESSION['user_id'])): ?>            
        <a href="#" class="mobile-link" id="notificationBell">
            <i class="fa-solid fa-bell" style="position: relative;" data-unread="<?php echo count($notifications); ?>"></i>
        </a>
<div id="notificationsModal" class="modal-notif">
    <div class="modal-content">
        <div class="modal-header">
            <span class="close-btn">&times;</span>
            <h5>Notifikasi</h5>
        </div>
        <div class="modal-body">
            <?php if (!empty($notifications)): ?>
                <ul>
                    <?php foreach ($notifications as $notification): ?>
                        <li>
                            <?php if (!empty($notification['link'])): ?>
                                <a href="<?php echo htmlspecialchars($notification['link']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($notification['message']); ?>
                            <?php endif; ?>
                            <br><small><?php echo date('d M Y H:i', strtotime($notification['created_at'])); ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Tidak ada notifikasi baru.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
        </div>
        <div class="profile">
            <i class="fa-solid fa-circle-user icon"></i>
            <div class="profile-menu">
            	<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            	<a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="cheat.php"><i class="fas fa-tools"></i> Cheat</a>
            	<?php endif; ?>
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="#contact"><i class="fas fa-envelope"></i> Contact</a>
                    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                	<a href="message.php" class="message-icon">
    <i class="fas fa-envelope"></i> Message
    <?php if ($new_messages > 0): ?>
        <span class="notification-badge"><?= $new_messages; ?></span>
    <?php endif; ?>
</a>
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="tools.php"><i class="fas fa-tools"></i> Tools</a>
                    <a href="myprofile.php"><i class="fas fa-user"></i> My Profile</a>
                    <a href="insight.php"><i class="fas fa-chart-line"></i> Insight</a>
                    <a href="progress.php"><i class="fas fa-tasks"></i> Progress</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log Out</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
<div class="container">
<?php if (isset($_SESSION['user_id'])): ?>
    <?php
        $user_id = $_SESSION['user_id'];
        
        $query = "SELECT is_banned,    ban_message FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($is_banned, $ban_message);
        $stmt->fetch();
        $stmt->close();

        if ($is_banned == 0):
    ?>
            <div class="new-post">
                <form action="index.php" method="POST" enctype="multipart/form-data">
                    <textarea name="status" placeholder="What's on your mind?" required></textarea>
                    <div class="upload-icons-container">
                        <label for="upload-video" class="upload-icon">
                            <i class="fas fa-video"></i>
                        </label>
                        <input type="file" id="upload-video" name="video" accept="video/mp4, video/webm, video/ogg" style="display: none;">

                        <label for="upload-audio" class="upload-icon">
                            <i class="fas fa-music"></i>
                        </label>
                        <input type="file" id="upload-audio" name="audio" accept="audio/mpeg, audio/mp3, audio/wav" style="display: none;">            

                        <label for="upload-image" class="upload-icon">
                            <i class="fas fa-image"></i>
                        </label>
                        <input type="file" id="upload-image" name="image" accept="image/png, image/jpeg, image/jpg, image/gif" style="display: none;">
                    </div>
                    <div id="preview-container"></div>
            <?php
                $user_id = $_SESSION['user_id'];
                $query = "SELECT is_private FROM users WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->bind_result($is_private);
                $stmt->fetch();
                $stmt->close();
                
                $visibility = $is_private ? 'followers_only' : 'public';
            ?>           
                    <input type="hidden" name="visibility" value="<?php echo $visibility; ?>">
                    <div style="position: relative; height: 10px; width: 100%;" id="loading-bar" class="hidden">
                        <div class="progress-bar"></div>
                    </div>
                    <button type="submit" name="submit_status" class="button">Post</button>
                </form>
            </div>
    <?php else: ?>
    <p style="color: red; text-align: center; font-size: 16px; margin-top: 50px;"><?= htmlspecialchars($ban_message); ?></p>
    <?php endif; ?>
<?php endif; ?>
<?php
$query = "SELECT is_private FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($is_private);
$stmt->fetch();
$stmt->close();

if ($is_private == 1) {
    $query = "SELECT posts.*, users.username, users.fullname, users.photo, users.is_verified, users.role
              FROM posts
              JOIN users ON posts.user_id = users.id
              WHERE (posts.visibility = 'public'
                   OR (posts.visibility = 'followers_only' 
                       AND EXISTS (
                           SELECT 1 FROM follows WHERE follower_id = ? AND user_id = posts.user_id)))
              AND (posts.user_id = ? OR posts.visibility != 'followers_only')
              ORDER BY posts.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $_SESSION['user_id'], $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $query = "SELECT posts.*, users.username, users.fullname, users.photo, users.is_verified, users.role
              FROM posts
              JOIN users ON posts.user_id = users.id
              WHERE (posts.visibility = 'public' 
                     OR (posts.visibility = 'followers_only' 
                         AND EXISTS (
                             SELECT 1 
                             FROM follows 
                             WHERE follower_id = ? 
                             AND user_id = posts.user_id)))
              AND (posts.user_id IN (
                      SELECT following_id 
                      FROM follows 
                      WHERE follower_id = ?)
                   OR posts.visibility = 'public')
              ORDER BY posts.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $_SESSION['user_id'], $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
}  
?>
        <?php while ($post = $result->fetch_assoc()): ?>
        <div class="post">
        <?php if ($post['shared_post_id']): 
            $query = "SELECT posts.*, users.username AS original_username, users.fullname AS original_fullname, users.photo AS original_photo
                      FROM posts 
                      JOIN users ON posts.user_id = users.id 
                      WHERE posts.id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $post['shared_post_id']);
            $stmt->execute();
            $sharedResult = $stmt->get_result();
            $originalPost = $sharedResult->fetch_assoc();
        ?>
            <div class="user-info">
                <div class="user-photo">
                <a href="dashboard.php?user_id=<?php echo htmlspecialchars($post['user_id']); ?>">
                    <?php if (!empty($post['photo']) && file_exists($post['photo'])): ?>
                        <img src="<?php echo htmlspecialchars($post['photo']); ?>" alt="User Photo" class="user-photo">
                    <?php else: ?>
                        <i class="fa-solid fa-circle-user icon-default"></i>
                    <?php endif; ?>
                </a>
                </div>
                <div class="user-details">
                    <h2><a href="dashboard.php?user_id=<?php echo htmlspecialchars($post['user_id']); ?>" class="no-style-link">
                        <?php echo htmlspecialchars($post['fullname']); ?>
                    </a></h2>
                    <span class="username">@<?php echo htmlspecialchars($post['username']); ?></span> 
                </div>
                <?php
                $followerCount = getFollowerCount($post['user_id'], $conn);
              
                $isLoggedIn = isset($_SESSION['user_id']);
               
                $isSelf = $isLoggedIn && $_SESSION['user_id'] == $post['user_id'];
                ?>

                <?php if (!$isSelf): ?>
                    <form action="index.php" method="POST" class="follow-form">
                        <input type="hidden" name="following_id" value="<?php echo $post['user_id']; ?>">
                        <button type="submit" 
                                name="follow_user" 
                                class="follow-button <?php echo $isLoggedIn && isFollowing($_SESSION['user_id'], $post['user_id'], $conn) ? 'followed' : ''; ?>" 
                                data-following-id="<?php echo $post['user_id']; ?>" 
                                <?php echo !$isLoggedIn ? 'disabled' : ''; ?>>
                            <i class="<?php echo $isLoggedIn && isFollowing($_SESSION['user_id'], $post['user_id'], $conn) ? 'fas fa-user-check' : 'fas fa-user-plus'; ?>"></i>
                            <span class="follower-count" data-following-id="<?php echo $post['user_id']; ?>">
                                <?php echo $followerCount; ?>
                            </span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <p id="post_<?= $post['id']; ?>" data-teks-asli="<?= htmlspecialchars($post['status']); ?>">
                <?= potong_teks(str_replace("\n", '<br>', htmlspecialchars($post['status']))); ?>
            </p>
            <div class="post">
                <div class="user-info">
                    <div class="user-photo">
                        <a href="dashboard.php?user_id=<?php echo htmlspecialchars($originalPost['user_id']); ?>">
                            <?php if (!empty($originalPost['original_photo']) && file_exists($originalPost['original_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($originalPost['original_photo']); ?>" alt="User Photo" class="user-photo">
                            <?php else: ?>
                                <i class="fa-solid fa-circle-user icon-default"></i>
                            <?php endif; ?>
                        </a>
                    </div>
                    <div class="user-details">
                        <h2><a href="dashboard.php?user_id=<?php echo htmlspecialchars($originalPost['user_id']); ?>" class="no-style-link">
                            <?php echo htmlspecialchars($originalPost['original_fullname']); ?>
                        </a></h2>
                        <span class="username">@<?php echo htmlspecialchars($originalPost['original_username']); ?></span> 
                    </div>
                    <?php
                    $followerCount = getFollowerCount($originalPost['user_id'], $conn);
        
                    $isLoggedIn = isset($_SESSION['user_id']);
                    
                    $isSelf = $isLoggedIn && $_SESSION['user_id'] == $originalPost['user_id'];
                    ?>
                
                    <?php if (!$isSelf): ?>
                        <form action="index.php" method="POST" class="follow-form">
                            <input type="hidden" name="following_id" value="<?php echo $originalPost['user_id']; ?>">
                            <button type="submit" 
                                    name="follow_user" 
                                    class="follow-button <?php echo $isLoggedIn && isFollowing($_SESSION['user_id'], $originalPost['user_id'], $conn) ? 'followed' : ''; ?>" 
                                    data-following-id="<?php echo $originalPost['user_id']; ?>" 
                                    <?php echo !$isLoggedIn ? 'disabled' : ''; ?>>
                                <i class="<?php echo $isLoggedIn && isFollowing($_SESSION['user_id'], $originalPost['user_id'], $conn) ? 'fas fa-user-check' : 'fas fa-user-plus'; ?>"></i>
                                <span class="follower-count" data-following-id="<?php echo $originalPost['user_id']; ?>">
                                    <?php echo $followerCount; ?>
                                </span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <p><?php echo nl2br(htmlspecialchars($originalPost['status'])); ?></p>
                <?php if (!empty($originalPost['image']) && file_exists($originalPost['image'])): ?>
                    <div class="post-image">
                        <img src="<?php echo htmlspecialchars($originalPost['image']); ?>" alt="Post Image">
                    </div>
                <?php endif; ?>
                <?php if (!empty($originalPost['video']) && file_exists($originalPost['video'])): ?>
                    <div class="post-video">
                        <video controls>
                            <source src="<?php echo htmlspecialchars($originalPost['video']); ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                <?php endif; ?>
                <?php if (!empty($originalPost['audio']) && file_exists($originalPost['audio'])): ?>
                <div class="post-audio">
    <div class="control-button" onclick="togglePlay(this)">
        <i class="fas fa-play"></i> 
    </div>

    <div class="progress-container">
        <div class="progress-bar" onclick="setProgress(event, this)">
            <div class="progress-bar-inner"></div> <!-- Inner bar untuk progress -->
        </div>

        <div class="time-container">
            <span class="current-time">0:00</span> / <span class="total-duration">0:00</span>
        </div>
    </div>

    <audio id="audio-<?php echo $originalPost['id']; ?>">
        <source src="<?php echo htmlspecialchars($originalPost['audio']); ?>" type="audio/mpeg">
        Your browser does not support the audio element.
    </audio>
        <div class="visualizer" id="visualizer">
        <div class="bar"></div>
        <div class="bar"></div>
        <div class="bar"></div>
        <div class="bar"></div>
        <div class="bar"></div>
        </div>
                </div>
                <?php endif; ?>
               
            </div>
            <div id="loginModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn">&times;</span>
                    You must log in to like a post.
                </div>
            </div>
            <div class="post-footer-wrapper">
                <div class="like-footer">
                    <form action="index.php" method="POST" class="like-form">
                        <button type="button" class="like-button" style="background: none; border: none;">
                            <i class="fas fa-thumbs-up <?php echo (isset($_SESSION['user_id']) && isLiked($post['id'], $_SESSION['user_id'], $conn)) ? 'liked' : ''; ?>"></i>
                            <span><?php echo getLikeCount($post['id'], $conn); ?> Likes</span>
                        </button>
                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                    </form>
                </div>
            
                <div class="comment-footer">
                <form action="comment.php" method="GET" class="comment-form">
                    <button type="submit" class="comment-button" style="background: none; border: none;">
                        <i class="fas fa-comment"></i>
                                <span><?php echo getCommentCount($post['id'], $conn); ?> Comments</span>
                    </button>
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                </form>
                </div>
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $post['user_id']): ?>
                        <button class="message-button" style="background: none; border: none;" onclick="window.location.href='conversation.php?contact_id=<?= $post['user_id']; ?>'">
                            <i class="fas fa-envelope"></i> 
                        </button>
                    <?php endif; ?>
                    <div class="post-footer">
                        <?php echo timeAgo($post['created_at']); ?>
                    </div>
                    <div class="wedit-wapus">
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $post['user_id']): ?>
                       <button class="edit-button" style="background: none; border: none;"  data-post-id="<?= $post['id']; ?>"><i class="fas fa-edit"></i></button>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $post['user_id']): ?>
                        <!-- Menambahkan tombol hapus di post -->
                    <form action="index.php" method="POST" style="display: inline;">
                        <button type="button" class="delete-button" style="background: none; border: none;" data-post-id="<?php echo $post['id']; ?>">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                    </div>
            </div>
        <?php else: ?>
            <div class="user-info">
                <div class="user-photo">
                <a href="dashboard.php?user_id=<?php echo htmlspecialchars($post['user_id']); ?>">
                    <?php if (!empty($post['photo']) && file_exists($post['photo'])): ?>
                        <img src="<?php echo htmlspecialchars($post['photo']); ?>" alt="User Photo" class="user-photo">
                    <?php else: ?>
                        <i class="fa-solid fa-circle-user icon-default"></i> <!-- Ikon default jika tidak ada foto -->
                    <?php endif; ?>
                </a>
                </div>
                <div class="user-details">
                    <h2><a href="dashboard.php?user_id=<?php echo htmlspecialchars($post['user_id']); ?>" class="no-style-link">
    <?php 
        echo htmlspecialchars($post['fullname']);
        
    if ($post['is_verified'] == 1) {
        if ($post['role'] == 'admin') {
            // Centang emas untuk admin
            echo ' <span class="verified admin"><i class="fas fa-check-circle"></i></span>';
        } else {
            // Centang biru untuk user biasa
            echo ' <span class="verified user"><i class="fas fa-check-circle"></i></span>';
        }
    }
    ?>
</a>
                    </h2>
                    <span class="username">@<?php echo htmlspecialchars($post['username']); ?></span> 
                </div>
                <?php
                $followerCount = getFollowerCount($post['user_id'], $conn);
                
                $isLoggedIn = isset($_SESSION['user_id']);
                
                $isSelf = $isLoggedIn && $_SESSION['user_id'] == $post['user_id'];
                ?>
                
                <?php if (!$isSelf): ?>
                    <form action="index.php" method="POST" class="follow-form">
                        <input type="hidden" name="following_id" value="<?php echo $post['user_id']; ?>">
                        <button type="submit" 
                                name="follow_user" 
                                class="follow-button <?php echo $isLoggedIn && isFollowing($_SESSION['user_id'], $post['user_id'], $conn) ? 'followed' : ''; ?>" 
                                data-following-id="<?php echo $post['user_id']; ?>" 
                                <?php echo !$isLoggedIn ? 'disabled' : ''; ?>>
                            <i class="<?php echo $isLoggedIn && isFollowing($_SESSION['user_id'], $post['user_id'], $conn) ? 'fas fa-user-check' : 'fas fa-user-plus'; ?>"></i>
                            <span class="follower-count" data-following-id="<?php echo $post['user_id']; ?>">
                                <?php echo $followerCount; ?>
                            </span>
                        </button>
                    </form>
                <?php endif; ?>
                <div class="envelope-message">
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $post['user_id']): ?>
                        <button class="message-button" style="background: none; border: none;" onclick="window.location.href='conversation.php?contact_id=<?= $post['user_id']; ?>'">
                            <i class="fas fa-envelope"></i> 
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <p id="post_<?= $post['id']; ?>" data-teks-asli="<?= htmlspecialchars($post['status']); ?>">
                <?= potong_teks(str_replace("\n", '<br>', htmlspecialchars($post['status']))); ?>
            </p>
            <?php if (!empty($post['image']) && file_exists($post['image'])): ?>
                <div class="post-image">
                    <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="Post Image" style="max-width: 100%; height: auto;">
                </div>
            <?php endif; ?>
            <?php if (!empty($post['video']) && file_exists($post['video'])): ?>
                <div class="post-video">
                    <video controls style="max-width: 100%; height: auto;">
                        <source src="<?php echo htmlspecialchars($post['video']); ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
            <?php endif; ?>
            <?php if (!empty($post['audio']) && file_exists($post['audio'])): ?>
            <div class="post-audio">
                <div class="control-button" onclick="togglePlay(this)">
                    <i class="fas fa-play"></i>
                </div>
            
                <div class="progress-container">
                    <div class="progress-bar" onclick="setProgress(event, this)">
                        <div class="progress-bar-inner"></div> <!-- Inner bar untuk progress -->
                    </div>
                  
                    <div class="time-container">
                        <span class="current-time">0:00</span> / <span class="total-duration">0:00</span>
                    </div>
                </div>
            
                <audio id="audio-<?php echo $post['id']; ?>">
                    <source src="<?php echo htmlspecialchars($post['audio']); ?>" type="audio/mpeg">
                    Your browser does not support the audio element.
                </audio>
                    <div class="visualizer" id="visualizer">
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                    </div>
            </div>
            <?php endif; ?>
            
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            You must log in to like a post.
        </div>
    </div>
    <div class="post-footer-wrapper">
        <div class="like-footer">
            <form action="index.php" method="POST" class="like-form">
                <button type="button" class="like-button" style="background: none; border: none;">
                    <i class="fas fa-thumbs-up <?php echo (isset($_SESSION['user_id']) && isLiked($post['id'], $_SESSION['user_id'], $conn)) ? 'liked' : ''; ?>"></i>
                    <span><?php echo getLikeCount($post['id'], $conn); ?> Likes</span>
                </button>
                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
            </form>
        </div>

        <div class="comment-footer">
            <form action="comment.php" method="GET" class="comment-form">
                <button type="submit" class="comment-button" style="background: none; border: none;">
                    <i class="fas fa-comment"></i>
                            <span><?php echo getCommentCount($post['id'], $conn); ?> Comments</span>
                </button>
                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
            </form>
        </div>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $post['user_id']): ?>
        <div class="share-footer">
            <button type="button" class="share-button" style="background: none; border: none;" 
                    onclick="sharePost(<?php echo htmlspecialchars($post['id']); ?>)">
                <i class="fas fa-share"></i> 
                <span>Share</span>
            </button>
        </div>
        <?php endif; ?>
        <div class="post-footer">
            <?php echo timeAgo($post['created_at']); ?>
        </div>
        <div class="wedit-wapus">
            
            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $post['user_id']): ?>
               <button class="edit-button" style="background: none; border: none;"  data-post-id="<?= $post['id']; ?>"><i class="fas fa-edit"></i></button>
            <?php endif; ?>
            
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $post['user_id']): ?>
            <form action="index.php" method="POST" style="display: inline;">
                <button type="button" class="delete-button" style="background: none; border: none;" data-post-id="<?php echo $post['id']; ?>">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </form>
        <?php endif; ?>
        </div>
    </div>
        <?php endif; ?>
        </div>
    <?php endwhile; ?>
</div>
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>Edit Status</h2>
            <form id="editForm" method="POST">
                <textarea name="status" id="status" required></textarea>
                <input type="hidden" name="post_id" id="post_id">
                <button type="submit" name="edit_status" class="button">Simpan</button>
                <button type="button" class="button cancel-btn">Batal</button>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h2>Are you sure you want to delete this post?</h2>
            <form id="deleteForm" method="POST">
                <input type="hidden" name="post_id" id="post_id">
                <button type="submit" style="background-color: red;" name="delete_status" class="button">Yes, Delete</button>
                <button type="button" class="button cancel-btn" style="background-color: grey;">Cancel</button>
            </form>
        </div>
    </div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
<script>
    const toggleLink = document.getElementById("toggle-link");
    const toggleText = document.getElementById("toggle-text");
    const toggleIcon = document.getElementById("toggle-icon");

    const items = [
        { text: "SONG", icon: "fa-music", href: "music.php" },
        { text: "WATCH", icon: "fa-tv", href: "video.php" },
    ];

    let currentIndex = 0;

    function toggleContent() {
        currentIndex = (currentIndex + 1) % items.length;
        toggleText.textContent = items[currentIndex].text;
        toggleIcon.className = `fa-solid ${items[currentIndex].icon}`;
        toggleLink.href = items[currentIndex].href;
    }

    setInterval(toggleContent, 2000);
</script>
<script> 
    const form = document.querySelector('.new-post form');
    const loadingBar = document.getElementById('loading-bar');
    const progressBar = loadingBar.querySelector('.progress-bar');

    form.addEventListener('submit', function (e) {
        loadingBar.classList.remove('hidden');

        let progress = 0;
        const interval = setInterval(() => {
            if (progress >= 100) {
                clearInterval(interval);
            } else {
                progress += 20; 
                progressBar.style.width = progress + '%';
            }
        }, 500);
    });
</script> 
<script>
    function togglePlay(button) {
        const audio = button.closest('.post-audio').querySelector('audio');
        const icon = button.querySelector('i');
        const currentTimeElem = button.closest('.post-audio').querySelector('.current-time');
        const totalDurationElem = button.closest('.post-audio').querySelector('.total-duration');
        const visualizerBars = button.closest('.post-audio').querySelectorAll('.visualizer .bar');

        if (audio.paused) {
            audio.play();
            icon.classList.remove('fa-play');
            icon.classList.add('fa-pause');
            visualizerBars.forEach(bar => bar.style.animationPlayState = 'running');
        } else {
            audio.pause();
            icon.classList.remove('fa-pause');
            icon.classList.add('fa-play');
            visualizerBars.forEach(bar => bar.style.animationPlayState = 'paused');
        }

        audio.ontimeupdate = function () {
            const currentMinutes = Math.floor(audio.currentTime / 60);
            const currentSeconds = Math.floor(audio.currentTime % 60).toString().padStart(2, '0');
            currentTimeElem.textContent = `${currentMinutes}:${currentSeconds}`;

            const progressBar = button.closest('.post-audio').querySelector('.progress-bar-inner');
            const percentage = (audio.currentTime / audio.duration) * 100;
            progressBar.style.width = percentage + '%';
        };

        audio.onended = function () {
            icon.classList.remove('fa-pause');
            icon.classList.add('fa-play');
            visualizerBars.forEach(bar => bar.style.animationPlayState = 'paused');
        };
    }

    function setProgress(event, progressBar) {
        const audio = progressBar.closest('.post-audio').querySelector('audio');
        const width = progressBar.offsetWidth;
        const clickX = event.offsetX;
        const duration = audio.duration;

        audio.currentTime = (clickX / width) * duration;
    }

    function updateVisualizer(audio) {
        const visualizerBars = audio.closest('.post-audio').querySelectorAll('.visualizer .bar');
        const currentTime = audio.currentTime;
        const duration = audio.duration;

        visualizerBars.forEach((bar, index) => {
            const intensity = (Math.sin(currentTime * (index + 1) * 0.1) + 1) * 50; 
            bar.style.height = `${intensity}%`;
        });

        if (!audio.paused && currentTime < duration) {
            requestAnimationFrame(() => updateVisualizer(audio)); 
        }
    }

    document.querySelectorAll('.post-audio audio').forEach(audio => {
        audio.addEventListener('loadedmetadata', function () {
            const totalDurationElem = audio.closest('.post-audio').querySelector('.total-duration');
            const totalMinutes = Math.floor(audio.duration / 60);
            const totalSeconds = Math.floor(audio.duration % 60).toString().padStart(2, '0');
            totalDurationElem.textContent = `${totalMinutes}:${totalSeconds}`;
        });

        audio.addEventListener('play', function () {
            updateVisualizer(audio); 
        });
    });
</script>
<script>
    function sharePost(postId) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'share.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4 && xhr.status == 200) {
                if (xhr.responseText === "Post shared successfully.") {
                    document.querySelector('.share-button').innerHTML = '<i class="fas fa-check"></i> Shared';
                    document.querySelector('.share-button').disabled = true;  
                    
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            }
        };
        xhr.send('post_id=' + postId);
    }
</script>
<script>
    document.getElementById("upload-image").addEventListener("change", function(event) {
        const previewContainer = document.getElementById("preview-container");
        previewContainer.innerHTML = "";
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement("img");
                img.src = e.target.result; 
                img.style.maxWidth = "100%";
                img.style.marginTop = "10px"; 
                previewContainer.appendChild(img); 
            };
            reader.readAsDataURL(file);
        }
    });
    
    document.getElementById("upload-video").addEventListener("change", function(event) {
        const previewContainer = document.getElementById("preview-container");
        previewContainer.innerHTML = "";
        const file = event.target.files[0]; 
        if (file) {
            const video = document.createElement("video");
            video.src = URL.createObjectURL(file);
            video.controls = true; 
            video.style.maxWidth = "100%"; 
            video.style.marginTop = "10px"; 
            previewContainer.appendChild(video); 
        }
    });
    
    document.getElementById("upload-audio").addEventListener("change", function(event) {
        const previewContainer = document.getElementById("preview-container");
        previewContainer.innerHTML = ""; 
        const file = event.target.files[0];  
        if (file) {
            const audio = document.createElement("audio");
            audio.src = URL.createObjectURL(file);  
            audio.controls = true;  
            audio.style.marginTop = "10px";  
            previewContainer.appendChild(audio);  
        }
    });
</script>
<script>
    $(document).ready(function() {
      $(".edit-button").on("click", function() {
        var postId = $(this).data('post-id'); 
        var status = $("#post_" + postId).attr("data-teks-asli");  
        console.log(status);  
        $("#post_id").val(postId);  
        $("#status").val(status);  
        $("#editModal").show(); 
      });
     
      $(".cancel-btn").on("click", function() {
        $("#editModal").hide();
      });
    });
</script>
<script>
    $(document).on("click", ".baca-selengkapnya", function(e) {
        e.preventDefault();
        var post = $(this).closest(".post");
        var teksAsli = post.find("p").attr("data-teks-asli");
        post.find("p").html(teksAsli);
    });
</script>

<script>
    function showLoginPopup() {
        document.getElementById('loginModal').style.display = 'flex';
    }
    
    var modal = document.getElementById('loginModal');
    var closeBtn = document.getElementsByClassName('close-btn')[0];

    closeBtn.onclick = function() {
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    function showDeletePopup(postId) {
        document.getElementById('post_id').value = postId;
        document.getElementById('deleteModal').style.display = 'flex';
    }
    
    $(".delete-button").on("click", function() {
        var postId = $(this).data('post-id');
        showDeletePopup(postId);
    });
    
    $(".cancel-btn").on("click", function() {
        document.getElementById('deleteModal').style.display = 'none';
    });
    
    function showDeletePopup(postId) {
        document.getElementById('post_id').value = postId;
        document.getElementById('deleteModal').style.display = 'flex';
    }
    
    $(".delete-button").on("click", function() {
        var postId = $(this).data('post-id');
        showDeletePopup(postId);
    });
    
    $(".cancel-btn").on("click", function() {
        document.getElementById('deleteModal').style.display = 'none';
    });
    
    $("#deleteForm").on("submit", function(e) {
        e.preventDefault();
        
        var postId = $("#post_id").val();
        
        $.ajax({
            url: 'index.php', 
            type: 'POST',
            data: {
                delete_status: true,
                post_id: postId
            },
            success: function(response) {
                $('#post_' + postId).remove(); 
                
                document.getElementById('deleteModal').style.display = 'none';
           
                location.reload(); 
            },
            error: function() {
                alert("An error occurred while deleting the status.");
            }
        });
    }); 
</script>
<script>
    $(document).ready(function() {
        $(".like-button").on("click", function(e) {
            e.preventDefault();
            
            var postId = $(this).closest("form").find("input[name='post_id']").val();
            var button = $(this);  

            <?php if (isset($_SESSION['user_id'])): ?>
                $.ajax({
                    url: 'index.php', 
                    type: 'POST',
                    data: {
                        like_post: true,
                        post_id: postId
                    },
                    success: function(response) {
                        var data = JSON.parse(response);
                        
                        if (data.status == 'liked') {
                            button.find("i").addClass("liked");
                        } else {
                            button.find("i").removeClass("liked");
                        }
                        
                        button.find("span").text(data.like_count + " Likes");
                    },
                    error: function() {
                        alert("An error occurred while processing your like.");
                    }
                });
            <?php else: ?>
                showLoginPopup();
            <?php endif; ?>
        });
    });

    $(document).ready(function() {
        $(".mobile-link").on("click", function(e) {
            e.preventDefault();
            
            $("#notificationsModal").fadeIn(100).addClass("show");
            $.ajax({
                url: 'index.php',
                type: 'POST',
                data: { mark_notifications_read: true },
                success: function(response) {
                    $(".fa-bell").attr("data-unread", 0); 
                },
                error: function(xhr, status, error) {
                    console.error("Error marking notifications as read: " + error);
                }
            });
        });
    
        $(".close-btn").on("click", function() {
            $("#notificationsModal").fadeOut(100).removeClass("show");
        });
    
        $(window).on("click", function(event) {
            if ($(event.target).is("#notificationsModal")) {
                $("#notificationsModal").fadeOut(100).removeClass("show");
            }
        });
    });
 
    document.querySelectorAll('.follow-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
    
            const formData = new FormData(this);
            const followingId = formData.get('following_id');
            const button = form.querySelector('button');
    
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            button.disabled = true; 
    
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'followed' || data.status === 'unfollowed') {
                    button.innerHTML = `<i class="fas fa-user-${data.status === 'followed' ? 'check' : 'plus'}"></i> ${data.follower_count} `;
                    button.classList.toggle('followed', data.status === 'followed'); 
                    button.disabled = false; 
    
                    document.querySelectorAll(`.follower-count[data-following-id="${followingId}"]`).forEach(counter => {
                        counter.textContent = `${data.follower_count} `;
                    });
    
                    document.querySelectorAll(`.follow-button[data-following-id="${followingId}"]`).forEach(button => {
                        button.classList.toggle('followed', data.status === 'followed'); 
                    });
    
                    document.querySelectorAll('.status').forEach(status => {
                        const statusId = status.getAttribute('data-following-id');
                        if (statusId === followingId) {
                            status.querySelector('.follower-count').textContent = `${data.follower_count} Followers`;
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                button.innerHTML = '<i class="fas fa-user-plus"></i> Follow';
                button.disabled = false;
            });
        });
    });
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