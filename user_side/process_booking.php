<?php
session_name('USERSESSID');
session_start();
include '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Siguraduhin na naka-login ang user
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    // Imbak lahat ng detalye sa session
    $_SESSION['temp_booking'] = [
        'user_id'    => $_SESSION['user_id'],
        'event_type' => $_POST['event_type'],
        'event_date' => $_POST['event_date'],
        'location'   => $_POST['location'],
        'notes'      => $_POST['notes'] ?? '',
        'downpayment'=> 2000 // Fixed downpayment rule mo
    ];

    // Diretso sa payment page
    header("Location: payment.php");
    exit();
}