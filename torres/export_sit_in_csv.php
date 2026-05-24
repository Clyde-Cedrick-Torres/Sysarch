<?php
session_start();
include 'db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Set headers to force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="sit_in_report_' . date('Y-m-d_H-i-s') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, [
    'Record ID',
    'Student ID',
    'Student Name',
    'Purpose',
    'Programming Language',
    'Lab Room',
    'Remaining Session (mins)',
    'Time In',
    'Time Out',
    'Duration',
    'Status',
    'Created At'
]);

// Fetch all sit-in records with student info
$query = "SELECT * FROM sit_in_records ORDER BY time_in DESC";
$result = $conn->query($query);

// Add data rows
while($row = $result->fetch_assoc()) {
    // Calculate duration if time_out exists
    $duration = '';
    if ($row['time_out'] && $row['time_in']) {
        $time_in = new DateTime($row['time_in']);
        $time_out = new DateTime($row['time_out']);
        $diff = $time_in->diff($time_out);
        $duration = $diff->h . 'h ' . $diff->i . 'm';
    }
    
    fputcsv($output, [
        $row['id'],
        $row['student_id'],
        $row['student_name'],
        $row['purpose'],
        $row['programming_lang'],
        $row['lab_room'],
        $row['remaining_session'],
        $row['time_in'],
        $row['time_out'] ?? 'N/A',
        $duration,
        $row['status'],
        $row['created_at']
    ]);
}

// Close output stream
fclose($output);
$conn->close();
exit();
?>