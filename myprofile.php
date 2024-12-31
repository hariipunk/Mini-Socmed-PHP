<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$query = "SELECT last_username_change, last_email_change FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

$last_username_change = strtotime($user_data['last_username_change']);
$last_email_change = strtotime($user_data['last_email_change']);
$current_time = time();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $fullname = $_POST['fullname'];
    $alamat = $_POST['alamat'];
    $no_telepon = $_POST['no_telepon'];
    $username = ($_POST['username'] != $user['username'] && $current_time - $last_username_change >= 7 * 24 * 60 * 60) ? $_POST['username'] : $user['username'];
    $email = ($_POST['email'] != $user['email'] && $current_time - $last_email_change >= 30 * 24 * 60 * 60) ? $_POST['email'] : $user['email'];
    
if (strlen($fullname) < 4) {
    $_SESSION['notification'] = [
        'type' => 'error',
        'message' => 'Full name harus minimal 4 karakter.',
    ];
    header("Location: myprofile.php");
    exit;
}

if (strlen($username) < 4) {
    $_SESSION['notification'] = [
        'type' => 'error',
        'message' => 'Username harus minimal 4 karakter.',
    ];
    header("Location: myprofile.php");
    exit;
}

if (strlen($no_telepon) < 10 || strlen($no_telepon) > 15) {
    $_SESSION['notification'] = [
        'type' => 'error',
        'message' => 'Nomor telepon harus 10-15 karakter.',
    ];
    header("Location: myprofile.php");
    exit;
}

    $photo = $user['photo']; 
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed_types = ['image/png', 'image/jpg', 'image/jpeg'];
        $file_type = mime_content_type($_FILES['photo']['tmp_name']);
        if ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'Ukuran file tidak boleh lebih dari 2MB.',
            ];
            header("Location: myprofile.php");
            exit;
        }
        if (in_array($file_type, $allowed_types)) {
            $username = preg_replace('/[^a-zA-Z0-9-_]/', '', $user['username']);
            $upload_dir = "uploads/$username/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);

            $base_name = $upload_dir . $user_id . "profilepic";
            $new_file = $base_name . "." . $extension;
            $counter = 1;

            while (file_exists($new_file)) {
                $new_file = $base_name . $counter . "." . $extension;
                $counter++;
            }

            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $new_file)) {
                $_SESSION['notification'] = [
                    'type' => 'error',
                    'message' => 'Gagal mengunggah file.',
                ];
                header("Location: myprofile.php");
                exit;
            }
            $photo = $new_file;
        } else {
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'Hanya file PNG, JPG, dan JPEG yang diperbolehkan.',
            ];
            header("Location: myprofile.php");
            exit;
        }
    }

    $cover_photo = $user['cover_photo']; 
    if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] == 0) {
        $allowed_types = ['image/png', 'image/jpg', 'image/jpeg'];
        $file_type = mime_content_type($_FILES['cover_photo']['tmp_name']);
        if ($_FILES['cover_photo']['size'] > 5 * 1024 * 1024) {
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'Ukuran file cover photo tidak boleh lebih dari 5MB.',
            ];
            header("Location: myprofile.php");
            exit;
        }
        if (in_array($file_type, $allowed_types)) {
            $username = preg_replace('/[^a-zA-Z0-9-_]/', '', $user['username']);
            $upload_dir = "uploads/$username/coverphoto/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $extension = pathinfo($_FILES['cover_photo']['name'], PATHINFO_EXTENSION);

            $base_name = $upload_dir . "coverphoto";
            $new_file = $base_name . "." . $extension;
            $counter = 1;

            while (file_exists($new_file)) {
                $new_file = $base_name . $counter . "." . $extension;
                $counter++;
            }

            if (!move_uploaded_file($_FILES['cover_photo']['tmp_name'], $new_file)) {
                $_SESSION['notification'] = [
                    'type' => 'error',
                    'message' => 'Gagal mengunggah cover photo.',
                ];
                header("Location: myprofile.php");
                exit;
            }
            $cover_photo = $new_file;
        } else {
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'Hanya file PNG, JPG, dan JPEG yang diperbolehkan untuk cover photo.',
            ];
            header("Location: myprofile.php");
            exit;
        }
    }

    $query = "UPDATE users SET fullname = ?, username = ?, email = ?, photo = ?, cover_photo = ?, alamat = ?, no_telepon =? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssssssi', $fullname, $username, $email, $photo, $cover_photo, $alamat, $no_telepon, $user_id);
    $stmt->execute();

    if ($username != $user['username']) {
        $query = "UPDATE users SET last_username_change = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
    }
    if ($email != $user['email']) {
        $query = "UPDATE users SET last_email_change = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
    }

    $_SESSION['notification'] = [
        'type' => 'success',
        'message' => 'Profil berhasil diperbarui.',
    ];

    header("Location: myprofile.php");
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="styleprofile.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<header>
    <h1>PojoKan</h1>
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
    <h2>Edit Profile</h2>
    <form action="myprofile.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="fullname">Full Name</label>
            <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" minlength="4" required>
        </div>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" minlength="4"
                <?php if ($current_time - $last_username_change < 7 * 24 * 60 * 60) echo 'readonly'; ?> required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                <?php if ($current_time - $last_email_change < 30 * 24 * 60 * 60) echo 'readonly'; ?> required>
        </div>
        <div class="form-group">
            <label for="alamat">Alamat</label>
            <input type="text" id="alamat" name="alamat" value="<?php echo htmlspecialchars($user['alamat']); ?>" required>
        </div>
        <div class="form-group">
            <label for="no_telepon">Nomor</label>
            <input type="text" id="no_telepon" name="no_telepon" value="<?php echo htmlspecialchars($user['no_telepon']); ?>" minlength="10" maxlength="15" required>
        </div>
        <div class="form-group">
            <label for="photo">Profile Photo</label>
            <input type="file" id="photo" name="photo" accept="image/*">
<p>
            	        <label for="cover_photo">Cover Photo</label>
        <input type="file" name="cover_photo" id="cover_photo" accept="image/*">
  
        </div>
 <div class="photo-box">
    <div class="cover-photo-container">
        <?php if (!empty($user['cover_photo']) && file_exists($user['cover_photo'])): ?>
            <img src="<?php echo htmlspecialchars($user['cover_photo']); ?>" alt="Cover Photo" class="cover-photo-img">
        <?php else: ?>
            <p class="no-photo-message">No cover photo uploaded</p>
        <?php endif; ?>
    </div>

    <div class="user-photo-container">
        <?php if (!empty($user['photo']) && file_exists($user['photo'])): ?>
            <img src="<?php echo htmlspecialchars($user['photo']); ?>" alt="User Photo" class="user-photo">
        <?php else: ?>
            <p class="no-photo-message">No profile photo uploaded</p>
        <?php endif; ?>
    </div>
 </div>
        <button type="submit" name="update_profile" class="button">Update Profile</button>
    </form>
</div>

<div id="popup-notification" class="popup-notification" style="display: none;"></div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        <?php if (isset($_SESSION['notification'])): ?>
            const popup = document.getElementById('popup-notification');
            popup.className = `popup-notification <?php echo $_SESSION['notification']['type']; ?>`;
            popup.innerText = '<?php echo $_SESSION['notification']['message']; ?>';
            popup.style.display = 'block';

            setTimeout(() => {
                popup.style.display = 'none';
            }, 3000);
            <?php unset($_SESSION['notification']); ?>
        <?php endif; ?>
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