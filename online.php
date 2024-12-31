<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('db.php');

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; 
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$conn->query("UPDATE users SET is_online = 1 WHERE id = $current_user_id");

$sql = "SELECT u.id, u.fullname, u.username, u.photo
        FROM users u
        JOIN follows f ON (u.id = f.follower_id OR u.id = f.following_id)
        WHERE (f.follower_id = $current_user_id OR f.following_id = $current_user_id)
          AND u.is_online = 1
        GROUP BY u.id";
$result = $conn->query($sql);

$users_online = [];
while ($row = $result->fetch_assoc()) {
    $users_online[] = $row;
}

// Memisahkan user yang sedang login
$logged_in_user = null;
foreach ($users_online as $key => $user) {
    if ($user['id'] == $current_user_id) {
        $logged_in_user = $user;
        unset($users_online[$key]); // Menghapus user yang sedang login dari array
        break;
    }
}

// Menambahkan user yang sedang login di awal array
if ($logged_in_user) {
    array_unshift($users_online, $logged_in_user);
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode($users_online);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Users</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
#online-users {
    display: flex;
    overflow-x: auto;
    gap: 10px;
    padding: 10px;
}

.user-card {
    text-align: center;
}

.user-card img {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
}

.user-card .status {
    position: relative;
}

.user-card .status::after {
    content: '';
    position: absolute;
    bottom: 5px;
    right: 5px;
    width: 10px;
    height: 10px;
    background-color: green;
    border-radius: 50%;
    border: 2px solid white;
}

.user-card p {
    font-size: 14px;
    margin-top: 5px;
}
    </style>
</head>
<body>
    <div id="online-users">
    </div>    
<script>
    function loadOnlineUsers() {
        $.ajax({
            url: 'online.php?ajax=1',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                let html = '';
                data.forEach(user => {
                    html += `
                        <div class="user-card">
                            <div class="status">
                                <img src="${user.photo || 'avatar.png'}" alt="${user.fullname}">
                            </div>
                           <span>${user.fullname}</span>
                        </div>`;
                });
                $('#online-users').html(html);
            },
            error: function(err) {
                console.error('Error fetching online users:', err);
            }
        });
    }

    setInterval(loadOnlineUsers, 5000);
    loadOnlineUsers();
</script>
</body>
</html>