<?php
$host = 'localhost'; 
$user = 'YourUserDB'; 
$pass = 'YourPasswordDB'; 
$dbname = 'YourDB'; 

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>