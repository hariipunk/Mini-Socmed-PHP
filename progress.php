<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$query = "SELECT COUNT(*) AS new_messages FROM messages WHERE receiver_id = ? AND status = 0";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$new_messages = $data['new_messages'] ?? 0;

// agar Admin auto Full progress 
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $current_level = 5;
    $progress_posts = 100;
    $progress_likes = 100;
    $progress_follows = 100;

    $sql_update_level = "UPDATE users SET level = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update_level);
    $stmt_update->bind_param("ii", $current_level, $user_id);
    $stmt_update->execute();
    $stmt_update->close();

    $query_check_progress = "SELECT * FROM progress WHERE user_id = ?";
    $stmt_check_progress = $conn->prepare($query_check_progress);
    $stmt_check_progress->bind_param("i", $user_id);
    $stmt_check_progress->execute();
    $result_check_progress = $stmt_check_progress->get_result();

    if ($result_check_progress->num_rows > 0) {
        $query_update_progress = "UPDATE progress SET 
            level = ?, 
            progress_posts = ?, 
            progress_likes = ?, 
            progress_follows = ?, 
            updated_at = NOW() 
            WHERE user_id = ?";
        $stmt_update_progress = $conn->prepare($query_update_progress);
        $stmt_update_progress->bind_param("iddii", $current_level, $progress_posts, $progress_likes, $progress_follows, $user_id);
        $stmt_update_progress->execute();
        $stmt_update_progress->close();
    } else {
        $query_insert_progress = "INSERT INTO progress (user_id, level, progress_posts, progress_likes, progress_follows) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert_progress = $conn->prepare($query_insert_progress);
        $stmt_insert_progress->bind_param("iiddi", $user_id, $current_level, $progress_posts, $progress_likes, $progress_follows);
        $stmt_insert_progress->execute();
        $stmt_insert_progress->close();
    }
} else {
    $total_posts = $conn->query("SELECT COUNT(*) AS total_posts FROM posts WHERE user_id = $user_id")->fetch_assoc()['total_posts'];
    $total_likes = $conn->query("SELECT COUNT(*) AS total_likes FROM likes WHERE user_id = $user_id")->fetch_assoc()['total_likes'];
    $total_follows = $conn->query("SELECT COUNT(*) AS total_follows FROM follows WHERE follower_id = $user_id")->fetch_assoc()['total_follows'];

    $levels = [
        1 => ['posts' => 0, 'likes' => 0, 'follows' => 0],
        2 => ['posts' => 5, 'likes' => 10, 'follows' => 5],
        3 => ['posts' => 10, 'likes' => 20, 'follows' => 10],
        4 => ['posts' => 20, 'likes' => 40, 'follows' => 20],
        5 => ['posts' => 30, 'likes' => 60, 'follows' => 30],
    ];

    $current_level = 1;
    $next_level_posts = $levels[$current_level]['posts'];
    $next_level_likes = $levels[$current_level]['likes'];
    $next_level_follows = $levels[$current_level]['follows'];

    foreach ($levels as $level => $requirements) {
        if ($total_posts >= $requirements['posts'] && 
            $total_likes >= $requirements['likes'] &&
            $total_follows >= $requirements['follows']) {
            $current_level = $level;
        } else {
            $next_level_posts = $requirements['posts'];
            $next_level_likes = $requirements['likes'];
            $next_level_follows = $requirements['follows'];
            break;
        }
    }

    $sql_update_level = "UPDATE users SET level = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update_level);
    $stmt_update->bind_param("ii", $current_level, $user_id);
    $stmt_update->execute();
    $stmt_update->close();

    $progress_posts = ($total_posts / $next_level_posts) * 100;
    $progress_likes = ($total_likes / $next_level_likes) * 100;
    $progress_follows = ($total_follows / $next_level_follows) * 100;

    $progress_posts = $progress_posts > 100 ? 100 : $progress_posts;
    $progress_likes = $progress_likes > 100 ? 100 : $progress_likes;
    $progress_follows = $progress_follows > 100 ? 100 : $progress_follows;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PojoKan</title>
     <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styleprofile.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body>
<header>
    <h1>Progress Level</h1>
    <div class="right-icons">
        <div class="profile">
            <i class="fa-solid fa-circle-user icon"></i>
            <div class="profile-menu">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
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
                    <a href="myprofile.php"><i class="fas fa-user"></i> My Profile</a>          
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log Out</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<div class="progress-container">
    <h1>Your Progress</h1>
    <p>Level Saat Ini : <strong>Level <?= $current_level; ?></strong></p>
    
    <div class="progress-box">
        <p>Progres Status :</p>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?= $progress_posts; ?>%;">
                <span><?= round($progress_posts); ?>%</span>
            </div>
        </div>
    </div>

    <div class="progress-box">
        <p>Progres Likes :</p>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?= $progress_likes; ?>%;">
                <span><?= round($progress_likes); ?>%</span>
            </div>
        </div>
    </div>

    <div class="progress-box">
        <p>Progres Follows :</p>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?= $progress_follows; ?>%;">
                <span><?= round($progress_follows); ?>%</span>
            </div>
        </div>
    </div>

<?php if ($current_level < 5): ?>
    <div class="level-box">
        <p><strong>Postingan Berikutnya :</strong></p>
        <p class="level-notification">
            Untuk mencapai Level <?= $current_level + 1; ?>, Anda memerlukan <strong><?= $next_level_posts - $total_posts; ?></strong> lagi (Status).
        </p>
    </div>
    <div class="level-box">
        <p><strong>Likes Berikutnya :</strong></p>
        <p class="level-notification">
            Untuk mencapai Level <?= $current_level + 1; ?>, Anda memerlukan <strong><?= $next_level_likes - $total_likes; ?></strong> lagi (Likes).
        </p>
    </div>
    <div class="level-box">
        <p><strong>Follow Berikutnya :</strong></p>
        <p class="level-notification">
            Untuk mencapai Level <?= $current_level + 1; ?>, Anda memerlukan <strong><?= $next_level_follows - $total_follows; ?></strong> lagi (Follow).
        </p>
    </div>
<?php else: ?>
    <div class="level-box">
        <p class="level-notification"><strong>Selamat!</strong> Anda telah mencapai <strong>Level Maksimal!</strong></p>
    </div>
<div class="chest-container">
    <button id="chest" class="button" onclick="unlockKey()">Buka Peti</button>
</div>

<div id="myModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Terima kasih!</h2>
        <p>Anda telah menyelesaikan progress dengan sukses.</p>
        <button id="get-key" class="button" onclick="getKey()">Dapatkan Kunci</button>
    </div>
</div>

<div id="key-animation" class="key"></div>

<div id="notification" class="notification"></div>
<?php endif; ?>
</div>

</body>
<script>
function unlockKey() {
    const key = document.getElementById('key-animation');
    key.classList.add('unlocked');

    const modal = document.getElementById("myModal");
    modal.style.display = "flex";

    fetch('key.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'unlock' }),
        headers: {
            'Content-Type': 'application/json',
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            console.log('Kunci berhasil disimpan!');
        } else {
            console.error('Gagal menyimpan kunci!');
        }
    })
    .catch(error => console.error('Error:', error));
}

function closeModal() {
    const modal = document.getElementById("myModal");
    modal.style.display = "none";
}

function getKey() {
    fetch('key.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'get_key' }),
        headers: {
            'Content-Type': 'application/json',
        },
    })
    .then(response => response.json())
    .then(data => {
        const notification = document.getElementById('notification');
        if (data.status === 'success') {
            notification.textContent = 'Kunci berhasil diterima!';
            notification.classList.remove('error');
            notification.classList.add('show');
        } else {
            notification.textContent = 'Gagal mendapatkan kunci!';
            notification.classList.add('error');
            notification.classList.add('show');
        }
       
        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
        
        closeModal();
    })
    .catch(error => console.error('Error:', error));
}
</script>
</html>