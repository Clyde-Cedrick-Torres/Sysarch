<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$id = $_GET['id'] ?? '';

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit();
}

$stmt = $conn->prepare("SELECT first_name, last_name, middle_name FROM users WHERE id_number = ?");
$stmt->bind_param("s", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $student = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'found' => true,
        'student' => $student,
        'full_name' => $student['first_name'] . ' ' . $student['last_name']
    ]);
} else {
    echo json_encode([
        'success' => true,
        'found' => false,
        'message' => 'Student not registered in system'
    ]);
}
?>