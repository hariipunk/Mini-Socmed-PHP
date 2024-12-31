<?php
session_start();
include('db.php'); 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_user'])) {
    $username = $_POST['username'];
    $ban_message = $_POST['ban_message']; 
    
    $query = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $updateQuery = "UPDATE users SET is_banned = 1, ban_message = ? WHERE username = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("ss", $ban_message, $username);
        $updateStmt->execute();

        $success = "User '$username' berhasil dibanned.";
    } else {
        $error = "User dengan username '$username' tidak ditemukan.";
    }

    $stmt->close();
    if (isset($updateStmt)) $updateStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unban_user'])) {
    $username = $_POST['unban_username'];

    $query = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $updateQuery = "UPDATE users SET is_banned = 0, ban_message = NULL WHERE username = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("s", $username);
        $updateStmt->execute();

        $success = "User '$username' berhasil di-unban.";
    } else {
        $error = "User dengan username '$username' tidak ditemukan.";
    }

    $stmt->close();
    if (isset($updateStmt)) $updateStmt->close();
}

$allUsersQuery = "SELECT username, fullname, photo, is_banned FROM users";
$allUsersResult = $conn->query($allUsersQuery);
$allUsers = $allUsersResult->fetch_all(MYSQLI_ASSOC);

$bannedUsersQuery = "SELECT username, fullname, photo FROM users WHERE is_banned = 1";
$bannedUsersResult = $conn->query($bannedUsersQuery);
$bannedUsers = $bannedUsersResult->fetch_all(MYSQLI_ASSOC);

$user_id = $_SESSION['user_id'];
$query = "SELECT COUNT(*) AS new_messages FROM messages WHERE receiver_id = ? AND status = 0";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$new_messages = $data['new_messages'] ?? 0;

$stmt->close();

$itemsPerPage = 5;
$totalUsers = count($allUsers); 
$totalPages = ceil($totalUsers / $itemsPerPage); 
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1; 
$currentPage = max(1, min($currentPage, $totalPages)); 

$offset = ($currentPage - 1) * $itemsPerPage;

$paginatedUsers = array_slice($allUsers, $offset, $itemsPerPage);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = $_POST['username'];
    $action = $_POST['action'];

    $query = "SELECT id, is_verified FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ($action === 'verify') {
            $update_query = "UPDATE users SET is_verified = 1 WHERE id = ?";
            $message = "Verifikasi berhasil diberikan.";
        } elseif ($action === 'unverify') {
            $update_query = "UPDATE users SET is_verified = 0 WHERE id = ?";
            $message = "Verifikasi berhasil dicabut.";
        }
        $stmt_update = $conn->prepare($update_query);
        $stmt_update->bind_param("i", $user['id']);
        $stmt_update->execute();
    } else {
        $message = "Username tidak ditemukan.";
    }
}

$query_verified = "SELECT id, fullname, username, photo, role FROM users WHERE is_verified = 1";
$result_verified = $conn->query($query_verified);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PojoKan</title>
    <link rel="stylesheet" href="stylesettings.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body>
<header>
    <h1>Admin Panel</h1>
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

<div class="container">
    <h2>Verifikasi User</h2>
    <form method="POST">
        <label for="username">Username :</label>
        <input type="text" id="username" name="username" placeholder="Masukan Username" required>
        <select name="action">
            <option value="verify">Verifikasi</option>
            <option value="unverify">UnVerifikasi</option>
        </select>
        <button type="submit">Proses</button>
    </form>
    <?php if (!empty($message)): ?>
        <p><?= htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <h2>Daftar User Terverifikasi</h2>
<div class="user-list">
    <table>
        <thead>
            <tr>
                <th>Foto</th>
                <th>Fullname</th>
                <th>Username</th>
                <th>Role</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($user = $result_verified->fetch_assoc()): ?>
                <tr>
                    <td data-label="Foto">
                        <?php if (!empty($user['photo']) && file_exists($user['photo'])): ?>
                            <img src="<?= htmlspecialchars($user['photo']); ?>" alt="User Photo">
                        <?php else: ?>
                            <i class="fa-solid fa-circle-user" style="font-size: 40px;"></i>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($user['fullname']); ?></td>
                    <td>@<?= htmlspecialchars($user['username']); ?></td>
                    <td><?= htmlspecialchars($user['role']); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
    <h2>Banned User</h2>
    <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>
    <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
    <form action="settings.php" method="post">
        <label for="username">Username :</label>
        <input type="text" id="username" name="username" placeholder="Masukan Username" required>
        <label for="ban_message">Pesan Banned :</label>
        
        <textarea id="ban_message" name="ban_message" placeholder="Masukkan pesan banned" required></textarea>
        

        <button type="submit" name="ban_user">Banned</button>
    </form>

<h2>Daftar User yang Dibanned</h2>
<div class="post-container">
    <?php if (!empty($bannedUsers)): ?>
        <?php foreach ($bannedUsers as $user): ?>
        <div class="post">
            <center>
            <div class="user-photo">
                  <?php if (!empty($user['photo']) && file_exists($user['photo'])): ?>
                        <img src="<?php echo htmlspecialchars($user['photo']); ?>" alt="User Photo" class="user-photo">
                    <?php else: ?>
                        <i class="fa-solid fa-circle-user icon-default"></i>
                    <?php endif; ?>
            </div>
            <div class="user-details">
                <h2><?= htmlspecialchars($user['fullname']); ?></h2>
                <span class="username">@<?= htmlspecialchars($user['username']); ?></span>
            </div>
            <form action="settings.php" method="post">
                <input type="hidden" name="unban_username" value="<?= htmlspecialchars($user['username']); ?>">
                <button type="submit" name="unban_user">Unban</button>
            </form>
            </center>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Tidak ada user yang dibanned.</p>
    <?php endif; ?>
</div>
<h2>Daftar User Banned</h2>
<div class="user-list">
    <table>
        <thead>
            <tr>
                <th>Foto</th>
                <th>Nama Lengkap</th>
                <th>Username</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($paginatedUsers as $index => $user): ?>
                <tr>
                    <td data-label="Foto">
                        <?php if (!empty($user['photo']) && file_exists($user['photo'])): ?>
                            <img src="<?= htmlspecialchars($user['photo']); ?>" alt="User Photo">
                        <?php else: ?>
                            <i class="fa-solid fa-circle-user" style="font-size: 40px;"></i>
                        <?php endif; ?>
                    </td>
                    <td data-label="Nama Lengkap"><?= htmlspecialchars($user['fullname']); ?></td>
                    <td data-label="Username">@<?= htmlspecialchars($user['username']); ?></td>
                    <td data-label="Status">
                        <?= $user['is_banned'] ? '<span style="color: red;">Banned</span>' : '<span style="color: green;">Active</span>'; ?>
                    </td>
                    <td data-label="Aksi">
                        <?php if ($user['is_banned']): ?>
                            <form action="settings.php" method="post" style="display: inline;">
                                <input type="hidden" name="unban_username" value="<?= htmlspecialchars($user['username']); ?>">
                                <button type="submit" name="unban_user" style="background: green;">Unban</button>
                            </form>
                        <?php else: ?>
                            <form action="settings.php" method="post" style="display: inline;">
                                <input type="hidden" name="username" value="<?= htmlspecialchars($user['username']); ?>">
                                <button type="submit" name="ban_user" style="background: red;">Ban</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="pagination">
    <?php if ($currentPage > 1): ?>
        <a href="?page=<?= $currentPage - 1; ?>" class="prev">Prev</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i; ?>" class="<?= $i == $currentPage ? 'active' : ''; ?>">
            <?= $i; ?>
        </a>
    <?php endfor; ?>

    <?php if ($currentPage < $totalPages): ?>
        <a href="?page=<?= $currentPage + 1; ?>" class="next">Next</a>
    <?php endif; ?>
</div>
</div>
</body>
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
