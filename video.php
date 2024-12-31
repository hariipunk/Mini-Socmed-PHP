<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function getUsernameById($user_id, $conn) {
    $query = "SELECT username FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['username'];
}

function getVideos($user_id, $conn) {
    $query = "
        SELECT posts.*, users.username, users.fullname, users.photo
        FROM posts 
        JOIN users ON posts.user_id = users.id
        WHERE posts.video IS NOT NULL
        AND (posts.visibility = 'public' OR 
            (posts.visibility = 'private' AND posts.user_id IN (
                SELECT following_id 
                FROM follows 
                WHERE follower_id = ?
            ))
        )
        ORDER BY posts.created_at DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return $stmt->get_result();
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
        $notification_message = "{$liker_username} menyukai video Anda.";
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

    $user_id = $_SESSION['user_id'];
    $videos = getVideos($user_id, $conn);
    
    function timeAgo($datetime) {
        $time_ago = strtotime($datetime);
        $current_time = time();
        $time_difference = $current_time - $time_ago;
        $seconds = $time_difference;
        $minutes      = round($seconds / 60);           
        $hours        = round($seconds / 3600);         
        $days         = round($seconds / 86400);        
        $weeks        = round($seconds / 604800);      
        $months       = round($seconds / 2629440);      
        $years        = round($seconds / 31553280); 
    
        if($seconds <= 60) {
            return "Baru Saja";
        } else if($minutes <= 60) {
            if($minutes == 1) {
                return "1 menit";
            } else {
                return "$minutes menit";
            }
        } else if($hours <= 24) {
            if($hours == 1) {
                return "1 jam";
            } else {
                return "$hours jam";
            }
        } else if($days <= 7) {
            if($days == 1) {
                return "Kemarin";
            } else {
                return "$days hari";
            }
        } else if($weeks <= 4.3) { // 4.3 == 30/7
            if($weeks == 1) {
                return "1 minggu";
            } else {
                return "$weeks minggu";
            }
        } else if($months <= 12) {
            if($months == 1) {
                return "1 bulan";
            } else {
                return "$months bulan";
            }
        } else {
            if($years == 1) {
                return "1 tahun";
            } else {
                return "$years tahun";
            }
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

    function isFollowing($follower_id, $following_id, $conn) {
        $query = "SELECT * FROM follows WHERE follower_id = ? AND following_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $follower_id, $following_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
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
<body-v>
<header>
    <h1>PojoKan <i class="fa-solid fa-tv"></i></h1>
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
<div class="container-v">
    <?php if ($videos->num_rows > 0): ?>
        <?php while ($video = $videos->fetch_assoc()): ?>
            <div class="post-video-v">
                <div class="user-info-v">
                    <a href="dashboard.php?user_id=<?php echo htmlspecialchars($video['user_id']); ?>">
                        <?php if (!empty($video['photo']) && file_exists($video['photo'])): ?>
                            <img src="<?php echo htmlspecialchars($video['photo']); ?>" alt="User Photo" class="user-photo-v">
                        <?php else: ?>
                            <i class="fa-solid fa-circle-user icon-default"></i>
                        <?php endif; ?>
                    </a>
                    <div class="user-details-v">
                        <span class="fullname-v"><a href="dashboard.php?user_id=<?php echo htmlspecialchars($video['user_id']); ?>" class="no-style-link">
                            <?php echo htmlspecialchars($video['fullname']); ?>
                        </a></span>
                        <span class="username-v">@<?php echo htmlspecialchars($video['username']); ?></span> 
                    </div>
                </div>

                <?php
                $isLoggedIn = isset($_SESSION['user_id']);
                $isSelf = $isLoggedIn && $_SESSION['user_id'] == $video['user_id'];
                ?>
                
                <?php if (!$isSelf): ?>
                    <form action="video.php" method="POST" class="follow-form">
                        <input type="hidden" name="following_id" value="<?php echo $video['user_id']; ?>">
                        <button type="submit" 
                                name="follow_user" 
                                class="follow-button-v<?php echo $isLoggedIn && isFollowing($_SESSION['user_id'], $video['user_id'], $conn) ? ' followed-v' : ''; ?>" 
                                data-following-id="<?php echo $video['user_id']; ?>" 
                                <?php echo !$isLoggedIn ? 'disabled' : ''; ?>>
                            <i class="<?php echo $isLoggedIn && isFollowing($_SESSION['user_id'], $video['user_id'], $conn) ? 'fas fa-user-check' : 'fas fa-user-plus'; ?>"></i>
                        </button> 
                    </form>
                <?php endif; ?>
                
                <?php if (!empty($video['video']) && file_exists($video['video'])): ?>
                    <video controls>
                        <source src="<?php echo htmlspecialchars($video['video']); ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                <?php endif; ?>

                <div class="post-footer-wrapper-v">
                    <div class="like-footer-v">
                        <form action="video.php" method="POST" class="like-form">
                            <button type="button" class="like-button">
                                <i class="fas fa-thumbs-up <?php echo (isset($_SESSION['user_id']) && isLiked($video['id'], $_SESSION['user_id'], $conn)) ? 'liked' : ''; ?>"></i>
                                <span><?php echo getLikeCount($video['id'], $conn); ?> Likes</span>
                            </button>
                            <input type="hidden" name="post_id" value="<?php echo $video['id']; ?>">
                        </form>
                    </div>
                    <div class="comment-footer-v">
                        <form action="comment.php" method="GET" class="comment-form">
                            <button type="submit" class="comment-button">
                                <i class="fas fa-comment"></i>
                                <span><?php echo getCommentCount($video['id'], $conn); ?> Comments</span>
                            </button>
                            <input type="hidden" name="post_id" value="<?php echo $video['id']; ?>">
                        </form>
                    </div>
                    <div class="post-footer-v">
                        <?php echo timeAgo($video['created_at']); ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No videos available.</p>
    <?php endif; ?>
</div>
</body>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
<script>
document.querySelectorAll('.follow-form').forEach(form => {
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const followingId = formData.get('following_id');
        const button = form.querySelector('button');


        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        button.disabled = true;

        fetch('video.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'followed' || data.status === 'unfollowed') {
                button.innerHTML = `<i class="fas fa-user-${data.status === 'followed' ? 'check' : 'plus'}"></i> ${data.follower_count} `;
                button.classList.toggle('followed', data.status === 'followed');
                button.disabled = false; 
            
                if (data.status === 'followed') {
                    button.classList.add('followed-v');
                } else {
                    button.classList.remove('followed-v'); 
                }

                document.querySelectorAll(`.follower-count[data-following-id="${followingId}"]`).forEach(counter => {
                    counter.textContent = `${data.follower_count} `;
                });

                document.querySelectorAll(`.follow-button[data-following-id="${followingId}"]`).forEach(button => {
                    if (data.status === 'followed') {
                        button.classList.add('followed-v'); 
                    } else {
                        button.classList.remove('followed-v'); 
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
$(document).ready(function() {
    $(".like-button").on("click", function(e) {
        e.preventDefault();
        
        var postId = $(this).closest("form").find("input[name='post_id']").val();
        var button = $(this);

        <?php if (isset($_SESSION['user_id'])): ?>
            $.ajax({
                url: 'video.php', 
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