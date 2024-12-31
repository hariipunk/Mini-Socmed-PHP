<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$limit = 5; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_query = "SELECT COUNT(*) AS total FROM posts WHERE user_id = '$user_id'";
$total_result = $conn->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_posts = $total_row['total'];
$total_pages = ceil($total_posts / $limit);

$query = "
    SELECT 
        p.id AS post_id,
        p.status,
        p.image,
        p.video,
        p.audio,
        COUNT(DISTINCT l.id) AS total_likes,
        COUNT(DISTINCT c.id) AS total_comments
    FROM 
        posts p
    LEFT JOIN 
        likes l ON p.id = l.post_id
    LEFT JOIN 
        comments c ON p.id = c.post_id
    WHERE 
        p.user_id = '$user_id'
    GROUP BY 
        p.id
    ORDER BY 
        p.created_at DESC
    LIMIT $limit OFFSET $offset
";

$result = $conn->query($query);
$posts = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
}

$max_query = "
    SELECT 
        MAX(total_likes) AS max_likes, 
        MAX(total_comments) AS max_comments 
    FROM (
        SELECT 
            COUNT(DISTINCT l.id) AS total_likes, 
            COUNT(DISTINCT c.id) AS total_comments 
        FROM 
            posts p
        LEFT JOIN 
            likes l ON p.id = l.post_id
        LEFT JOIN 
            comments c ON p.id = c.post_id
        WHERE 
            p.user_id = '$user_id'
        GROUP BY 
            p.id
    ) AS subquery
";
$max_result = $conn->query($max_query);
$max_data = $max_result->fetch_assoc();
$max_likes = $max_data['max_likes'];
$max_comments = $max_data['max_comments'];

$user_id = $_SESSION['user_id']; 
$query = "SELECT COUNT(*) AS new_messages FROM messages WHERE receiver_id = ? AND status = 0";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$new_messages = $data['new_messages'];

$follower_query = "
    SELECT 
        COUNT(f.follower_id) AS current_followers,
        (
            SELECT COUNT(f1.follower_id)
            FROM follows f1
            WHERE f1.following_id = '$user_id'
            AND DATE(f1.created_at) = CURDATE() - INTERVAL 1 DAY
        ) AS previous_followers,
        COUNT(f.follower_id) AS total_followers
    FROM follows f
    WHERE f.following_id = '$user_id'
    AND DATE(f.created_at) = CURDATE()
";

$follower_result = $conn->query($follower_query);
$follower_data = $follower_result->fetch_assoc();

$current_followers = $follower_data['current_followers'];
$previous_followers = $follower_data['previous_followers'];
$total_followers = $follower_data['total_followers'];

$percent_change = ($previous_followers > 0) 
    ? (($current_followers - $previous_followers) / $previous_followers) * 100
    : 0;

$followers_query = "
    SELECT u.fullname, u.username
    FROM follows f
    JOIN users u ON u.id = f.follower_id
    WHERE f.following_id = '$user_id'
";

$followers_result = $conn->query($followers_query);
$followers = [];
if ($followers_result->num_rows > 0) {
    while ($follower = $followers_result->fetch_assoc()) {
        $followers[] = $follower;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insight</title>
    <link rel="stylesheet" href="styleprofile.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<header>
    <h1>PojoKan Insight</h1>
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
    <div class="progress-container">
<h1>Followers Data</h1>
<div class="level-box">
    <strong>Followers Today : <?= $current_followers ?> 
    <?php if ($percent_change > 0): ?>
    <span class="change-up"><?= round($percent_change, 2) ?>%</span>
    <?php elseif ($percent_change < 0): ?>
    <span class="change-down"><?= round(abs($percent_change), 2) ?>%</span>
    <?php else: ?>
    <span class="change-no-change"></span>
    <?php endif; ?>
    </strong> 
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?= min($current_followers / 100 * 100, 100) ?>%;">
                <?= $current_followers ?> 
            </div>
        </div>
    </p>

<?php if (count($followers) > 0): ?>
    <?php 
    $follower = $followers[count($followers) - 1]; 
    ?>
    
    <strong>Followers :</strong> <?= htmlspecialchars($follower['fullname']) ?> <br>
    <strong>Username :</strong> <?= htmlspecialchars($follower['username']) ?>

<?php else: ?>
    <p>Tidak ada Follower</p>
<?php endif; ?> 
</div>
        <h1>Your Insight</h1>
        <?php if (count($posts) > 0): ?>
            <?php foreach ($posts as $post): ?>
                <div class="level-box <?= ($post['total_likes'] == $max_likes || $post['total_comments'] == $max_comments) ? 'highlight' : '' ?>">
                    <div>
                        <strong>Jenis Post :</strong> 
                        <?php 
                        if (!empty($post['status'])) {
                            echo "Status";
                        } elseif (!empty($post['image'])) {
                            echo "Image";
                        } elseif (!empty($post['video'])) {
                            echo "Video";
                        } elseif (!empty($post['audio'])) {
                            echo "Audio";
                        } else {
                            echo "Tidak Diketahui";
                        }
                        ?>
                    </div>

                    <div>
                        <strong>Konten :</strong> 
                        <?php 
                        if (!empty($post['status'])) {
                            echo htmlspecialchars($post['status']);
                        } elseif (!empty($post['image'])) {
                            echo "<img src='uploads/{$post['image']}' alt='Image' width='100'>";
                        } elseif (!empty($post['video'])) {
                            echo "<video src='uploads/{$post['video']}' controls width='100'></video>";
                        } elseif (!empty($post['audio'])) {
                            echo "<audio src='uploads/{$post['audio']}' controls></audio>";
                        } else {
                            echo "Tidak Ada Konten";
                        }
                        ?>
                    </div>

                    <div>
                        <strong>Jumlah Like :</strong> <?= $post['total_likes'] ?> Likes
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= min($post['total_likes'] / 100 * 100, 100) ?>%;">
                                <?= $post['total_likes'] ?>
                            </div>
                        </div>
                    </div>

                    <div>
                        <strong>Jumlah Komentar :</strong> <?= $post['total_comments'] ?> Komentar
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= min($post['total_comments'] / 100 * 100, 100) ?>%;">
                                <?= $post['total_comments'] ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="button">Sebelumnya</a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="button">Selanjutnya</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>Anda belum memiliki postingan.</p>
        <?php endif; ?>
    </div>
</body>
</html>