<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id']; 
$query = "SELECT COUNT(*) AS new_messages FROM messages WHERE receiver_id = ? AND status = 0";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$new_messages = $data['new_messages'];

$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];

$query = "SELECT posts.*, users.username, users.fullname, users.photo 
          FROM posts 
          JOIN users ON posts.user_id = users.id 
          WHERE posts.user_id = ? 
          ORDER BY posts.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);  
$stmt->execute();
$result = $stmt->get_result();

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
    header('Location: dashboard.php');
    exit;
}


$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];

$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_row = $user_result->fetch_assoc();

$is_private = false;

if ($_SESSION['user_id'] !== $user_id) {
    if ($user_row['is_private'] == 1 && !isFollowing($_SESSION['user_id'], $user_id, $conn)) {
        $is_private = true; 
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_privacy'])) {
    
    var_dump($_POST);

    $is_private = isset($_POST['is_private']) ? (int)$_POST['is_private'] : 0;

    $update_query = "UPDATE users SET is_private = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('ii', $is_private, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        if ($is_private == 1) {
            $update_visibility_query = "UPDATE posts SET visibility = 'followers_only' WHERE user_id = ?";
            $stmt = $conn->prepare($update_visibility_query);
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
        } else {
            $update_visibility_query = "UPDATE posts SET visibility = 'public' WHERE user_id = ?";
            $stmt = $conn->prepare($update_visibility_query);
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
        }

        $user_stmt = $conn->prepare($user_query);
        $user_stmt->bind_param('i', $_SESSION['user_id']);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_row = $user_result->fetch_assoc();

        header("Location: ".$_SERVER['PHP_SELF']."?user_id=" . $_SESSION['user_id']);
        exit;
    } else {
        echo "Gagal memperbarui status privasi.";
    }
}

$query = "SELECT posts.*, users.username, users.fullname, users.photo, users.is_verified, users.role FROM posts JOIN users ON posts.user_id = users.id WHERE posts.user_id = ? ORDER BY posts.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

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
        return "$minutes minutes";
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

function getCommentCount($post_id, $conn) {
    $query = "SELECT COUNT(*) AS comment_count FROM comments WHERE post_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['comment_count'];
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

function getFollowingCount($userId, $conn) {
    $query = "SELECT COUNT(*) AS following_count FROM follows WHERE follower_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['following_count'];
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

function get_user_level($conn, $user_id) {
    $sql_level = "SELECT level FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql_level);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    return $data['level'] ?? 1; 
}
$level = get_user_level($conn, $user_id);
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
    <h1>PojoKan Dashboard</h1>
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
<div class="profile-box">
    <div class="cover-photo-box">
                    <?php if (!empty($user_row['cover_photo']) && file_exists($user_row['cover_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($user_row['cover_photo']); ?>" alt="Cover Photo" class="cover-photo-img">
                    <?php else: ?>
                        <p class="no-photo-message">No cover photo uploaded</p>
                    <?php endif; ?>
    </div>
    <div class="profile-picture-box">
        <?php if (!empty($user_row['photo']) && file_exists($user_row['photo'])): ?>
            <img src="<?php echo htmlspecialchars($user_row['photo']); ?>" alt="User Photo" class="profile-picture-img">
        <?php else: ?>
            <i class="fa-solid fa-circle-user icon-default"></i>
        <?php endif; ?>
    </div>
<center>
    <strong class="name-tag">
        <?php echo htmlspecialchars($user_row['fullname']); ?>
    </strong>
       <?php 
    if ($user_row['is_verified'] == 1) {
        if ($user_row['role'] == 'admin') {
            echo ' <span class="verified admin"><i class="fas fa-check-circle"></i></span>';
        } else {            
            echo ' <span class="verified user"><i class="fas fa-check-circle"></i></span>';
        }
    }
        ?>
</center>
 <div class="rank-display level-<?= $level; ?>">
    <div class="rank-icon level-<?= $level; ?>"></div>
    Level <?= $level; ?>
</div>
 <div class="user-details-container">
 <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id): ?>
    <div class="follower-box">
    <form method="POST" class="privacy-form">
        <?php if ($user_row['is_private'] == 1): ?>
            <p>Private</p>
            <button type="submit" name="update_privacy" class="privacy-button" style="background: none; border: none;">
            
             <span class="user-followers"><i class="fas fa-lock"></i></span> 
            </button>
            <input type="hidden" name="is_private" value="0">
        <?php else: ?>
            <p>Public</p>
            <button type="submit" name="update_privacy" class="privacy-button" style="background: none; border: none;">
            
            <span class="user-followers"><i class="fas fa-lock-open"></i></span>
            </button>
            <input type="hidden" name="is_private" value="1">
        <?php endif; ?>
    </form>
    </div>
<?php endif; ?>
	
    <div class="follower-box">
        <p>Followers</p>
        <span class="user-followers"><?php echo getFollowerCount($user_row['id'], $conn); ?></span>
    </div>

    <div class="following-box">
        <p>Following</p>
        <span class="user-following"><?php echo getFollowingCount($user_row['id'], $conn); ?></span>
    </div>
 </div>
</div>
    <?php
    $query = "SELECT posts.*, users.username, users.fullname, users.photo 
              FROM posts 
              JOIN users ON posts.user_id = users.id 
              WHERE posts.user_id = ?
              ORDER BY posts.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id); 
    $stmt->execute();
    $result = $stmt->get_result();
?>
 <div class="post-wrapper">
 	
    <?php if ($is_private): ?>
        <div class="private-overlay">
    <i class="fas fa-lock"></i> 
    <span>this Account is Private</span>
        </div>
    <?php else: ?>
   
   <?php while ($row = $result->fetch_assoc()) : ?>
        <div class="post" id="post_<?php echo $row['id']; ?>">
        <?php if ($row['shared_post_id']): 
            $query = "SELECT posts.*, users.username AS original_username, users.fullname AS original_fullname, users.photo AS original_photo
                      FROM posts 
                      JOIN users ON posts.user_id = users.id 
                      WHERE posts.id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $row['shared_post_id']);
            $stmt->execute();
            $sharedResult = $stmt->get_result();
            $originalPost = $sharedResult->fetch_assoc();
        ?>
            <div class="user-info">
                <div class="user-photo">
                <a href="dashboard.php?user_id=<?php echo htmlspecialchars($row['user_id']); ?>">
                    <?php if (!empty($row['photo']) && file_exists($row['photo'])): ?>
                        <img src="<?php echo htmlspecialchars($row['photo']); ?>" alt="User Photo" class="user-photo">
                    <?php else: ?>
                        <i class="fa-solid fa-circle-user icon-default"></i> 
                    <?php endif; ?>
                </a>
                </div>
                <div class="user-details">
                    <h2><a href="dashboard.php?user_id=<?php echo htmlspecialchars($row['user_id']); ?>" class="no-style-link">
                        <?php echo htmlspecialchars($row['fullname']); ?>
                    </a></h2>
                    <span class="username">@<?php echo htmlspecialchars($row['username']); ?></span> 
                </div>
                <?php
                $followerCount = getFollowerCount($row['user_id'], $conn);

                $isLoggedIn = isset($_SESSION['user_id']);
                
                $isSelf = $isLoggedIn && $_SESSION['user_id'] == $row['user_id'];
                ?>

                <?php if (!$isSelf): ?>
                    <form action="index.php" method="POST" class="follow-form">
                        <input type="hidden" name="following_id" value="<?php echo $row['user_id']; ?>">
                        <button type="submit" 
                                name="follow_user" 
                                class="follow-button <?php echo $isLoggedIn && isFollowing($_SESSION['user_id'], $row['user_id'], $conn) ? 'followed' : ''; ?>" 
                                data-following-id="<?php echo $row['user_id']; ?>" 
                                <?php echo !$isLoggedIn ? 'disabled' : ''; ?>>
                            <i class="<?php echo $isLoggedIn && isFollowing($_SESSION['user_id'], $row['user_id'], $conn) ? 'fas fa-user-check' : 'fas fa-user-plus'; ?>"></i>
                            <span class="follower-count" data-following-id="<?php echo $row['user_id']; ?>">
                                <?php echo $followerCount; ?>
                            </span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <p class="status-text" data-post-id="<?php echo $row['id']; ?>">
                <?php echo htmlspecialchars($row['status']); ?>
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
                            <i class="fas fa-thumbs-up <?php echo (isset($_SESSION['user_id']) && isLiked($row['id'], $_SESSION['user_id'], $conn)) ? 'liked' : ''; ?>"></i>
                            <span><?php echo getLikeCount($row['id'], $conn); ?> Likes</span>
                        </button>
                        <input type="hidden" name="post_id" value="<?php echo $row['id']; ?>">
                    </form>
                </div>
            
                <div class="comment-footer">
                <form action="comment.php" method="GET" class="comment-form">
                    <button type="submit" class="comment-button" style="background: none; border: none;">
                        <i class="fas fa-comment"></i>
                                <span><?php echo getCommentCount($row['id'], $conn); ?> Comments</span>
                    </button>
                    <input type="hidden" name="post_id" value="<?php echo $row['id']; ?>">
                </form>
                </div>
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $row['user_id']): ?>
                        <button class="message-button" style="background: none; border: none;" onclick="window.location.href='conversation.php?contact_id=<?= $row['user_id']; ?>'">
                            <i class="fas fa-envelope"></i> 
                        </button>
                    <?php endif; ?>
                    <div class="post-footer">
                        <?php echo timeAgo($row['created_at']); ?>
                    </div>
                    <div class="wedit-wapus">
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $row['user_id']): ?>
                       <button class="edit-button" style="background: none; border: none;"  data-post-id="<?= $row['id']; ?>"><i class="fas fa-edit"></i></button>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $row['user_id']): ?>
                    <form action="index.php" method="POST" style="display: inline;">
                        <button type="button" class="delete-button" style="background: none; border: none;" data-post-id="<?php echo $row['id']; ?>">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                    </div>
            </div>
        <?php else: ?>
          <div class="user-info">
            <div class="user-photo">
                <?php if (!empty($row['photo']) && file_exists($row['photo'])): ?>
                    <img src="<?php echo htmlspecialchars($row['photo']); ?>" alt="User Photo" class="user-photo">
                <?php else: ?>
                    <i class="fa-solid fa-circle-user icon-default"></i>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <h2><?php echo htmlspecialchars($row['fullname']); ?></h2>
                <span class="username">@<?php echo htmlspecialchars($row['username']); ?></span>
            </div>
          </div>
          <p class="status-text" data-post-id="<?php echo $row['id']; ?>">
    <?php echo htmlspecialchars($row['status']); ?>
           </p>
          
          <?php if (!empty($row['image']) && file_exists($row['image'])): ?>
            <div class="post-image">
                <img src="<?php echo htmlspecialchars($row['image']); ?>" alt="Post Image" style="max-width: 100%; height: auto;">
            </div>
          <?php endif; ?>
<?php if (!empty($row['video']) && file_exists($row['video'])): ?>
    <div class="post-video">
        <video controls style="max-width: 100%; height: auto;">
            <source src="<?php echo htmlspecialchars($row['video']); ?>" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>
<?php endif; ?>
<?php if (!empty($row['audio']) && file_exists($row['audio'])): ?>
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

    <audio id="audio-<?php echo $row['id']; ?>">
        <source src="<?php echo htmlspecialchars($row['audio']); ?>" type="audio/mpeg">
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

          <div class="post-footer-wrapper">
              <div class="like-footer">
                    <form action="index.php" method="POST" class="like-form">
                        <button type="button" class="like-button" style="background: none; border: none;">
                            <i class="fas fa-thumbs-up <?php echo (isset($_SESSION['user_id']) && isLiked($row['id'], $_SESSION['user_id'], $conn)) ? 'liked' : ''; ?>"></i>
                            <span><?php echo getLikeCount($row['id'], $conn); ?> Likes</span>
                        </button>
                        <input type="hidden" name="post_id" value="<?php echo $row['id']; ?>">
                    </form>
                </div>
            <div class="comment-footer">
    <form action="comment.php" method="GET" class="comment-form">
        <button type="submit" class="comment-button" style="background: none; border: none;">
            <i class="fas fa-comment"></i>
            <span><?php echo getCommentCount($row['id'], $conn); ?> Comments</span>
        </button>
        <input type="hidden" name="post_id" value="<?php echo $row['id']; ?>">
    </form>
            </div>
            <div class="post-footer">
                <?php echo timeAgo($row['created_at']); ?>
            </div>
            <div class="wedit-wapus">
    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $row['user_id']): ?>
       <button class="edit-button" style="background: none; border: none;"  data-post-id="<?= $row['id']; ?>"><i class="fas fa-edit"></i></button>
    <?php endif; ?>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $row['user_id']): ?>
<form action="dashboard.php" method="POST" style="display: inline;">
    <button type="button" class="delete-button" style="background: none; border: none;" data-post-id="<?php echo $row['id']; ?>">
        <i class="fas fa-trash-alt"></i>
    </button>
</form>
        <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
        </div>

    <?php endwhile; ?>
    <?php endif; ?>
 </div>
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
            <button type="submit" name="delete_status" class="button" style="background-color: red;">Yes, Delete</button>
            <button type="button" class="button cancel-btn" style="background-color: grey;">Cancel</button>
        </form>
    </div>
</div>

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
$(document).ready(function () {
    $(".edit-button").on("click", function () {
        var postId = $(this).data("post-id");

        var status = $('p[data-post-id="' + postId + '"]').text().trim();

        $("#post_id").val(postId);
        $("#status").val(status);
        $("#editModal").show();
    });

    $(".cancel-btn").on("click", function () {
        $("#editModal").hide();
    });
});

$(document).ready(function () {

    $(".delete-button").on("click", function () {
        var postId = $(this).data("post-id"); 
        $("#deleteForm #post_id").val(postId);
        $("#deleteModal").show(); 
    });

    $(".cancel-btn").on("click", function () {
        $("#deleteModal").hide();
    });

    $("#deleteForm").on("submit", function (e) {
        e.preventDefault();

        var postId = $("#deleteForm #post_id").val(); 

        $.ajax({
            url: "dashboard.php", 
            type: "POST",
            data: {
                delete_status: true,
                post_id: postId
            },
            success: function (response) {
                if (response.trim() === "success") {
                    
                    $("#post_" + postId).remove();
                }
               
                $("#deleteModal").hide();
            },
            error: function () {
             
                $("#deleteModal").hide();
            }
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
</body>
</html>