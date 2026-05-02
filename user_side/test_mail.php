<?php
require_once 'mailer.php';

$result = sendMail(
    'YOUR_EMAIL@gmail.com', // lagay mo sariling email mo
    'Test User',
    'Magarbo Test Email',
    '<h2>SUCCESS!</h2><p>Gumagana na ang email system mo.</p>'
);

if ($result === true) {
    echo "Email sent successfully!";
} else {
    echo "Error: " . $result;
}