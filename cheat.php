<?php
session_start();
include('db.php'); 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

function set_notification($message, $type) {
    $_SESSION['notification'] = [
        'message' => $message,
        'type' => $type,
        'id' => uniqid() 
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action_type'] === 'follow') {
    $target_username = $_POST['target_username'];
    $num_followers = $_POST['num_followers']; 

    $query = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $target_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $target_user = $result->fetch_assoc();
        $target_id = $target_user['id'];

        $users_query = "SELECT id FROM users WHERE role != 'admin' LIMIT ?";
        $stmt_users = $conn->prepare($users_query);
        $stmt_users->bind_param("i", $num_followers); 
        $stmt_users->execute();
        $users_result = $stmt_users->get_result();

        $followed_count = 0;

        while ($user = $users_result->fetch_assoc()) {
            if ($followed_count >= $num_followers) break; 
            
            $user_id = $user['id'];

            $check_follow_query = "SELECT * FROM follows WHERE follower_id = ? AND following_id = ?";
            $stmt_check_follow = $conn->prepare($check_follow_query);
            $stmt_check_follow->bind_param("ii", $user_id, $target_id);
            $stmt_check_follow->execute();
            $follow_result = $stmt_check_follow->get_result();

            if ($follow_result->num_rows === 0) { 
                $follow_query = "INSERT INTO follows (follower_id, following_id, created_at) VALUES (?, ?, NOW())";
                $stmt_follow = $conn->prepare($follow_query);
                $stmt_follow->bind_param("ii", $user_id, $target_id);
                $stmt_follow->execute();
                $followed_count++;
            }
        }

        set_notification("Berhasil mengikuti $followed_count user non-admin untuk user '$target_username'.", "success");
    } else {
        set_notification("Username '$target_username' tidak ditemukan.", "error");
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
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

    set_notification("Semua user non-admin berhasil memberi like pada postingan dengan ID $post_id.", "success");
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action_type'] === 'fetch_posts') {
    $target_username = $_POST['target_username'];
    
    $query = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $target_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $target_user = $result->fetch_assoc();
        $target_id = $target_user['id'];

        $post_query = "SELECT id, status FROM posts WHERE user_id = ?";
        $stmt_post = $conn->prepare($post_query);
        $stmt_post->bind_param("i", $target_id);
        $stmt_post->execute();
        $post_result = $stmt_post->get_result();

        if ($post_result->num_rows > 0) {
            $posts = [];
            while ($post = $post_result->fetch_assoc()) {
                $posts[] = $post;
            }
            echo json_encode($posts); 
            exit();
        } else {
            set_notification("Tidak ada postingan untuk user '$target_username'.", "error");
        }
    } else {
        set_notification("Username '$target_username' tidak ditemukan.", "error");
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

$username = $_SESSION['username']; 

$query = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_status = isset($_POST['auto_like']) ? 1 : 0; 
    $target_usernames = $_POST['target_usernames']; 

    $usernames_array = explode(',', $target_usernames);
    foreach ($usernames_array as $target_username) {
        $target_username = trim($target_username); 

        $query = "UPDATE users SET auto_like = ? WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $current_status, $target_username);
        $stmt->execute();
    }

    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

if (isset($_POST['disable_auto_like'])) {
    $target_username = $_POST['target_usernames']; 
    $query = "UPDATE users SET auto_like = 0 WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $target_username);
    $stmt->execute();

    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

$query_users = "SELECT username FROM users WHERE auto_like = 1";
$result_users = $conn->query($query_users);
$auto_liked_users = [];
while ($row = $result_users->fetch_assoc()) {
    $auto_liked_users[] = $row['username'];
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cheat Auto Like & Follow</title>
    <link rel="stylesheet" href="stylesettings.css">
     <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <script>
        function fetchPosts() {
            const username = document.getElementById('target_username').value;
            
            const formData = new FormData();
            formData.append('action_type', 'fetch_posts');
            formData.append('target_username', username);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const postsContainer = document.getElementById('posts-container');
                postsContainer.innerHTML = ''; 
                
                data.forEach(post => {
                    const postElement = document.createElement('div');
                    postElement.classList.add('post');
                    postElement.innerHTML = `
                        <h3>Postingan ID: ${post.id}</h3>
                        <p>${post.status}</p>
                        <form method="POST" action="">
                            <input type="hidden" name="post_id" value="${post.id}">
                            <button class="button" type="submit" name="action_type" value="like">Like Postingan Ini</button>
                        </form>
                    `;
                    postsContainer.appendChild(postElement);
                });
            })
            .catch(error => console.error('Error fetching posts:', error));
        }
    </script>
</head>
<body>
<div class="container-cheat">
    <h1 class="title-cheat">Cheat Like</h1>
   
    <?php
if (isset($_SESSION['notification']) && is_array($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
  
    if (isset($notification['type'], $notification['id'], $notification['message'])) {
        echo "<div class='notification-cheat {$notification['type']}' id='{$notification['id']}'>
                {$notification['message']}
              </div>";
        unset($_SESSION['notification']); 
    } else {
     
        echo "<div class='notification-cheat error'>Terjadi kesalahan dalam notifikasi.</div>";
    }
}
?>
    <form class="new-post-cheat" method="POST" action="">
        <label for="target_username" class="label-cheat">Username Target :</label>
        <input type="text" id="target_username" name="target_username" required class="input-cheat">
        <button class="button-cheat" type="button" onclick="fetchPosts()">Cari Postingan</button>
    </form>

    <div id="posts-container" class="posts-container-cheat"></div>
</div>
<div class="container-cheat">
    <h1 class="title-cheat">Cheat Followers</h1>
    <form method="POST" action="" class="follow-form-cheat">
        <label for="target_username_follow" class="label-cheat">Username Target :</label>
        <input type="text" id="target_username_follow" name="target_username" required class="input-cheat">
        
        <label for="num_followers" class="label-cheat">Jumlah Followers :</label>
        <input type="number" id="num_followers" name="num_followers" min="1" required class="input-cheat">
        
        <button class="button-cheat" type="submit" name="action_type" value="follow">Follow User</button>
    </form>
</div>

<div class="container-cheat">
    <h1 class="title-cheat">Auto Like</h1>
    <form method="POST" action="" class="auto-like-form">
        <label for="target_usernames" class="label-cheat">Username Target (pisahkan dengan koma):</label>
        <input type="text" id="target_usernames" name="target_usernames" value="<?php echo htmlspecialchars($user['target_usernames']); ?>" class="input-cheat" placeholder="username1, username2, username3">

        <div class="checkbox-container">
            <label for="auto_like" class="label-cheat">Aktifkan Auto Like:</label>
            <input type="checkbox" id="auto_like" name="auto_like" value="1" 
            <?php echo ($user['auto_like'] == 1) ? 'checked' : ''; ?> >
        </div>

        <button class="button-cheat" type="submit" name="action_type" value="update_auto_like">Update Pengaturan</button>
    </form>

    <div class="user-list">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Username</th>
                    <th>Aksi</th> 
                </tr>
            </thead>
            <tbody>
                <?php
                if (count($auto_liked_users) > 0) {
                    $no = 1;
                    foreach ($auto_liked_users as $username) {
                        echo "<tr><td>{$no}</td><td>{$username}</td>";
                        echo "<td>
                                <form method='POST' action='' style='display:inline;'>
                                    <input type='hidden' name='target_usernames' value='{$username}'>
                                    <button type='submit' name='disable_auto_like' class='button-cheat' style='background-color: red;'>Nonaktifkan Auto Like</button>
                                </form>
                              </td></tr>";
                        $no++;
                    }
                } else {
                    echo "<tr><td colspan='3'>Tidak ada user dengan Auto Like aktif.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>