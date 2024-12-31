<?php
session_start();
include('db.php'); 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$level_query = "SELECT level FROM users WHERE id = ?";
$stmt_level = $conn->prepare($level_query);
$stmt_level->bind_param("i", $user_id);
$stmt_level->execute();
$result_level = $stmt_level->get_result();

$show_content = false; 
if ($result_level->num_rows > 0) {
    $user = $result_level->fetch_assoc();
    if ($user['level'] >= 5) {
        $show_content = true; 
    }
} else {
    echo "<p>Terjadi kesalahan saat memeriksa level pengguna.</p>";
    exit;
}

function set_notification($message, $type) {
    $_SESSION['notification'] = [
        'message' => $message,
        'type' => $type,
        'id' => uniqid()
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action_type'] === 'like') {
    $post_id = $_POST['post_id'];

    $users_query = "SELECT id FROM users WHERE role != 'admin'";
    $users_result = $conn->query($users_query);

    while ($user = $users_result->fetch_assoc()) {
        $user_id = $user['id'];
        
        $check_like_query = "SELECT * FROM likes WHERE user_id = ? AND post_id = ?";
        $stmt_check_like = $conn->prepare($check_like_query);
        $stmt_check_like->bind_param("ii", $user_id, $post_id);
        $stmt_check_like->execute();
        $like_result = $stmt_check_like->get_result();

        if ($like_result->num_rows === 0) {
            $like_query = "INSERT INTO likes (user_id, post_id) VALUES (?, ?)";
            $stmt_like = $conn->prepare($like_query);
            $stmt_like->bind_param("ii", $user_id, $post_id);
            $stmt_like->execute();
        }
    }

    set_notification("Berhasil memberi like pada postingan dengan ID $post_id.", "success");
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

$post = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action_type'] === 'fetch_post') {
    $post_id = $_POST['post_id'];

    $post_query = "SELECT id, status FROM posts WHERE id = ?";
    $stmt_post = $conn->prepare($post_query);
    $stmt_post->bind_param("i", $post_id);
    $stmt_post->execute();
    $post_result = $stmt_post->get_result();

    if ($post_result->num_rows > 0) {
        $post = $post_result->fetch_assoc();
    } else {
        set_notification("Postingan dengan ID $post_id tidak ditemukan.", "error");
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tools</title>
    <link rel="stylesheet" href="stylesettings.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
<div class="container-cheat">
    <?php if ($show_content): ?>
        <h1 class="title-cheat">Like Tools</h1>
        <?php
        if (isset($_SESSION['notification']) && is_array($_SESSION['notification'])) {
            $notification = $_SESSION['notification'];
          
            if (isset($notification['type'], $notification['id'], $notification['message'])) {
                echo "<div class='notification-cheat {$notification['type']}' id='{$notification['id']}'>
                        {$notification['message']}
                      </div>";
                unset($_SESSION['notification']);
            } else {
                echo "<div class='notification-cheat error'>Terjadi kesalahan.</div>";
            }
        }
        ?>
        <form class="new-post-cheat" method="POST" action="">
            <label for="post_id" class="label-cheat">Masukkan ID Postingan:</label>
            <input type="number" id="post_id" name="post_id" required class="input-cheat">
            <button class="button-cheat" type="submit" name="action_type" value="fetch_post">Cari Postingan</button>
        </form>

        <div id="posts-container" class="posts-container-cheat">
            <?php
            if (!empty($post)) {
                ?>
                <div class="post">
                    <h3>Status ID: <?php echo $post['id']; ?></h3>
                    <p><?php echo $post['status']; ?></p>
                    <form method="POST" action="">
                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                        <button class="button" type="submit" name="action_type" value="like">Like</button>
                    </form>
                </div>
                <?php
            }
            ?>
        </div>
    <?php else: ?>
        <p>Maaf, fitur ini hanya tersedia untuk pengguna dengan level 5 atau lebih.</p>
    <?php endif; ?>
</div>
</body>
</html>