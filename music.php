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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_status'])) {
        $judul = htmlspecialchars(trim($_POST['status']));
        $user_id = $_SESSION['user_id'];
        $audio_path = null;
        
        if (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['audio'];
            $allowed_audio_types = ['audio/mpeg', 'audio/mp3', 'audio/wav'];

            if (in_array($file['type'], $allowed_audio_types)) {
                $username = basename(getUsernameById($user_id, $conn));
                $upload_dir = "uploads/{$username}/audio/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $audio_filename = uniqid("audio_", true) . '.' . $extension;
                $audio_path = $upload_dir . $audio_filename;

                if (!move_uploaded_file($file['tmp_name'], $audio_path)) {
                    die("Gagal mengunggah audio.");
                }
            } else {
                die("Tipe file audio tidak valid.");
            }
        }

        $query = "INSERT INTO posts (user_id, status, audio) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iss', $user_id, $judul, $audio_path);
        $stmt->execute();

        header('Location: music.php');
        exit;
    }
}
	
function getMusics($user_id, $conn) {
    $query = "
        SELECT posts.*, users.username, users.fullname, users.photo
        FROM posts 
        JOIN users ON posts.user_id = users.id
        WHERE posts.audio IS NOT NULL
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
    $musics = getMusics($user_id, $conn);
    
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
<body-a>
<header>
    <h1>PojoKan <i class="fa-solid fa-music"></i></h1>
     <div class="right-icons-a">
    <div class="toggle-upload-form-a">
        <button id="toggle-form-btn" class="icon-toggle-form-a">
            <i class="fas fa-plus"></i> 
        </button>
    </div>
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
     </div>
</header>
<div class="container-a">
<?php if (isset($_SESSION['user_id'])): ?>
    <?php
    $user_id = $_SESSION['user_id'];
        $query = "SELECT is_banned,    ban_message FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($is_banned, $ban_message);
        $stmt->fetch();
        $stmt->close();
    ?>
    
    <?php if ($is_banned == 1): ?>
    <p style="color: red; text-align: center; font-size: 16px; margin-top: 50px;"><?= htmlspecialchars($ban_message); ?></p>
    <?php else: ?>
        <div class="new-post-a" id="upload-form-a" style="display: none;">
            <form action="music.php" method="POST" enctype="multipart/form-data" class="upload-audio-form-a">
                <textarea name="status" placeholder="Masukkan judul musik Anda" required></textarea>
                
                <label for="upload-audio-a" class="upload-icon-label-a">
                    <i class="fas fa-music"></i> Pilih file audio
                </label>
                
                <input type="file" id="upload-audio-a" name="audio" accept="audio/mpeg, audio/mp3, audio/wav" required>
                
                <div id="preview-container-a">             
                </div>
                
                <button type="submit" name="submit_status" class="button-a">Unggah Musik</button>
            </form>
        </div>
    <?php endif; ?>
<?php endif; ?>
    <?php if ($musics->num_rows > 0): ?>
    <?php while ($music = $musics->fetch_assoc()): ?>
        <?php if (!empty($music['audio']) && file_exists($music['audio'])): ?>
        <div class="post-a">
            <div class="user-info-a">
                <div class="user-photo-a">
                <a href="dashboard.php?user_id=<?php echo htmlspecialchars($music['user_id']); ?>">
                    <?php if (!empty($music['photo']) && file_exists($music['photo'])): ?>
                        <img src="<?php echo htmlspecialchars($music['photo']); ?>" alt="User Photo" class="user-photo-a">
                    <?php else: ?>
                        <i class="fa-solid fa-circle-user icon-default-a"></i>
                    <?php endif; ?>
                </a>
                </div>                    
                <div class="user-details-a">
                    <h2><a href="dashboard.php?user_id=<?php echo htmlspecialchars($music['user_id']); ?>" class="no-style-link-a">
                        <?php echo htmlspecialchars($music['fullname']); ?>
                    </a></h2>
                    <span class="username-a">@<?php echo htmlspecialchars($music['username']); ?></span> 
                </div>
            </div>
            <h2 class="title-a"><?php echo nl2br(htmlspecialchars($music['status'])); ?></h2>
            <div class="post-audio-a">
                <div class="visualizer-a" id="visualizer-<?php echo $music['id']; ?>">
                    <div class="bar-a"></div>
                    <div class="bar-a"></div>
                    <div class="bar-a"></div>
                    <div class="bar-a"></div>
                    <div class="bar-a"></div>
                </div>                	
                <div class="control-button-a" onclick="togglePlay(this)">
                    <i class="fas fa-play"></i> 
                </div>
                <div class="progress-container-a">
                    <div class="progress-bar-a" onclick="setProgress(event, this)">
                        <div class="progress-bar-inner-a"></div>
                    </div>
                    
                    <div class="time-container-a">
                        <span class="current-time-a">0:00</span> / <span class="total-duration-a">0:00</span>
                    </div>
                </div>

                <audio id="audio-<?php echo $music['id']; ?>">
                    <source src="<?php echo htmlspecialchars($music['audio']); ?>" type="audio/mpeg">
                    Your browser does not support the audio element.
                </audio>
            </div>
            <?php endif; ?>
            <div class="post-footer-a">
                <div class="like-footer-a">
                    <form action="music.php" method="POST" class="like-form-a">
                        <button type="button" class="like-button-a">
                            <i class="fas fa-thumbs-up <?php echo (isset($_SESSION['user_id']) && isLiked($music['id'], $_SESSION['user_id'], $conn)) ? 'liked-a' : ''; ?>"></i>
                            <span><?php echo getLikeCount($music['id'], $conn); ?> Likes</span>
                        </button>
                        <input type="hidden" name="post_id" value="<?php echo $music['id']; ?>">
                    </form>
                </div>
                <div class="comment-footer-a">
                    <form action="comment.php" method="GET" class="comment-form-a">
                        <button type="submit" class="comment-button-a">
                            <i class="fas fa-comment"></i>
                            <span><?php echo getCommentCount($music['id'], $conn); ?> Comments</span>
                        </button>
                        <input type="hidden" name="post_id" value="<?php echo $music['id']; ?>">
                    </form>
                </div>
                <div class="post-footer-a">
                    <?php echo timeAgo($music['created_at']); ?>
                </div>
            </div>
        </div> 
    <?php endwhile; ?>
    <?php else: ?>
        <p>No music available.</p>
    <?php endif; ?>
</div>
</body-a>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
<script>
    function togglePlay(button) {
        const audio = button.closest('.post-audio-a').querySelector('audio');
        const icon = button.querySelector('i');
        const currentTimeElem = button.closest('.post-audio-a').querySelector('.current-time-a');
        const totalDurationElem = button.closest('.post-audio-a').querySelector('.total-duration-a');
        const visualizerBars = button.closest('.post-audio-a').querySelectorAll('.visualizer-a .bar-a');

        if (audio.paused) {
            audio.play();
            icon.classList.remove('fa-play');
            icon.classList.add('fa-pause');
        } else {
            audio.pause();
            icon.classList.remove('fa-pause');
            icon.classList.add('fa-play');
        }

        audio.ontimeupdate = function () {
            const currentMinutes = Math.floor(audio.currentTime / 60);
            const currentSeconds = Math.floor(audio.currentTime % 60).toString().padStart(2, '0');
            currentTimeElem.textContent = `${currentMinutes}:${currentSeconds}`;

            const progressBar = button.closest('.post-audio-a').querySelector('.progress-bar-inner-a');
            const percentage = (audio.currentTime / audio.duration) * 100;
            progressBar.style.width = percentage + '%';
        };

        audio.onended = function () {
            icon.classList.remove('fa-pause');
            icon.classList.add('fa-play');
            resetVisualizer(visualizerBars); // Reset visualizer saat audio selesai
        };
    }

    // Fungsi untuk memindahkan progress
    function setProgress(event, progressBar) {
        const audio = progressBar.closest('.post-audio-a').querySelector('audio');
        const width = progressBar.offsetWidth;
        const clickX = event.offsetX;
        const duration = audio.duration;

        audio.currentTime = (clickX / width) * duration;
    }

    function createAudioVisualizer(audio, visualizerBars) {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const analyser = audioContext.createAnalyser();
        const source = audioContext.createMediaElementSource(audio);

        source.connect(analyser);
        analyser.connect(audioContext.destination);

        analyser.fftSize = 64; 
        const bufferLength = analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);

        function updateVisualizer() {
            analyser.getByteFrequencyData(dataArray);

            visualizerBars.forEach((bar, index) => {
                const value = dataArray[index] || 0;
                const percentage = (value / 255) * 100;
                bar.style.height = `${10 + percentage / 2}px`;
            });

            if (!audio.paused) {
                requestAnimationFrame(updateVisualizer);
            }
        }

        audio.addEventListener('play', function () {
            audioContext.resume().then(() => updateVisualizer());
        });

        audio.addEventListener('pause', function () {
            resetVisualizer(visualizerBars); 
        });
    }

    // Fungsi untuk mereset tinggi visualizer
    function resetVisualizer(visualizerBars) {
        visualizerBars.forEach(bar => (bar.style.height = '10px')); 
    }

    document.querySelectorAll('.post-audio-a').forEach(postAudio => {
        const audio = postAudio.querySelector('audio');
        const visualizerBars = postAudio.querySelectorAll('.visualizer-a .bar-a');

        audio.addEventListener('loadedmetadata', function () {
            const totalDurationElem = postAudio.querySelector('.total-duration-a');
            const totalMinutes = Math.floor(audio.duration / 60);
            const totalSeconds = Math.floor(audio.duration % 60).toString().padStart(2, '0');
            totalDurationElem.textContent = `${totalMinutes}:${totalSeconds}`;
        });

        createAudioVisualizer(audio, visualizerBars);
    });
</script>
<script>
$(document).ready(function() {
    $(".like-button-a").on("click", function(e) {
        e.preventDefault();
        
        var postId = $(this).closest("form").find("input[name='post_id']").val();
        var button = $(this); // Tombol like yang diklik

        // Cek apakah pengguna sudah login
        <?php if (isset($_SESSION['user_id'])): ?>
            $.ajax({
                url: 'music.php', 
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
document.getElementById("toggle-form-btn").addEventListener("click", function() {
    var form = document.getElementById("upload-form-a");
    if (form.style.display === "none") {
        form.style.display = "block"; 
    } else {
        form.style.display = "none"; 
    }
});

document.getElementById("upload-audio-a").addEventListener("change", function(event) {
    const previewContainer = document.getElementById("preview-container-a");
    previewContainer.innerHTML = ""; 
    const file = event.target.files[0]; 
    if (file) {
        const audio = document.createElement("audio");
        audio.src = URL.createObjectURL(file); 
        audio.controls = true; 
        audio.style.marginTop = "10px"; 
        previewContainer.appendChild(audio);
    }
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