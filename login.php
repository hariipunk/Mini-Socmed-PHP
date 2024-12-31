<?php
session_start();
include('db.php');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Username atau Password tidak boleh kosong!";
    } else {
        $query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                if ($user['role'] === 'admin') {
                    header("Location: index.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $error = "Username atau Password salah!";
            }
        } else {
            $error = "Username atau Password salah!";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $fullname = $_POST['fullname'];
    $username = $_POST['reg_username'];
    $password = $_POST['reg_password'];
    $email = $_POST['email'];
    $alamat = $_POST['alamat'];
    $no_telepon = $_POST['no_telepon'];
    $agree = isset($_POST['agree']) ? 1 : 0;

    if (empty(trim($fullname)) || empty(trim($username)) || empty(trim($password)) || empty(trim($email))) {
        $error = "Semua kolom harus diisi!";
    } elseif (strlen(trim($username)) < 4) {
        $error = "Username harus minimal 4 karakter!";
    } elseif (strlen(trim($no_telepon)) < 10 || strlen(trim($no_telepon)) > 15) {
        $error = "Nomor telepon harus antara 10-15 angka!";
    } elseif (strlen(trim($fullname)) < 3) {
        $error = "Full Name harus minimal 3 karakter!";
    } elseif (strlen($password) < 5) {
        $error = "Password harus minimal 5 karakter!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } elseif (!$agree) {
        $error = "Anda harus menyetujui kebijakan dan privasi!";
    }
     else {
        $query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Username atau email sudah terdaftar!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (fullname, username, password, email, alamat, no_telepon, role) VALUES (?, ?, ?, ?, ?, ?, 'user')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssssss', $fullname, $username, $hashed_password, $email, $alamat, $no_telepon);
            $stmt->execute();
            $success = "Registrasi berhasil! Silakan login.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login & Register</title>
    <link rel="stylesheet" href="stylelogin.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        function toggleForm(form) {
            if (form === 'register') {
                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('registerForm').style.display = 'block';
            } else {
                document.getElementById('registerForm').style.display = 'none';
                document.getElementById('loginForm').style.display = 'block';
            }
        }

        function showNotification(message, type) {
            var notification = document.createElement('div');
            notification.classList.add('popup-notification', type);
            notification.innerText = message;
            document.body.appendChild(notification);
            setTimeout(function() {
                notification.remove();
            }, 5000); 
        }

        function showLoading() {
            var loading = document.getElementById('loading');
            loading.style.display = 'block';
        }

        function hideLoading() {
            var loading = document.getElementById('loading');
            loading.style.display = 'none';
        }
    </script>
    <script>
        function validateUsername() {
            let username = document.getElementById('reg_username').value;
            let usernameError = document.getElementById('username-error');
            if (username.length < 4) {
                usernameError.innerText = 'Username harus minimal 4 karakter!';
            } else {
                usernameError.innerText = '';
            }
        }

        function validateFullname() {
            let fullname = document.getElementById('fullname').value;
            let fullnameError = document.getElementById('fullname-error');
            if (fullname.length < 3) {
                fullnameError.innerText = 'Full Name harus minimal 3 karakter!';
            } else {
                fullnameError.innerText = '';
            }
        }

        function validatePassword() {
            let password = document.getElementById('reg_password').value;
            let passwordError = document.getElementById('password-error');
            if (password.length < 5) {
                passwordError.innerText = 'Password harus minimal 5 karakter!';
            } else {
                passwordError.innerText = '';
            }
        }

        function validateEmail() {
            let email = document.getElementById('email').value;
            let emailError = document.getElementById('email-error');
            const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!emailPattern.test(email)) {
                emailError.innerText = 'Format email tidak valid!';
            } else {
                emailError.innerText = '';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('reg_username').addEventListener('input', validateUsername);
            document.getElementById('fullname').addEventListener('input', validateFullname);
            document.getElementById('reg_password').addEventListener('input', validatePassword);
            document.getElementById('email').addEventListener('input', validateEmail);
        });
        
         function validateForm() {
            validateUsername();
            validateFullname();
            validatePassword();
            validateEmail();
        
            const fullname = document.getElementById('fullname').value.trim();
            const username = document.getElementById('reg_username').value.trim();
            const password = document.getElementById('reg_password').value.trim();
            const email = document.getElementById('email').value.trim();
        
            if (!fullname || !username || !password || !email) {
                alert('Semua kolom harus diisi dan tidak boleh hanya berupa spasi!');
                return false;
            }
        
            let errorMessages = document.querySelectorAll('span[id$="-error"]');
            let isValid = true;
            errorMessages.forEach(function(errorMessage) {
                if (errorMessage.innerText !== '') {
                    isValid = false;
                }
            });
            return isValid;
        }
    </script>
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
            	<a href="#dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log Out</a>
            <?php endif; ?>
        </div>
    </div>
</header>

    <div class="container">
        <button class="button" onclick="toggleForm('login')">Login</button>
        <button class="button" onclick="toggleForm('register')">Register</button>

        <div id="loginForm">
            <h2>Login</h2>
            <form action="login.php" method="POST">
                <label for="username">Username / Email:</label>
                <input type="text" name="username" id="username" required>

                <label for="password">Password:</label>
                <input type="password" name="password" id="password" required>

                <button type="submit" name="login" class="button">Login</button>
            </form>
        </div>

        <div id="registerForm" style="display: none;">
            <h2>Register</h2>
            <form action="login.php" method="POST" onsubmit="return validateForm()">
                <label for="fullname">Full Name:</label>
                <input type="text" name="fullname" id="fullname" required>
                <span id="fullname-error" style="color: red;"></span> 
                <label for="reg_username">Username:</label>
                <input type="text" name="reg_username" id="reg_username" required>
                <span id="username-error" style="color: red;"></span> 
                <label for="reg_password">Password:</label>
                <input type="password" name="reg_password" id="reg_password" required>
                <span id="password-error" style="color: red;"></span>
                <label for="email">Email:</label>
                <input type="email" name="email" id="email" required>
                <span id="email-error" style="color: red;"></span> 
                <label for="alamat">Alamat (Opsional):</label>
                <input type="text" name="alamat" id="alamat">

                <label for="no_telepon">Nomor Telepon:</label>
                <input type="text" name="no_telepon" id="no_telepon" required>
                <span id="no-telepon-error" style="color: red;"></span>
        
                <label for="agree">
                    <input type="checkbox" name="agree" id="agree"> I agree to the <a href="terms-and-privacy.php">Terms & Privacy Policy</a>
                </label>

                <button type="submit" name="register" class="button">Register</button>
            </form>
        </div>
    </div>

    <div id="loading" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background-color: rgba(0,0,0,0.7); padding: 10px; border-radius: 5px; color: white;">
        <span>Loading...</span>
    </div>
    
    <script>
        <?php if ($error) { echo "showNotification('$error', 'error');"; } ?>
        <?php if ($success) { echo "showNotification('$success', 'success');"; } ?>
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