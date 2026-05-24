<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please login first'); window.close();</script>";
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Fetch user info
$stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id_number = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$student_name = $user['first_name'] . ' ' . $user['last_name'];

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $feedback_type = $_POST['feedback_type'];
    $subject = $_POST['subject'];
    $message_content = $_POST['message'];
    $lab_room = $_POST['lab_room'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO feedback (student_id, student_name, feedback_type, subject, message, lab_room, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssss", $user_id, $student_name, $feedback_type, $subject, $message_content, $lab_room);
    
    if ($stmt->execute()) {
        $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4'>✅ Feedback submitted successfully! Thank you for your input.</div>";
    } else {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4'>❌ Error submitting feedback. Please try again.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Feedback | CCS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-900 via-purple-900 to-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-lg w-full">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">
                <i class="fa-solid fa-comment-dots text-purple-600 mr-2"></i>Submit Feedback
            </h2>
            <button onclick="window.close()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fa-solid fa-times text-xl"></i>
            </button>
        </div>
        
        <?php echo $message; ?>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Feedback Type</label>
                <select name="feedback_type" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-600 focus:outline-none transition">
                    <option value="">Select Type</option>
                    <option value="Complaint">🔴 Complaint</option>
                    <option value="Suggestion">💡 Suggestion</option>
                    <option value="Praise">⭐ Praise</option>
                    <option value="Technical Issue">🔧 Technical Issue</option>
                    <option value="Other">📝 Other</option>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Subject</label>
                <input type="text" name="subject" placeholder="Brief summary of your feedback" required
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-600 focus:outline-none transition">
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Laboratory Room (Optional)</label>
                <select name="lab_room" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-600 focus:outline-none transition">
                    <option value="">Not Applicable</option>
                    <option value="524">Lab 524</option>
                    <option value="526">Lab 526</option>
                    <option value="530">Lab 530</option>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Your Message</label>
                <textarea name="message" placeholder="Please describe your feedback in detail..." required rows="5"
                          class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-600 focus:outline-none transition resize-none"></textarea>
            </div>
            
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="window.close()" 
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white font-semibold py-3 rounded-xl transition shadow-lg">
                    <i class="fa-solid fa-paper-plane mr-2"></i>Submit Feedback
                </button>
            </div>
        </form>
        
        <p class="text-xs text-gray-500 text-center mt-6">
            <i class="fa-solid fa-lock mr-1"></i>Your feedback is confidential and will be reviewed by CCS Admin
        </p>
    </div>
</body>
</html>