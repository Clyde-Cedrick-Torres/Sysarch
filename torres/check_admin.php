<?php
include 'db_connect.php';
$result = $conn->query("SELECT id, username, name FROM admins");
echo "<h2>Admin Accounts in Database:</h2>";
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Username: " . $row['username'] . " | Name: " . $row['name'] . "<br>";
    }
} else {
    echo "No admin accounts found!";
}
?>