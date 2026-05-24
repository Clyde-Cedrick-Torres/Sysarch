<?php
session_start();
include 'db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Handle status update
if (isset($_POST['update_status'])) {
    $feedback_id = $_POST['feedback_id'];
    $status = $_POST['status'];
    $admin_response = $_POST['admin_response'];
    
    $stmt = $conn->prepare("UPDATE feedback SET status = ?, admin_response = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $status, $admin_response, $feedback_id);
    $stmt->execute();
    header("Location: admin_feedback_reports.php?success=1");
    exit();
}

// Get all feedback
$feedback_query = "SELECT f.*, u.program, u.year_level FROM feedback f 
                   LEFT JOIN users u ON f.student_id = u.id_number 
                   ORDER BY f.created_at DESC";
$feedback = $conn->query($feedback_query);

// Get statistics
$total_feedback = $conn->query("SELECT COUNT(*) as count FROM feedback")->fetch_assoc()['count'];
$pending_feedback = $conn->query("SELECT COUNT(*) as count FROM feedback WHERE status = 'pending'")->fetch_assoc()['count'];
$resolved_feedback = $conn->query("SELECT COUNT(*) as count FROM feedback WHERE status = 'resolved'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Reports | CCS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Top Navigation -->
    <nav class="bg-gradient-to-r from-blue-800 to-purple-800 text-white shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <img src="CCS_LOGO.png" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1">
                <div>
                    <h1 class="font-bold text-lg">CCS Admin Portal</h1>
                    <p class="text-xs text-blue-200">Feedback Reports</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php" class="hover:text-yellow-300 transition"><i class="fa-solid fa-gauge mr-1"></i>Dashboard</a>
                <a href="admin_dashboard.php?tab=students" class="hover:text-yellow-300 transition"><i class="fa-solid fa-users mr-1"></i>Students</a>
                <a href="admin_dashboard.php?tab=sit_in" class="hover:text-yellow-300 transition"><i class="fa-solid fa-chair mr-1"></i>Sit-in</a>
                <a href="admin_feedback_reports.php" class="text-yellow-300 font-bold"><i class="fa-solid fa-comments mr-1"></i>Feedback</a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition">
                    <i class="fa-solid fa-right-from-bracket mr-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-6">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Feedback</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $total_feedback; ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fa-solid fa-comments text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Pending Review</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $pending_feedback; ?></p>
                    </div>
                    <div class="bg-yellow-100 p-3 rounded-full">
                        <i class="fa-solid fa-clock text-yellow-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Resolved</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $resolved_feedback; ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fa-solid fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4">
            <i class="fa-solid fa-check-circle mr-2"></i>Feedback status updated successfully!
        </div>
        <?php endif; ?>

        <!-- Feedback Table -->
        <div class="bg-white rounded-xl shadow-lg">
            <div class="bg-gradient-to-r from-purple-600 to-blue-600 text-white p-4 rounded-t-xl">
                <h2 class="font-bold text-lg"><i class="fa-solid fa-inbox mr-2"></i>Student Feedback</h2>
            </div>
            <div class="p-6 overflow-x-auto">
                <?php if ($feedback->num_rows > 0): ?>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lab Room</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while($fb = $feedback->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium"><?php echo $fb['student_id']; ?></td>
                            <td class="px-6 py-4">
                                <p class="font-semibold"><?php echo $fb['student_name']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo $fb['program']; ?> - <?php echo $fb['year_level']; ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php echo $fb['feedback_type'] == 'Complaint' ? 'bg-red-100 text-red-800' : ''; ?>
                                    <?php echo $fb['feedback_type'] == 'Suggestion' ? 'bg-blue-100 text-blue-800' : ''; ?>
                                    <?php echo $fb['feedback_type'] == 'Praise' ? 'bg-yellow-100 text-yellow-800' : ''; ?>
                                    <?php echo $fb['feedback_type'] == 'Technical Issue' ? 'bg-orange-100 text-orange-800' : ''; ?>
                                    <?php echo $fb['feedback_type'] == 'Other' ? 'bg-gray-100 text-gray-800' : ''; ?>">
                                    <?php echo $fb['feedback_type']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4"><?php echo substr($fb['subject'], 0, 30) . (strlen($fb['subject']) > 30 ? '...' : ''); ?></td>
                            <td class="px-6 py-4">
                                <?php if (!empty($fb['lab_room'])): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">Lab <?php echo $fb['lab_room']; ?></span>
                                <?php else: ?>
                                <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm"><?php echo date('M d, Y', strtotime($fb['created_at'])); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full font-medium
                                    <?php echo $fb['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : ''; ?>
                                    <?php echo $fb['status'] == 'reviewed' ? 'bg-blue-100 text-blue-800' : ''; ?>
                                    <?php echo $fb['status'] == 'resolved' ? 'bg-green-100 text-green-800' : ''; ?>">
                                    <?php echo ucfirst($fb['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <button onclick="openModal(<?php echo $fb['id']; ?>, '<?php echo htmlspecialchars($fb['message']); ?>', '<?php echo $fb['status']; ?>', '<?php echo htmlspecialchars($fb['admin_response'] ?? ''); ?>')" 
                                        class="text-blue-600 hover:text-blue-800 mr-2">
                                    <i class="fa-solid fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center py-12">
                    <i class="fa-solid fa-inbox text-gray-300 text-6xl mb-4"></i>
                    <p class="text-gray-500 text-lg">No feedback submitted yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View/Response Modal -->
    <div id="feedbackModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-2xl w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-gray-800">
                    <i class="fa-solid fa-comments text-purple-600 mr-2"></i>Feedback Details
                </h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="feedback_id" id="modal_feedback_id">
                
                <div class="bg-gray-50 p-4 rounded-xl">
                    <h4 class="font-bold text-gray-700 mb-2">Student Message:</h4>
                    <p id="modal_message" class="text-gray-800 whitespace-pre-wrap"></p>
                </div>
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Update Status</label>
                    <select name="status" id="modal_status" class="w-full px-4 py-2 border rounded-lg focus:border-purple-500 focus:outline-none">
                        <option value="pending">Pending</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="resolved">Resolved</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Admin Response (Optional)</label>
                    <textarea name="admin_response" id="modal_response" placeholder="Enter your response to the student..." rows="4"
                              class="w-full px-4 py-2 border rounded-lg focus:border-purple-500 focus:outline-none"></textarea>
                </div>
                
                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 rounded-xl transition">
                        Cancel
                    </button>
                    <button type="submit" name="update_status" class="flex-1 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white font-semibold py-3 rounded-xl transition">
                        <i class="fa-solid fa-paper-plane mr-2"></i>Update & Respond
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id, message, status, response) {
            document.getElementById('modal_feedback_id').value = id;
            document.getElementById('modal_message').textContent = message;
            document.getElementById('modal_status').value = status;
            document.getElementById('modal_response').value = response;
            document.getElementById('feedbackModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('feedbackModal').classList.add('hidden');
        }
        
        // Close modal on outside click
        document.getElementById('feedbackModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>