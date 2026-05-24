<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
// Connect to database to get user data
include 'db_connect.php';
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id_number = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// ✅ Fetch Sit-in History for this student
$sit_in_history = $conn->prepare("SELECT * FROM sit_in_records WHERE student_id = ? ORDER BY time_in DESC LIMIT 10");
$sit_in_history->bind_param("s", $user_id);
$sit_in_history->execute();
$history_result = $sit_in_history->get_result();

// ✅ NOTIFICATION SYSTEM - Fetch data from database
// Fetch announcements from database (last 7 days)
$announcements = $conn->query("SELECT * FROM announcements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY created_at DESC LIMIT 10");

// Fetch user's recent reservations
$user_reservations = $conn->prepare("SELECT * FROM reservations WHERE student_id = ? ORDER BY created_at DESC LIMIT 5");
$user_reservations->bind_param("s", $user_id);
$user_reservations->execute();
$reservations_result = $user_reservations->get_result();

// Fetch user's feedback responses
$user_feedback = $conn->prepare("SELECT * FROM feedback WHERE student_id = ? AND admin_response IS NOT NULL ORDER BY updated_at DESC LIMIT 5");
$user_feedback->bind_param("s", $user_id);
$user_feedback->execute();
$feedback_result = $user_feedback->get_result();

// Count unread notifications
$unread_count = 0;

// Count new announcements (last 7 days)
$recent_announcements = $conn->query("SELECT COUNT(*) as count FROM announcements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['count'];
$unread_count += $recent_announcements;

// Count pending reservations
$pending_reservations = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE student_id = ? AND status = 'pending'");
$pending_reservations->bind_param("s", $user_id);
$pending_reservations->execute();
$unread_count += $pending_reservations->get_result()->fetch_assoc()['count'];

// Count approved reservations
$approved_reservations = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE student_id = ? AND status = 'approved'");
$approved_reservations->bind_param("s", $user_id);
$approved_reservations->execute();
$unread_count += $approved_reservations->get_result()->fetch_assoc()['count'];

// Count feedback with responses
$feedback_responses = $conn->prepare("SELECT COUNT(*) as count FROM feedback WHERE student_id = ? AND admin_response IS NOT NULL AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$feedback_responses->bind_param("s", $user_id);
$feedback_responses->execute();
$unread_count += $feedback_responses->get_result()->fetch_assoc()['count'];

// ✅ Fetch TOP 10 Leaderboard
$leaderboard = $conn->query("SELECT id_number, first_name, last_name, program, rewards, total_sessions FROM users WHERE rewards > 0 ORDER BY rewards DESC, total_sessions DESC LIMIT 10");

// ✅ Fetch current user's rank
$rank_query = $conn->query("SELECT id_number, rewards FROM users WHERE rewards > 0 ORDER BY rewards DESC");
$user_rank = 0;
$position = 1;
while($ranked = $rank_query->fetch_assoc()) {
    if ($ranked['id_number'] == $user_id) {
        $user_rank = $position;
        break;
    }
    $position++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | CCS Sit-in</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        /* Modern Leaderboard Styles */
        .leaderboard-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(102, 126, 234, 0.4);
        }
        .leaderboard-item {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .leaderboard-item:hover {
            transform: translateX(5px);
            background: rgba(255,255,255,0.1);
            border-left-color: #fbbf24;
        }
        .leaderboard-item.current-user {
            background: rgba(251, 191, 36, 0.2);
            border-left-color: #fbbf24;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.4); }
            50% { box-shadow: 0 0 0 10px rgba(251, 191, 36, 0); }
        }
        .rank-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        .rank-1 { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
        .rank-2 { background: linear-gradient(135deg, #94a3b8, #64748b); color: white; }
        .rank-3 { background: linear-gradient(135deg, #b45309, #92400e); color: white; }
        .rank-other { background: rgba(255,255,255,0.2); color: white; }
        .trophy-icon {
            font-size: 2rem;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .points-badge {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: bold;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        /* Notification Dropdown Animation */
        .notification-dropdown {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            pointer-events: none;
        }
        .notification-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
            pointer-events: auto;
        }
        .notification-item {
            transition: background-color 0.2s ease;
        }
        .notification-item:hover {
            background-color: #f3f4f6;
        }
        .notification-item.unread {
            background-color: #eff6ff;
            border-left: 3px solid #3b82f6;
        }
        /* Pulse animation for notification bell */
        @keyframes pulse-notification {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .notification-badge {
            animation: pulse-notification 2s infinite;
        }
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.5); }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen font-sans">
    <!-- Top Navbar with Notifications -->
    <nav class="bg-gradient-to-r from-blue-800 to-purple-800 text-white px-4 py-3 shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <h1 class="font-bold text-lg flex items-center gap-2">
                    <i class="fa-solid fa-gauge"></i> Dashboard
                </h1>
            </div>
            
            <div class="flex items-center gap-4">
                <!-- ✅ ACCESSIBLE NOTIFICATION BUTTON -->
                <div class="relative">
                    <button 
                        id="notificationButton" 
                        class="hover:text-yellow-300 transition flex items-center gap-1 relative focus:outline-none focus:ring-2 focus:ring-yellow-400 rounded px-2 py-1"
                        aria-label="Notifications"
                        aria-expanded="false"
                        aria-haspopup="true"
                        onclick="toggleNotifications()"
                    >
                        <i class="fa-solid fa-bell"></i> 
                        <span class="hidden md:inline">Notification</span> 
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                        <?php if ($unread_count > 0): ?>
                        <span class="notification-badge absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
                            <?php echo $unread_count > 9 ? '9+' : $unread_count; ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- ✅ NOTIFICATION DROPDOWN -->
                    <div 
                        id="notificationDropdown" 
                        class="notification-dropdown absolute right-0 mt-2 w-96 bg-white rounded-xl shadow-2xl border border-gray-200 z-50"
                        role="menu"
                        aria-labelledby="notificationButton"
                    >
                        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                            <h3 class="font-bold text-gray-800">
                                <i class="fa-solid fa-bell mr-2 text-blue-600"></i>Notifications
                            </h3>
                            <?php if ($unread_count > 0): ?>
                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full font-bold">
                                <?php echo $unread_count; ?> new
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="max-h-96 overflow-y-auto">
                            <?php 
                            $hasNotifications = false;
                            
                            // Show recent announcements
                            if ($announcements && $announcements->num_rows > 0):
                                $announcements->data_seek(0);
                                while($announcement = $announcements->fetch_assoc()): 
                                    $hasNotifications = true;
                            ?>
                            <div class="notification-item unread p-4 border-b border-gray-100 cursor-pointer" onclick="viewAnnouncement()">
                                <div class="flex items-start gap-3">
                                    <div class="bg-blue-100 p-2 rounded-full">
                                        <i class="fa-solid fa-bullhorn text-blue-600"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-gray-800">New Announcement</p>
                                        <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars(substr($announcement['content'], 0, 80)) . (strlen($announcement['content']) > 80 ? '...' : ''); ?></p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            <i class="fa-solid fa-clock mr-1"></i><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; endif; ?>
                            
                            <!-- Show reservation updates -->
                            <?php 
                            if ($reservations_result && $reservations_result->num_rows > 0):
                                $reservations_result->data_seek(0);
                                while($reservation = $reservations_result->fetch_assoc()): 
                                    $hasNotifications = true;
                                    $statusColor = $reservation['status'] == 'approved' ? 'green' : ($reservation['status'] == 'pending' ? 'yellow' : 'red');
                                    $statusIcon = $reservation['status'] == 'approved' ? 'check-circle' : ($reservation['status'] == 'pending' ? 'clock' : 'times-circle');
                            ?>
                            <div class="notification-item p-4 border-b border-gray-100 cursor-pointer">
                                <div class="flex items-start gap-3">
                                    <div class="bg-<?php echo $statusColor; ?>-100 p-2 rounded-full">
                                        <i class="fa-solid fa-<?php echo $statusIcon; ?> text-<?php echo $statusColor; ?>-600"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-gray-800">
                                            Reservation <?php echo ucfirst($reservation['status']); ?>
                                        </p>
                                        <p class="text-xs text-gray-600 mt-1">
                                            Lab <?php echo $reservation['lab_room']; ?> • PC-<?php echo str_pad($reservation['computer_number'], 2, '0', STR_PAD_LEFT); ?>
                                        </p>
                                        <p class="text-xs text-gray-600">
                                            <?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?> at <?php echo date('g:i A', strtotime($reservation['start_time'])); ?>
                                        </p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            <i class="fa-solid fa-clock mr-1"></i><?php echo date('M d', strtotime($reservation['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; endif; ?>
                            
                            <!-- Show feedback responses -->
                            <?php 
                            if ($feedback_result && $feedback_result->num_rows > 0):
                                $feedback_result->data_seek(0);
                                while($feedback = $feedback_result->fetch_assoc()): 
                                    $hasNotifications = true;
                            ?>
                            <div class="notification-item unread p-4 border-b border-gray-100 cursor-pointer">
                                <div class="flex items-start gap-3">
                                    <div class="bg-purple-100 p-2 rounded-full">
                                        <i class="fa-solid fa-comment-dots text-purple-600"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-gray-800">Feedback Response</p>
                                        <p class="text-xs text-gray-600 mt-1">Admin has responded to your feedback</p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            <i class="fa-solid fa-clock mr-1"></i><?php echo date('M d', strtotime($feedback['updated_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; endif; ?>
                            
                            <?php if (!$hasNotifications): ?>
                            <div class="p-8 text-center text-gray-500">
                                <i class="fa-solid fa-bell-slash text-4xl mb-3 text-gray-300"></i>
                                <p class="text-sm">No notifications yet</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="p-3 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                            <button class="w-full text-center text-sm text-blue-600 hover:text-blue-800 font-semibold" onclick="markAllAsRead()">
                                Mark all as read
                            </button>
                        </div>
                    </div>
                </div>
                
                <a href="#" class="hover:text-yellow-300 transition hidden md:inline">Home</a>
                <a href="edit_profile.php" class="hover:text-yellow-300 transition">Edit Profile</a>
                
                <!-- Reservation Button -->
                <a href="reservation.php" onclick="window.open('reservation.php', 'ReservationWindow', 'width=700,height=650,resizable=yes,scrollbars=yes'); return false;" 
                   class="hover:text-yellow-300 transition flex items-center gap-1 bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded font-bold transition">
                    <i class="fa-solid fa-calendar-check"></i> Reservation
                </a>
                
                <!-- Feedback Button -->
                <a href="feedback.php" onclick="window.open('feedback.php', 'FeedbackWindow', 'width=600,height=700,resizable=yes,scrollbars=yes'); return false;" 
                   class="hover:text-yellow-300 transition flex items-center gap-1 bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded font-bold transition">
                    <i class="fa-solid fa-comment-dots"></i> Feedback
                </a>
                
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded font-bold transition">
                    <i class="fa-solid fa-right-from-bracket mr-1"></i>Log out
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container mx-auto p-4 grid grid-cols-12 gap-6">
        
        <!-- Student Info Card -->
        <div class="col-span-12 lg:col-span-3">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-4 flex items-center gap-2">
                    <i class="fa-solid fa-user"></i> 
                    <span class="font-bold">My Profile</span>
                </div>
                <div class="p-6 text-center">
                    <div class="relative inline-block">
                        <img src="avatar1.png" alt="Avatar" class="w-24 h-24 rounded-full mx-auto border-4 border-blue-200 mb-4 object-cover shadow-lg">
                        <?php if ($user['rewards'] > 0): ?>
                        <span class="absolute -bottom-1 -right-1 bg-yellow-400 text-white text-xs px-2 py-0.5 rounded-full font-bold shadow">
                            ⭐ <?php echo $user['rewards']; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <h3 class="font-bold text-lg text-gray-800"><?php echo $user['first_name'] . " " . $user['last_name']; ?></h3>
                    <p class="text-sm text-gray-500"><?php echo $user['program']; ?></p>
                    
                    <div class="mt-6 space-y-3 text-left text-sm">
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded-lg">
                            <span><i class="fa-solid fa-graduation-cap mr-2 text-blue-600"></i>Year</span>
                            <span class="font-semibold"><?php echo str_replace("th Year", "", $user['year_level']); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded-lg">
                            <span><i class="fa-solid fa-envelope mr-2 text-blue-600"></i>Email</span>
                            <span class="font-semibold text-xs"><?php echo substr($user['email'], 0, 20) . (strlen($user['email']) > 20 ? '...' : ''); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-2 bg-gradient-to-r from-yellow-50 to-orange-50 rounded-lg border border-yellow-200">
                            <span><i class="fa-solid fa-trophy mr-2 text-yellow-600"></i>My Rewards</span>
                            <span class="points-badge"><i class="fa-solid fa-star"></i> <?php echo $user['rewards']; ?> pts</span>
                        </div>
                        <?php if ($user_rank > 0): ?>
                        <div class="flex justify-between items-center p-2 bg-purple-50 rounded-lg">
                            <span><i class="fa-solid fa-ranking-star mr-2 text-purple-600"></i>My Rank</span>
                            <span class="font-bold text-purple-700">#<?php echo $user_rank; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- LEADERBOARD CARD -->
        <div class="col-span-12 lg:col-span-5">
            <div class="leaderboard-card text-white p-6 h-full">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i class="fa-solid fa-trophy trophy-icon"></i>
                        🏆 Leaderboard
                    </h2>
                    <span class="text-sm bg-white/20 px-3 py-1 rounded-full">Top 10</span>
                </div>
                
                <div class="space-y-3 max-h-96 overflow-y-auto pr-2 custom-scrollbar">
                    <?php if ($leaderboard && $leaderboard->num_rows > 0): ?>
                        <?php $rank = 1; while($leader = $leaderboard->fetch_assoc()): ?>
                        <div class="leaderboard-item flex items-center gap-4 p-3 rounded-xl <?php echo $leader['id_number'] == $user_id ? 'current-user' : ''; ?>">
                            <div class="rank-badge rank-<?php echo $rank <= 3 ? $rank : 'other'; ?>">
                                <?php echo $rank <= 3 ? ['🥇','',''][$rank-1] : '#' . $rank; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold truncate"><?php echo $leader['first_name'] . ' ' . $leader['last_name']; ?></p>
                                <p class="text-xs text-white/70 truncate"><?php echo $leader['program']; ?></p>
                            </div>
                            <div class="text-right">
                                <span class="points-badge">
                                    <i class="fa-solid fa-star"></i>
                                    <?php echo $leader['rewards']; ?>
                                </span>
                                <p class="text-xs text-white/60 mt-1"><?php echo $leader['total_sessions']; ?> sessions</p>
                            </div>
                        </div>
                        <?php $rank++; endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-white/80">
                            <i class="fa-solid fa-trophy text-4xl mb-3 opacity-50"></i>
                            <p>No rewards yet. Be the first!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="mt-6 p-4 bg-white/10 rounded-xl">
                    <p class="text-sm font-semibold mb-2">💡 How to Earn Points:</p>
                    <ul class="text-xs text-white/80 space-y-1">
                        <li>✓ Complete a sit-in session (+1 point)</li>
                        <li>✓ Get rewarded by admin (+1 point)</li>
                        <li>✓ Stay consistent to climb the ranks!</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Announcements Card -->
        <div class="col-span-12 lg:col-span-4">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 h-full">
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-4 rounded-t-2xl flex items-center gap-2">
                    <i class="fa-solid fa-bullhorn"></i> 
                    <span class="font-bold">Announcements</span>
                </div>
                <div class="p-4 space-y-4 max-h-80 overflow-y-auto">
                    <?php 
                    if ($announcements && $announcements->num_rows > 0):
                        $announcements->data_seek(0);
                        while($announcement = $announcements->fetch_assoc()): 
                    ?>
                    <div class="border-b border-gray-100 pb-3 last:border-b-0">
                        <p class="text-xs text-gray-500 flex items-center gap-2">
                            <i class="fa-solid fa-user-shield"></i>
                            <?php echo htmlspecialchars($announcement['posted_by']); ?> • 
                            <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                        </p>
                        <?php if (!empty($announcement['title'])): ?>
                        <p class="font-semibold text-gray-800 mt-1"><?php echo htmlspecialchars($announcement['title']); ?></p>
                        <?php endif; ?>
                        <p class="text-sm mt-2 bg-gray-50 p-3 rounded-lg text-gray-700">
                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                        </p>
                    </div>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                    <p class="text-gray-500 text-center py-6 italic">📭 No announcements yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Rules & Sit-in History Row -->
        <div class="col-span-12 lg:col-span-4">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 h-full">
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-4 rounded-t-2xl flex items-center gap-2">
                    <i class="fa-solid fa-book"></i> 
                    <span class="font-bold">Laboratory Rules</span>
                </div>
                <div class="p-4 space-y-2 max-h-80 overflow-y-auto custom-scrollbar">
                    <ol class="list-decimal pl-5 space-y-1.5 text-sm text-gray-700">
                        <li>Maintain silence and proper decorum inside the laboratory.</li>
                        <li>Mobile phones and personal equipment must be switched off.</li>
                        <li>Games are not allowed inside the lab.</li>
                        <li>Internet surfing requires instructor permission.</li>
                        <li>Food and drinks are strictly prohibited.</li>
                        <li>Keep the laboratory clean and dispose waste properly.</li>
                        <li>Report malfunctioning equipment immediately.</li>
                        <li>Always wear your proper ID when entering.</li>
                        <li>Log off before leaving to prevent unauthorized access.</li>
                        <li>Respect equipment; damage due to negligence will be charged.</li>
                    </ol>
                </div>
            </div>
        </div>
        
        <!-- ✅ SIT-IN HISTORY CARD -->
        <div class="col-span-12 lg:col-span-4">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 h-full">
                <div class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 rounded-t-2xl flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left"></i> 
                    <span class="font-bold">Sit-in History</span>
                </div>
                <div class="p-4 space-y-3 max-h-80 overflow-y-auto custom-scrollbar">
                    <?php if ($history_result->num_rows > 0): ?>
                        <?php while($record = $history_result->fetch_assoc()): ?>
                        <div class="border border-gray-200 rounded-lg p-3 hover:shadow-md transition bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-bold <?php echo $record['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700'; ?>">
                                            <?php echo ucfirst($record['status']); ?>
                                        </span>
                                        <span class="text-sm font-semibold text-gray-800">
                                            <i class="fa-solid fa-chair mr-1 text-green-600"></i>
                                            Lab <?php echo $record['lab_room']; ?>
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-600 space-y-1">
                                        <p>
                                            <i class="fa-solid fa-code mr-1 text-blue-600"></i>
                                            <strong>Purpose:</strong> <?php echo htmlspecialchars($record['purpose']); ?>
                                        </p>
                                        <p>
                                            <i class="fa-solid fa-laptop-code mr-1 text-purple-600"></i>
                                            <strong>Language:</strong> <?php echo $record['programming_lang']; ?>
                                        </p>
                                        <p>
                                            <i class="fa-solid fa-calendar mr-1 text-red-600"></i>
                                            <strong>Date:</strong> <?php echo date('M d, Y', strtotime($record['time_in'])); ?>
                                        </p>
                                        <p>
                                            <i class="fa-solid fa-clock mr-1 text-orange-600"></i>
                                            <strong>Time:</strong> <?php echo date('h:i A', strtotime($record['time_in'])); ?>
                                        </p>
                                        <?php if ($record['time_out']): ?>
                                        <p>
                                            <i class="fa-solid fa-clock mr-1 text-gray-600"></i>
                                            <strong>End:</strong> <?php echo date('h:i A', strtotime($record['time_out'])); ?>
                                        </p>
                                        <?php 
                                        $time_in = new DateTime($record['time_in']);
                                        $time_out = new DateTime($record['time_out']);
                                        $diff = $time_in->diff($time_out);
                                        $duration = $diff->h . 'h ' . $diff->i . 'm';
                                        ?>
                                        <p class="pt-1 border-t border-gray-200 mt-1">
                                            <i class="fa-solid fa-hourglass-half mr-1 text-teal-600"></i>
                                            <strong>Duration:</strong> <?php echo $duration; ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fa-solid fa-chair text-gray-300 text-4xl mb-3"></i>
                            <p class="text-gray-500 text-sm">No sit-in history yet.</p>
                            <p class="text-gray-400 text-xs mt-1">Start your first session!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Floating Action Buttons -->
    <div class="fixed bottom-6 right-6 flex flex-col gap-3 z-50">
        <a href="reservation.php" onclick="window.open('reservation.php', 'ReservationWindow', 'width=700,height=650,resizable=yes,scrollbars=yes'); return false;"
           class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white p-4 rounded-full shadow-2xl transition transform hover:scale-110 flex items-center gap-2"
           title="Reserve a PC">
            <i class="fa-solid fa-calendar-check text-xl"></i>
            <span class="hidden md:inline font-semibold">Reserve</span>
        </a>
        
        <a href="feedback.php" onclick="window.open('feedback.php', 'FeedbackWindow', 'width=600,height=700,resizable=yes,scrollbars=yes'); return false;"
           class="bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700 text-white p-4 rounded-full shadow-2xl transition transform hover:scale-110 flex items-center gap-2"
           title="Submit Feedback">
            <i class="fa-solid fa-comment-dots text-xl"></i>
            <span class="hidden md:inline font-semibold">Feedback</span>
        </a>
    </div>

    <!-- ✅ JAVASCRIPT: Notifications Only -->
    <script>
        // ============ NOTIFICATION FUNCTIONS ============
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            const button = document.getElementById('notificationButton');
            
            dropdown.classList.toggle('show');
            
            const isExpanded = dropdown.classList.contains('show');
            button.setAttribute('aria-expanded', isExpanded);
        }
        
        function viewAnnouncement() {
            const announcementsSection = document.querySelector('.col-span-12.lg\\:col-span-4');
            if (announcementsSection) {
                announcementsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                announcementsSection.classList.add('ring-2', 'ring-blue-500');
                setTimeout(() => {
                    announcementsSection.classList.remove('ring-2', 'ring-blue-500');
                }, 2000);
            }
            toggleNotifications();
        }
        
        function markAllAsRead() {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            
            const badge = document.querySelector('.notification-badge');
            if (badge) badge.remove();
            
            toggleNotifications();
            showNotification('All notifications marked as read', 'success');
        }
        
        function showNotification(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transition-all transform ${
                type === 'success' ? 'bg-green-500 text-white' : 'bg-blue-500 text-white'
            }`;
            toast.innerHTML = `<i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'info-circle'} mr-2"></i>${message}`;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationDropdown');
            const button = document.getElementById('notificationButton');
            
            if (dropdown && !dropdown.classList.contains('hidden') && 
                !dropdown.contains(event.target) && !button.contains(event.target)) {
                toggleNotifications();
            }
        });
        
        // Keyboard accessibility
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const dropdown = document.getElementById('notificationDropdown');
                if (dropdown && dropdown.classList.contains('show')) {
                    toggleNotifications();
                }
            }
        });
    </script>
</body>
</html>