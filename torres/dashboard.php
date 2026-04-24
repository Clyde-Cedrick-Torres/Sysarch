<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';
$user_id = $_SESSION['user_id'];

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id_number = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// ✅ Fetch announcements from database
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 10");

// ✅ Fetch TOP 10 Leaderboard (students with most rewards)
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
    
    /* ✅ FIXED: Added standard background-clip property */
    .trophy-icon {
        font-size: 2rem;
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        background-clip: text;              /* ✅ Standard property */
        -webkit-background-clip: text;      /* ✅ WebKit prefix */
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
    
    /* Custom Scrollbar */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.5); }
</style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen font-sans">
    <!-- Top Navbar -->
    <nav class="bg-gradient-to-r from-blue-800 to-purple-800 text-white px-4 py-3 flex justify-between items-center shadow-lg">
        <h1 class="font-bold text-lg flex items-center gap-2">
            <i class="fa-solid fa-gauge"></i> Dashboard
        </h1>
        <div class="flex items-center gap-4">
            <a href="#" class="hover:text-yellow-300 transition flex items-center gap-1 relative">
                <i class="fa-solid fa-bell"></i> 
                <span class="hidden md:inline">Notification</span>
                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">3</span>
            </a>
            <a href="#" class="hover:text-yellow-300 transition hidden md:inline">Home</a>
            <a href="edit_profile.php" class="hover:text-yellow-300 transition">Edit Profile</a>
            
            <!-- ✅ RESERVATION BUTTON -->
            <a href="reservation.php" onclick="window.open('reservation.php', 'ReservationWindow', 'width=700,height=650,resizable=yes,scrollbars=yes'); return false;" 
               class="hover:text-yellow-300 transition flex items-center gap-1 bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded font-bold transition">
                <i class="fa-solid fa-calendar-check"></i> Reservation
            </a>
            
            <!-- ✅ FEEDBACK BUTTON - NEW! -->
            <a href="feedback.php" onclick="window.open('feedback.php', 'FeedbackWindow', 'width=600,height=700,resizable=yes,scrollbars=yes'); return false;" 
               class="hover:text-yellow-300 transition flex items-center gap-1 bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded font-bold transition">
                <i class="fa-solid fa-comment-dots"></i> Feedback
            </a>
            
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded font-bold transition">
                <i class="fa-solid fa-right-from-bracket mr-1"></i>Log out
            </a>
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
        
        <!-- ✅ LEADERBOARD CARD - Modern Design -->
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
                    <?php if ($leaderboard->num_rows > 0): ?>
                        <?php $rank = 1; while($leader = $leaderboard->fetch_assoc()): ?>
                        <div class="leaderboard-item flex items-center gap-4 p-3 rounded-xl <?php echo $leader['id_number'] == $user_id ? 'current-user' : ''; ?>">
                            <!-- Rank Badge -->
                            <div class="rank-badge rank-<?php echo $rank <= 3 ? $rank : 'other'; ?>">
                                <?php echo $rank <= 3 ? ['🥇','🥈','🥉'][$rank-1] : '#' . $rank; ?>
                            </div>
                            
                            <!-- Avatar & Name -->
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold truncate"><?php echo $leader['first_name'] . ' ' . $leader['last_name']; ?></p>
                                <p class="text-xs text-white/70 truncate"><?php echo $leader['program']; ?></p>
                            </div>
                            
                            <!-- Points -->
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
                
                <!-- How to Earn Points -->
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
                    <?php if ($announcements->num_rows > 0): ?>
                        <?php while($announcement = $announcements->fetch_assoc()): ?>
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
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-6 italic">📭 No announcements yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Rules & Regulations Card -->
        <div class="col-span-12">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-4 rounded-t-2xl flex items-center gap-2">
                    <i class="fa-solid fa-book"></i> 
                    <span class="font-bold">Laboratory Rules & Regulations</span>
                </div>
                <div class="p-6 max-h-60 overflow-y-auto pr-2 text-sm text-gray-700">
                    <ol class="list-decimal pl-5 space-y-2">
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
    </div>
    
    <!-- ✅ FLOATING ACTION BUTTONS - Reservation + Feedback -->
    <div class="fixed bottom-6 right-6 flex flex-col gap-3 z-50">
        <!-- Reservation Button -->
        <a href="reservation.php" onclick="window.open('reservation.php', 'ReservationWindow', 'width=700,height=650,resizable=yes,scrollbars=yes'); return false;"
           class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white p-4 rounded-full shadow-2xl transition transform hover:scale-110 flex items-center gap-2"
           title="Reserve a PC">
            <i class="fa-solid fa-calendar-check text-xl"></i>
            <span class="hidden md:inline font-semibold">Reserve</span>
        </a>
        
        <!-- ✅ FEEDBACK BUTTON - NEW! -->
        <a href="feedback.php" onclick="window.open('feedback.php', 'FeedbackWindow', 'width=600,height=700,resizable=yes,scrollbars=yes'); return false;"
           class="bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700 text-white p-4 rounded-full shadow-2xl transition transform hover:scale-110 flex items-center gap-2"
           title="Submit Feedback">
            <i class="fa-solid fa-comment-dots text-xl"></i>
            <span class="hidden md:inline font-semibold">Feedback</span>
        </a>
    </div>
    
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.5); }
    </style>
</body>
</html>