<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "magarbo_system";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

date_default_timezone_set('Asia/Manila');
mysqli_query($conn, "SET time_zone = '+08:00'");
?>