<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "magarbo_system";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

/* FORCE TIMEZONE */
date_default_timezone_set('Asia/Manila');
mysqli_query($conn, "SET time_zone = '+08:00'");
?>