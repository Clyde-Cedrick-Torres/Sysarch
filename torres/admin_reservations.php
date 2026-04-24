<?php
session_start();
include 'db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$message = "";
$message_type = "";

// Handle approve/deny actions
if (isset($_POST['action'])) {
    $reservation_id = intval($_POST['reservation_id']);
    $action = $_POST['action'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    if ($action == 'approve') {
        $status = 'approved';
        
        // Get reservation details
        $res_stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ?");
        $res_stmt->bind_param("i", $reservation_id);
        $res_stmt->execute();
        $reservation = $res_stmt->get_result()->fetch_assoc();
        $res_stmt->close();
        
        // Update reservation status
        $update_stmt = $conn->prepare("UPDATE reservations SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->bind_param("ssi", $status, $admin_notes, $reservation_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // ✅ AUTO-ADD TO SIT-IN RECORDS
        $sitin_stmt = $conn->prepare("INSERT INTO sit_in_records (student_id, student_name, purpose, programming_lang, lab_room, remaining_session, time_in, status) VALUES (?, ?, ?, 'N/A', ?, 30, NOW(), 'active')");
        $purpose = $reservation['purpose'];
        $lab_room = $reservation['lab_room'];
        $sitin_stmt->bind_param("ssss", $reservation['student_id'], $reservation['student_name'], $purpose, $lab_room);
        $sitin_stmt->execute();
        $sitin_stmt->close();
        
        $message = "✅ Reservation approved! Student added to Current Sit-in.";
        $message_type = "success";
        
    } elseif ($action == 'deny') {
        $status = 'cancelled';
        $update_stmt = $conn->prepare("UPDATE reservations SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->bind_param("ssi", $status, $admin_notes, $reservation_id);
        $update_stmt->execute();
        $update_stmt->close();
        $message = "❌ Reservation denied.";
        $message_type = "error";
    }
}

// Get all reservations with student info
$reservations = $conn->query("SELECT r.*, u.email, u.program, u.year_level FROM reservations r 
                              LEFT JOIN users u ON r.student_id = u.id_number 
                              ORDER BY r.created_at DESC");

// Get statistics
$total_reservations = $conn->query("SELECT COUNT(*) as count FROM reservations")->fetch_assoc()['count'];
$pending_reservations = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'pending'")->fetch_assoc()['count'];
$approved_reservations = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'approved'")->fetch_assoc()['count'];
$cancelled_reservations = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'cancelled'")->fetch_assoc()['count'];

// Filter by status
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
if ($filter == 'pending') {
    $reservations = $conn->query("SELECT r.*, u.email, u.program, u.year_level FROM reservations r 
                                  LEFT JOIN users u ON r.student_id = u.id_number 
                                  WHERE r.status = 'pending' ORDER BY r.created_at DESC");
} elseif ($filter == 'approved') {
    $reservations = $conn->query("SELECT r.*, u.email, u.program, u.year_level FROM reservations r 
                                  LEFT JOIN users u ON r.student_id = u.id_number 
                                  WHERE r.status = 'approved' ORDER BY r.created_at DESC");
} elseif ($filter == 'cancelled') {
    $reservations = $conn->query("SELECT r.*, u.email, u.program, u.year_level FROM reservations r 
                                  LEFT JOIN users u ON r.student_id = u.id_number 
                                  WHERE r.status = 'cancelled' ORDER BY r.created_at DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Control | CCS Admin</title>
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
                    <p class="text-xs text-blue-200">Reservation Control Panel</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php" class="hover:text-yellow-300 transition"><i class="fa-solid fa-gauge mr-1"></i>Dashboard</a>
                <a href="admin_dashboard.php?tab=students" class="hover:text-yellow-300 transition"><i class="fa-solid fa-users mr-1"></i>Students</a>
                <a href="admin_dashboard.php?tab=sit_in" class="hover:text-yellow-300 transition"><i class="fa-solid fa-chair mr-1"></i>Sit-in</a>
                <a href="admin_feedback_reports.php" class="hover:text-yellow-300 transition"><i class="fa-solid fa-comments mr-1"></i>Feedback</a>
                <a href="admin_reservations.php" class="text-yellow-300 font-bold"><i class="fa-solid fa-calendar-check mr-1"></i>Reservations</a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition">
                    <i class="fa-solid fa-right-from-bracket mr-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-6">
        <!-- Message Display -->
        <?php if ($message): ?>
        <div class="mb-4 p-4 rounded-xl border-l-4 <?php echo $message_type == 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'; ?>">
            <i class="fa-solid fa-<?php echo $message_type == 'success' ? 'check-circle' : 'circle-exclamation'; ?> mr-2"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Reservations</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $total_reservations; ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fa-solid fa-calendar-check text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Pending Approval</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $pending_reservations; ?></p>
                    </div>
                    <div class="bg-yellow-100 p-3 rounded-full">
                        <i class="fa-solid fa-clock text-yellow-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Approved</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $approved_reservations; ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fa-solid fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Denied/Cancelled</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $cancelled_reservations; ?></p>
                    </div>
                    <div class="bg-red-100 p-3 rounded-full">
                        <i class="fa-solid fa-times-circle text-red-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="bg-white rounded-xl shadow-lg mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <a href="?filter=all" class="py-4 px-6 border-b-2 font-medium text-sm <?php echo $filter == 'all' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                        <i class="fa-solid fa-list mr-2"></i>All Reservations
                    </a>
                    <a href="?filter=pending" class="py-4 px-6 border-b-2 font-medium text-sm <?php echo $filter == 'pending' ? 'border-yellow-500 text-yellow-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                        <i class="fa-solid fa-clock mr-2"></i>Pending
                        <?php if ($pending_reservations > 0): ?>
                        <span class="ml-2 bg-yellow-100 text-yellow-800 py-0.5 px-2 rounded-full text-xs"><?php echo $pending_reservations; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?filter=approved" class="py-4 px-6 border-b-2 font-medium text-sm <?php echo $filter == 'approved' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                        <i class="fa-solid fa-check-circle mr-2"></i>Approved
                    </a>
                    <a href="?filter=cancelled" class="py-4 px-6 border-b-2 font-medium text-sm <?php echo $filter == 'cancelled' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                        <i class="fa-solid fa-times-circle mr-2"></i>Denied
                    </a>
                </nav>
            </div>
        </div>

        <!-- Reservations Table -->
        <div class="bg-white rounded-xl shadow-lg">
            <div class="bg-gradient-to-r from-purple-600 to-blue-600 text-white p-4 rounded-t-xl">
                <h2 class="font-bold text-lg"><i class="fa-solid fa-desktop mr-2"></i>PC Reservation Requests</h2>
            </div>
            <div class="p-6 overflow-x-auto">
                <?php if ($reservations->num_rows > 0): ?>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PC Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Purpose</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while($res = $reservations->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo $res['student_name']; ?></p>
                                    <p class="text-xs text-gray-500"><?php echo $res['student_id']; ?></p>
                                    <p class="text-xs text-gray-500"><?php echo $res['program']; ?> - <?php echo $res['year_level']; ?></p>
                                    <p class="text-xs text-gray-500"><i class="fa-solid fa-envelope mr-1"></i><?php echo $res['email']; ?></p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">
                                    Lab <?php echo $res['lab_room']; ?>
                                </span>
                                <p class="text-sm font-semibold mt-1">PC-<?php echo str_pad($res['computer_number'], 2, '0', STR_PAD_LEFT); ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold"><?php echo date('M d, Y', strtotime($res['reservation_date'])); ?></p>
                                <p class="text-xs text-gray-500"><i class="fa-solid fa-clock mr-1"></i><?php echo date('g:i A', strtotime($res['reservation_time'])); ?></p>
                                <p class="text-xs text-gray-400 mt-1">Requested: <?php echo date('M d, h:i A', strtotime($res['created_at'])); ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-medium"><?php echo $res['duration']; ?> hour(s)</span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-800"><?php echo $res['purpose']; ?></p>
                                <?php if (!empty($res['notes'])): ?>
                                <p class="text-xs text-gray-500 mt-1 italic">"<?php echo substr($res['notes'], 0, 30); ?><?php echo strlen($res['notes']) > 30 ? '...' : ''; ?>"</p>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full font-medium
                                    <?php echo $res['status'] == 'approved' ? 'bg-green-100 text-green-800' : ''; ?>
                                    <?php echo $res['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : ''; ?>
                                    <?php echo $res['status'] == 'cancelled' ? 'bg-red-100 text-red-800' : ''; ?>">
                                    <?php echo ucfirst($res['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($res['status'] == 'pending'): ?>
                                <div class="flex gap-2">
                                    <button onclick="openModal(<?php echo $res['id']; ?>, '<?php echo htmlspecialchars($res['student_name']); ?>', 'approve')" 
                                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded-lg text-sm transition">
                                        <i class="fa-solid fa-check mr-1"></i>Approve
                                    </button>
                                    <button onclick="openModal(<?php echo $res['id']; ?>, '<?php echo htmlspecialchars($res['student_name']); ?>', 'deny')" 
                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-sm transition">
                                        <i class="fa-solid fa-times mr-1"></i>Deny
                                    </button>
                                </div>
                                <?php elseif ($res['status'] == 'approved'): ?>
                                <span class="text-green-600 text-sm"><i class="fa-solid fa-check-circle mr-1"></i>Approved</span>
                                <?php if (!empty($res['admin_notes'])): ?>
                                <p class="text-xs text-gray-500 mt-1"><?php echo substr($res['admin_notes'], 0, 20); ?>...</p>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="text-red-600 text-sm"><i class="fa-solid fa-times-circle mr-1"></i>Denied</span>
                                <?php if (!empty($res['admin_notes'])): ?>
                                <p class="text-xs text-gray-500 mt-1"><?php echo substr($res['admin_notes'], 0, 20); ?>...</p>
                                <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center py-12">
                    <i class="fa-solid fa-calendar-xmark text-gray-300 text-6xl mb-4"></i>
                    <p class="text-gray-500 text-lg">No reservations found.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Approve/Deny Modal -->
    <div id="actionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800" id="modalTitle">
                    <i class="fa-solid fa-circle-question mr-2"></i>Confirm Action
                </h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="reservation_id" id="modal_reservation_id">
                <input type="hidden" name="action" id="modal_action">
                
                <div class="bg-gray-50 p-4 rounded-xl">
                    <p class="text-gray-700">
                        <span id="modal_student_name" class="font-bold"></span>
                    </p>
                    <p class="text-sm text-gray-500 mt-2" id="modal_action_text"></p>
                </div>
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Admin Notes (Optional)</label>
                    <textarea name="admin_notes" placeholder="Add a note for this reservation..." rows="3"
                              class="w-full px-4 py-2 border rounded-lg focus:border-purple-500 focus:outline-none"></textarea>
                </div>
                
                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 rounded-xl transition">
                        Cancel
                    </button>
                    <button type="submit" id="modal_submit_btn" class="flex-1 font-semibold py-3 rounded-xl transition">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id, studentName, action) {
            document.getElementById('modal_reservation_id').value = id;
            document.getElementById('modal_action').value = action;
            document.getElementById('modal_student_name').textContent = studentName;
            
            if (action === 'approve') {
                document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-check-circle text-green-600 mr-2"></i>Approve Reservation';
                document.getElementById('modal_action_text').textContent = 'This will approve the reservation and notify the student.';
                document.getElementById('modal_submit_btn').className = 'flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-xl transition';
                document.getElementById('modal_submit_btn').innerHTML = '<i class="fa-solid fa-check mr-2"></i>Approve';
            } else {
                document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-times-circle text-red-600 mr-2"></i>Deny Reservation';
                document.getElementById('modal_action_text').textContent = 'This will deny the reservation and notify the student.';
                document.getElementById('modal_submit_btn').className = 'flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-3 rounded-xl transition';
                document.getElementById('modal_submit_btn').innerHTML = '<i class="fa-solid fa-times mr-2"></i>Deny';
            }
            
            document.getElementById('actionModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('actionModal').classList.add('hidden');
        }
        
        // Close modal on outside click
        document.getElementById('actionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>