<?php
// Run this file once to generate a password hash
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password Hash: " . $hash;
echo "<br><br>";
echo "Copy this hash and use it in your SQL INSERT statement";
?>