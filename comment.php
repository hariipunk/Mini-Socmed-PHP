<?php
session_start();
include('db.php');

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['comment_id']) && isset($_SESSION['user_id'])) {
    $comment_id = $_GET['comment_id'];
    $user_id = $_SESSION['user_id'];

    $query = "SELECT * FROM comments WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $comment_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $delete_query = "DELETE FROM comments WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $comment_id);
        $delete_stmt->execute();
        
        header("Location: comment.php?post_id=" . $_GET['post_id']);
        exit();
    } else {
        echo "You are not authorized to delete this comment.";
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

if (isset($_GET['post_id'])) {
    $post_id = $_GET['post_id'];

    $query = "SELECT posts.*, users.username, users.fullname, users.photo, users.role, users.is_verified FROM posts JOIN users ON posts.user_id = users.id WHERE posts.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $post_id);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();

    if (!$post) {
        die("Status not found.");
    }
} else {
    $query = "SELECT posts.*, users.username, users.fullname, users.photo, users.role, users.is_verified FROM posts JOIN users ON posts.user_id = users.id ORDER BY posts.created_at DESC";
    $result = $conn->query($query);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_comment'])) {
    $comment = $_POST['comment'];
    $user_id = $_SESSION['user_id'];

    if (!empty($comment)) {

$query = "INSERT INTO comments (post_id, user_id, comment) VALUES (?, ?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param('iis', $post_id, $user_id, $comment);
$stmt->execute();

$owner_query = "SELECT user_id FROM posts WHERE id = ?";
$stmt = $conn->prepare($owner_query);
$stmt->bind_param('i', $post_id);
$stmt->execute();
$owner_result = $stmt->get_result();
$owner = $owner_result->fetch_assoc();
$post_owner_id = $owner['user_id'];

$user_query = "SELECT username FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param('i', $user_id); 
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$commenter_username = $user['username'];

if ($post_owner_id != $user_id) {
    $notification_message = "{$commenter_username} mengomentari postingan Anda.";
    $comment_url = "comment.php?post_id=" . $post_id; 
    
    $insert_notification = "INSERT INTO notifications (recipient_id, sender_id, message, link) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_notification);
    $stmt->bind_param('iiss', $post_owner_id, $user_id, $notification_message, $comment_url);
    $stmt->execute();
     }
  }
}

$query = "SELECT comments.*, users.username, users.fullname, users.username, users.photo, users.role, users.is_verified FROM comments JOIN users ON comments.user_id = users.id WHERE comments.post_id = ? ORDER BY comments.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $post_id);
$stmt->execute();
$comments = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['like_reply'])) {
    $comment_id = $_POST['comment_id'];  
    $user_id = $_SESSION['user_id'];

    $check_query = "SELECT * FROM replylikes WHERE user_id = ? AND comment_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('ii', $user_id, $comment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $insert_query = "INSERT INTO replylikes (user_id, comment_id) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('ii', $user_id, $comment_id);
        $stmt->execute();
        $status = 'replyliked';
    } else {
        $delete_query = "DELETE FROM replylikes WHERE user_id = ? AND comment_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('ii', $user_id, $comment_id);
        $stmt->execute();
        $status = 'replyunliked';
    }

    $like_count = getReplyLikeCount($comment_id, $conn);

    $owner_query = "SELECT user_id, post_id FROM comments WHERE id = ?";
    $stmt = $conn->prepare($owner_query);
    $stmt->bind_param('i', $comment_id); 
    $stmt->execute();
    $owner_result = $stmt->get_result();
    $owner = $owner_result->fetch_assoc();
    $comment_owner_id = $owner['user_id'];
    $post_id = $owner['post_id']; 

    $user_query = "SELECT username FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();
    $liker_username = $user['username'];

    if ($comment_owner_id != $user_id) {
        $notification_message = "{$liker_username} menyukai balasan komentar Anda.";
        $comment_url = "comment.php?post_id=" . $post_id; 
        
        $insert_notification = "INSERT INTO notifications (recipient_id, sender_id, message, link) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_notification);
        $stmt->bind_param('iiss', $comment_owner_id, $user_id, $notification_message, $comment_url);
        $stmt->execute();
    }

    echo json_encode([
        'status' => $status,
        'like_count' => $like_count
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
        return "$minutes minutes ago";
    } else if ($hours <= 24) {
        return "$hours hours ago";
    } else if ($days <= 7) {
        return "$days days ago";
    } else if ($weeks <= 4.3) {
        return "$weeks weeks ago";
    } else if ($months <= 12) {
        return "$months months ago";
    } else {
        return "$years years ago";
    }
}

function getReplyLikeCount($comment_id, $conn) {
    $query = "SELECT COUNT(*) AS like_count FROM replylikes WHERE comment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $comment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['like_count'];
}

function isreplyLiked($comment_id, $user_id, $conn) {
    $query = "SELECT * FROM replylikes WHERE comment_id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $comment_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_comments']) && isset($_POST['post_id'])) {
    $post_id = (int)$_POST['post_id'];
    $toggle_comments = (int)$_POST['toggle_comments'];
    $user_id = $_SESSION['user_id'];

    $query = "SELECT * FROM posts WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $post_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $update_query = "UPDATE posts SET comments_disabled = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ii', $toggle_comments, $post_id);
        $stmt->execute();

        header("Location: comment.php?post_id=$post_id");
        exit();
    } else {
        echo "Unauthorized action.";
    }
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
    <title>Comment</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<header>
    <h1>PojoKan Comment</h1>
    <div class="profile">
        <i class="fa-solid fa-circle-user icon"></i>
        <div class="profile-menu">
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
                <a href="myprofile.php"><i class="fas fa-user"></i> My Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log Out</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<div class="container">
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
                        <i class="fa-solid fa-circle-user icon-default"></i> <!-- Ikon default jika tidak ada foto -->
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
                <form action="index.php" method="POST" class="follow-form">
                    <input type="hidden" name="following_id" value="<?php echo $post['user_id']; ?>">
                    <button type="submit" 
                            name="follow_user" 
                            class="follow-button" 
                            data-following-id="<?php echo $post['user_id']; ?>" 
                            <?php echo (!$isLoggedIn || $isSelf) ? 'disabled' : ''; ?>>
                        <i class="<?php echo $isLoggedIn && isFollowing($_SESSION['user_id'], $post['user_id'], $conn) ? 'fas fa-user-check' : 'fas fa-user-plus'; ?>"></i>
                        <span class="follower-count" data-following-id="<?php echo $post['user_id']; ?>">
                            <?php echo $followerCount; ?>
                        </span>
                    </button>
                </form>
            </div>
             <p class="status-text" data-post-id="<?php echo $post['id']; ?>">
                 <?php echo htmlspecialchars($post['status']); ?>
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
                                <div class="progress-bar-inner"></div>
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
            </div>
        <?php else: ?>
            <div class="user-info">
                <div class="user-photo">
                    <?php if (!empty($post['photo']) && file_exists($post['photo'])): ?>
                        <img src="<?php echo htmlspecialchars($post['photo']); ?>" alt="User Photo" class="user-photo">
                    <?php else: ?>
                        <i class="fa-solid fa-circle-user icon-default"></i> 
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <h2><?php echo htmlspecialchars($post['fullname']); ?>            <?php 
            if ($post['is_verified'] == 1) {
        if ($post['role'] == 'admin') {
            echo ' <span class="verified admin"><i class="fas fa-check-circle"></i></span>';
        } else {
            echo ' <span class="verified user"><i class="fas fa-check-circle"></i></span>';
        }
    }
        ?></h2>
                    <span class="username">@<?php echo htmlspecialchars($post['username']); ?></span>

                </div>
                <?php
                $followerCount = getFollowerCount($post['user_id'], $conn);
                
                $isLoggedIn = isset($_SESSION['user_id']);
                
                $isSelf = $isLoggedIn && $_SESSION['user_id'] == $post['user_id'];
                ?>
                <form action="index.php" method="POST" class="follow-form">
                    <input type="hidden" name="following_id" value="<?php echo $post['user_id']; ?>">
                    <button type="submit" name="follow_user" class="follow-button" data-following-id="<?php echo $post['user_id']; ?>" 
                            <?php echo (!$isLoggedIn || $isSelf) ? 'disabled' : ''; ?>>
                                <i class="<?php echo $isLoggedIn && isFollowing($_SESSION['user_id'], $post['user_id'], $conn) ? 'fas fa-user-check' : 'fas fa-user-plus'; ?>"></i>
                            <span class="follower-count" data-following-id="<?php echo $post['user_id']; ?>">
                                <?php echo $followerCount; ?>
                            </span>
                    </button>
                </form>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $post['user_id']): ?>
                    <form action="comment.php" method="POST" class="toggle-comments-form">
                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                        <input type="hidden" name="toggle_comments" value="<?php echo $post['comments_disabled'] ? 0 : 1; ?>">
                        <button type="submit" class="toggle-comments-button" style="background: none; border: none;">
                            <?php if ($post['comments_disabled']): ?>
                                <i class="fa fa-eye-slash" aria-hidden="true"></i> 
                            <?php else: ?>
                                <i class="fa fa-eye" aria-hidden="true"></i>
                            <?php endif; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <p><?php echo htmlspecialchars($post['status']); ?></p> <!-- Status -->
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
                        <div class="progress-bar-inner"></div>
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
                <p>You must log in to like a post.</p>
            </div>
        </div>
    <div class="post-footer-wrapper">
        <div class="like-footer">
            <form action="comment.php" method="POST" class="like-form">
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
        <div class="post-footer">
            <?php echo timeAgo($post['created_at']); ?>
        </div>
    </div>
        <?php endif; ?>
        </div>
        <?php if (!$post['comments_disabled']) { ?>
            <h3>Comments</h3>
                <?php if (isset($_SESSION['user_id'])): ?>
                
                    <!-- Form untuk menambah komentar -->
                    <div class="commentbox">
                        <form action="comment.php?post_id=<?php echo $post_id; ?>" method="POST">
                            <textarea name="comment" placeholder="Add a comment..." required></textarea>
                            <button type="submit" name="submit_comment" class="button">Submit Comment</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p>You need to be logged in to comment.</p>
                <?php endif; ?>
<?php while ($comment = $comments->fetch_assoc()): ?>
    <div class="post">
        <div class="user-info">
            <div class="user-photo">
                <?php if (!empty($comment['photo']) && file_exists($comment['photo'])): ?>
                    <img src="<?php echo htmlspecialchars($comment['photo']); ?>" alt="User Photo" class="user-photo">
                <?php else: ?>
                    <i class="fa-solid fa-circle-user icon-default"></i> 
                <?php endif; ?>
            </div>
            <div class="user-details">
                <h2><?php echo htmlspecialchars($comment['fullname']); ?><?php 
            if ($comment['is_verified'] == 1) {
        if ($comment['role'] == 'admin') {
            echo ' <span class="verified admin"><i class="fas fa-check-circle"></i></span>';
        } else {
            echo ' <span class="verified user"><i class="fas fa-check-circle"></i></span>';
        }
    }
        ?></h2>
                <span class="username">@<?php echo htmlspecialchars($comment['username']); ?></span>
            </div>
        </div>
        <p><?php echo htmlspecialchars($comment['comment']); ?></p>

        <div class="post-footer-wrapper">
            <div class="like-footer">
                <form action="comment.php" method="POST" class="like-form">
                    <button type="button" class="like-button" style="background: none; border: none;">
                        <i class="fas fa-thumbs-up <?php echo (isset($_SESSION['user_id']) && isreplyLiked($comment['id'], $_SESSION['user_id'], $conn)) ? 'replyliked' : ''; ?>"></i>
                        <span><?php echo getReplyLikeCount($comment['id'], $conn); ?> Likes</span>
                    </button>
                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                </form>
            </div>
            <div class="post-footer"><?php echo timeAgo($comment['created_at']); ?>
            <?php if (isset($_SESSION['user_id']) && $comment['user_id'] == $_SESSION['user_id']): ?>
                <button type="button" class="delete-button" style="background: none; border: none;" onclick="showDeleteModal(<?php echo $comment['id']; ?>, <?php echo $post_id; ?>)">
                    <i class="fas fa-trash-alt"></i>
                </button>
            <?php endif; ?>
            </div>
        </div>
    </div>
<?php endwhile; ?>
        <?php } else { ?>
            <p><center>Comments are disabled for this post.</center></p>
        <?php } ?>
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <h2>Are you sure you want to delete this comment?</h2>
        <form id="deleteForm" action="" method="POST">
            <button type="submit" name="delete_comment" class="button" style="background-color: red;">Yes, Delete</button>
            <button type="button" class="button" style="background-color: grey;" onclick="closeDeleteModal()">Cancel</button>
        </form>
    </div>
</div>
</div>
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
    
    function showDeleteModal(commentId, postId) {
    document.getElementById('deleteModal').style.display = "block";
    
    var form = document.getElementById('deleteForm');
    form.action = "comment.php?action=delete&comment_id=" + commentId + "&post_id=" + postId;
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = "none";
}

window.onclick = function(event) {
    if (event.target == document.getElementById('deleteModal')) {
        closeDeleteModal();
    }
}
</script>
</body>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    $(document).ready(function() {
        $(".like-button").on("click", function(e) {
            e.preventDefault();
            
            var button = $(this); 
            var postId = button.closest("form").find("input[name='post_id']").val();
            var commentId = button.closest("form").find("input[name='comment_id']").val();

            <?php if (isset($_SESSION['user_id'])): ?>
                var actionData = {};
                
                if (postId) {
                    actionData = {
                        like_post: true,
                        post_id: postId
                    };
                } else if (commentId) {
                    actionData = {
                        like_reply: true,
                        comment_id: commentId
                    };
                }

                $.ajax({
                    url: 'comment.php', 
                    type: 'POST',
                    data: actionData,
                    success: function(response) {
                        var data = JSON.parse(response);

                        if (postId && data.status == 'liked') {
                            button.find("i").addClass("liked").removeClass("replyliked");
                        } else if (commentId && data.status == 'replyliked') {
                            button.find("i").addClass("replyliked").removeClass("liked");
                        } else {
                            button.find("i").removeClass("liked replyliked");
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
</html>