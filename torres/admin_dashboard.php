<?php
session_start();
include 'db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Get statistics
$students_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$sit_in_count = $conn->query("SELECT COUNT(*) as count FROM sit_in_records WHERE status = 'active'")->fetch_assoc()['count'];
$total_sit_in = $conn->query("SELECT COUNT(*) as count FROM sit_in_records")->fetch_assoc()['count'];

// ✅ Get sit-in count per lab room
$lab_524 = $conn->query("SELECT COUNT(*) as count FROM sit_in_records WHERE lab_room = '524' AND status = 'active'")->fetch_assoc()['count'];
$lab_526 = $conn->query("SELECT COUNT(*) as count FROM sit_in_records WHERE lab_room = '526' AND status = 'active'")->fetch_assoc()['count'];
$lab_530 = $conn->query("SELECT COUNT(*) as count FROM sit_in_records WHERE lab_room = '530' AND status = 'active'")->fetch_assoc()['count'];

// Handle announcement submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_announcement'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $content = $conn->real_escape_string($_POST['content']);
    $stmt = $conn->prepare("INSERT INTO announcements (title, content, posted_by, created_at) VALUES (?, ?, 'CCS Admin', NOW())");
    $stmt->bind_param("ss", $title, $content);
    $stmt->execute();
    header("Location: admin_dashboard.php");
    exit();
}

// Handle announcement deletion
if (isset($_GET['delete_announcement'])) {
    $id = intval($_GET['delete_announcement']);
    $conn->query("DELETE FROM announcements WHERE id = $id");
    header("Location: admin_dashboard.php");
    exit();
}

// Handle add student
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
    $id_number = $_POST['id_number'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $middle_name = $_POST['middle_name'];
    $program = $_POST['program'];
    $year_level = $_POST['year_level'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (id_number, first_name, last_name, middle_name, program, year_level, email, address, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssss", $id_number, $first_name, $last_name, $middle_name, $program, $year_level, $email, $address, $password);
    $stmt->execute();
    header("Location: admin_dashboard.php?tab=students");
    exit();
}

// Handle reset all sessions
if (isset($_GET['reset_sessions'])) {
    $conn->query("UPDATE sit_in_records SET status = 'completed', time_out = NOW() WHERE status = 'active'");
    header("Location: admin_dashboard.php");
    exit();
}

// Handle sit-in entry
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_sit_in'])) {
    $student_id = trim($_POST['student_id']);
    $student_name = trim($_POST['student_name']);
    $purpose = $_POST['purpose'];
    $programming_lang = $_POST['programming_lang'];
    $lab_room = $_POST['lab_room'];
    $remaining_session = $_POST['remaining_session'];
    
    // Verify student exists in database
    $check_stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id_number = ?");
    $check_stmt->bind_param("s", $student_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows == 0) {
        header("Location: admin_dashboard.php?tab=sit_in&error=student_not_found");
        exit();
    }
    
    $student_data = $result->fetch_assoc();
    $expected_name = $student_data['first_name'] . ' ' . $student_data['last_name'];
    
    // Verify the name matches
    if (strtolower($student_name) !== strtolower($expected_name)) {
        header("Location: admin_dashboard.php?tab=sit_in&error=name_mismatch&expected=" . urlencode($expected_name));
        exit();
    }
    
    // Check if student already has active sit-in
    $active_check = $conn->prepare("SELECT id FROM sit_in_records WHERE student_id = ? AND status = 'active'");
    $active_check->bind_param("s", $student_id);
    $active_check->execute();
    $active_result = $active_check->get_result();
    
    if ($active_result->num_rows > 0) {
        header("Location: admin_dashboard.php?tab=sit_in&error=duplicate_active");
        exit();
    }
    
    $active_check->close();
    $check_stmt->close();
    
    // Insert new sit-in record
    $stmt = $conn->prepare("INSERT INTO sit_in_records (student_id, student_name, purpose, programming_lang, lab_room, remaining_session, time_in, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'active')");
    $stmt->bind_param("ssssss", $student_id, $student_name, $purpose, $programming_lang, $lab_room, $remaining_session);
    
    if ($stmt->execute()) {
        header("Location: admin_dashboard.php?tab=sit_in&success=1");
    } else {
        header("Location: admin_dashboard.php?tab=sit_in&error=insert_failed");
    }
    exit();
}

// Handle delete student
if (isset($_GET['delete_student'])) {
    $id = $_GET['delete_student'];
    $conn->query("DELETE FROM users WHERE id_number = '$id'");
    header("Location: admin_dashboard.php?tab=students");
    exit();
}

// SEARCH FUNCTIONALITY
$search_results_users = [];
$search_results_sitin = [];
$search_query = "";
$search_performed = false;

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $conn->real_escape_string($_GET['search']);
    $search_performed = true;
    $search_term = "%$search_query%";
    
    // Search in users table
    $search_stmt = $conn->prepare("SELECT * FROM users WHERE id_number LIKE ? OR first_name LIKE ? OR last_name LIKE ?");
    $search_stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $search_stmt->execute();
    $search_results_users = $search_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $search_stmt->close();
    
    // Search in sit_in_records table
    $sitin_stmt = $conn->prepare("SELECT * FROM sit_in_records WHERE student_id LIKE ? OR student_name LIKE ?");
    $sitin_stmt->bind_param("ss", $search_term, $search_term);
    $sitin_stmt->execute();
    $search_results_sitin = $sitin_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $sitin_stmt->close();
}

// Get announcements
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 10");

// Get all students
$students = $conn->query("SELECT * FROM users ORDER BY created_at DESC");

// Get current sit-in records
$current_sit_in = $conn->query("SELECT * FROM sit_in_records WHERE status = 'active' ORDER BY time_in DESC");

// Get current tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CCS Sit-in Monitoring</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Top Navigation -->
    <nav class="bg-gradient-to-r from-blue-800 to-purple-800 text-white shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <img src="CCS_LOGO.png" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1">
                <div>
                    <h1 class="font-bold text-lg">CCS Admin Portal</h1>
                    <p class="text-xs text-blue-200">Welcome, <?php echo $_SESSION['admin_name']; ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="?tab=dashboard" class="hover:text-yellow-300 transition <?php echo $tab == 'dashboard' ? 'text-yellow-300 font-bold' : ''; ?>">
                    <i class="fa-solid fa-gauge mr-1"></i>Dashboard
                </a>
                <a href="?tab=sit_in" class="hover:text-yellow-300 transition <?php echo $tab == 'sit_in' ? 'text-yellow-300 font-bold' : ''; ?>">
                    <i class="fa-solid fa-chair mr-1"></i>Sit-in Form
                </a>
                <a href="?tab=students" class="hover:text-yellow-300 transition <?php echo $tab == 'students' ? 'text-yellow-300 font-bold' : ''; ?>">
                    <i class="fa-solid fa-users mr-1"></i>Students
                </a>
                <a href="?tab=current_sit_in" class="hover:text-yellow-300 transition <?php echo $tab == 'current_sit_in' ? 'text-yellow-300 font-bold' : ''; ?>">
                    <i class="fa-solid fa-clock mr-1"></i>Current Sit-in
                </a>
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
                        <p class="text-gray-500 text-sm">Students Registered</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $students_count; ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fa-solid fa-users text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Currently Sit-in</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $sit_in_count; ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fa-solid fa-chair text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Sit-in Records</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $total_sit_in; ?></p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i class="fa-solid fa-clock-rotate-left text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graph Section -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="font-bold text-lg text-gray-800 mb-4"><i class="fa-solid fa-chart-line mr-2"></i>Sit-in Analytics</h2>
            <canvas id="sitInChart" height="100"></canvas>
        </div>

        <?php if ($tab == 'dashboard'): ?>
        <!-- Dashboard Tab -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Announcement Management -->
            <div class="bg-white rounded-xl shadow-lg">
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-4 rounded-t-xl">
                    <h2 class="font-bold text-lg"><i class="fa-solid fa-bullhorn mr-2"></i>Announcement Management</h2>
                </div>
                <div class="p-6">
                    <form method="POST" class="mb-6 bg-gray-50 p-4 rounded-xl">
                        <h3 class="font-bold text-gray-700 mb-3">Post New Announcement</h3>
                        <div class="space-y-3">
                            <input type="text" name="title" placeholder="Announcement Title" required
                                   class="w-full px-4 py-2 border rounded-lg focus:border-purple-500 focus:outline-none">
                            <textarea name="content" placeholder="Announcement Content" required rows="3"
                                      class="w-full px-4 py-2 border rounded-lg focus:border-purple-500 focus:outline-none"></textarea>
                            <button type="submit" name="submit_announcement" 
                                    class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg transition">
                                <i class="fa-solid fa-paper-plane mr-2"></i>Post Announcement
                            </button>
                        </div>
                    </form>
                    
                    <h3 class="font-bold text-gray-700 mb-3">Posted Announcements</h3>
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <?php while($announcement = $announcements->fetch_assoc()): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="text-sm text-gray-600">
                                        <i class="fa-solid fa-user-shield mr-1"></i>
                                        <?php echo $announcement['posted_by']; ?> | 
                                        <?php echo date('Y-M-d', strtotime($announcement['created_at'])); ?>
                                    </p>
                                    <p class="text-gray-800 mt-2"><?php echo $announcement['content']; ?></p>
                                </div>
                                <a href="?delete_announcement=<?php echo $announcement['id']; ?>" 
                                   class="text-red-500 hover:text-red-700 ml-2"
                                   onclick="return confirm('Delete this announcement?')">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Search Student -->
            <div class="bg-white rounded-xl shadow-lg">
                <div class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 rounded-t-xl">
                    <h2 class="font-bold text-lg"><i class="fa-solid fa-magnifying-glass mr-2"></i>Search Student</h2>
                </div>
                <div class="p-6">
                    <form action="admin_dashboard.php" method="GET" class="mb-6">
                        <input type="hidden" name="tab" value="search">
                        <div class="relative">
                            <input type="text" name="search" placeholder="Search by ID Number or Name..."
                                   class="w-full px-4 py-3 pl-10 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:outline-none">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-4 text-gray-400"></i>
                        </div>
                        <button type="submit" class="w-full mt-3 bg-green-600 hover:bg-green-700 text-white py-2 rounded-xl transition">
                            <i class="fa-solid fa-search mr-2"></i>Search
                        </button>
                    </form>
                    
                    <div class="border-t pt-4">
                        <h3 class="font-bold text-gray-700 mb-3">Quick Actions</h3>
                        <div class="space-y-2">
                            <a href="?tab=students&action=add" class="block bg-blue-50 hover:bg-blue-100 p-3 rounded-lg transition">
                                <i class="fa-solid fa-user-plus text-blue-600 mr-2"></i>Add New Student
                            </a>
                            <a href="?reset_sessions=1" class="block bg-red-50 hover:bg-red-100 p-3 rounded-lg transition" onclick="return confirm('Reset all active sessions?')">
                                <i class="fa-solid fa-rotate-right text-red-600 mr-2"></i>Reset All Sessions
                            </a>
                            <a href="?tab=current_sit_in" class="block bg-green-50 hover:bg-green-100 p-3 rounded-lg transition">
                                <i class="fa-solid fa-clock text-green-600 mr-2"></i>View Current Sit-in
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'search'): ?>
        <!-- Search Results Tab -->
        <div class="bg-white rounded-xl shadow-lg">
            <div class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 rounded-t-xl">
                <h2 class="font-bold text-lg"><i class="fa-solid fa-magnifying-glass mr-2"></i>Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h2>
            </div>
            <div class="p-6">
                <?php 
                $total_results = count($search_results_users) + count($search_results_sitin);
                ?>
                
                <?php if ($total_results > 0): ?>
                
                    <!-- Results from Users Table -->
                    <?php if (count($search_results_users) > 0): ?>
                    <div class="mb-8">
                        <h3 class="font-bold text-lg text-blue-600 mb-3">
                            <i class="fa-solid fa-users mr-2"></i>Registered Students (<?php echo count($search_results_users); ?>)
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Number</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Program</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Year Level</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach($search_results_users as $student): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 font-medium"><?php echo $student['id_number']; ?></td>
                                        <td class="px-6 py-4"><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                                        <td class="px-6 py-4"><?php echo $student['program']; ?></td>
                                        <td class="px-6 py-4"><?php echo $student['year_level']; ?></td>
                                        <td class="px-6 py-4"><?php echo $student['email']; ?></td>
                                        <td class="px-6 py-4">
                                            <a href="?delete_student=<?php echo $student['id_number']; ?>" 
                                               class="text-red-600 hover:text-red-800"
                                               onclick="return confirm('Delete this student?')">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Results from Sit-in Records Table -->
                    <?php if (count($search_results_sitin) > 0): ?>
                    <div class="mb-8">
                        <h3 class="font-bold text-lg text-green-600 mb-3">
                            <i class="fa-solid fa-chair mr-2"></i>Sit-in Records (<?php echo count($search_results_sitin); ?>)
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Number</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Purpose</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Language</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lab Room</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time In</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach($search_results_sitin as $sitin): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 font-medium"><?php echo $sitin['student_id']; ?></td>
                                        <td class="px-6 py-4"><?php echo $sitin['student_name']; ?></td>
                                        <td class="px-6 py-4"><?php echo $sitin['purpose']; ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800"><?php echo $sitin['programming_lang']; ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">Lab <?php echo $sitin['lab_room']; ?></span>
                                        </td>
                                        <td class="px-6 py-4"><?php echo $sitin['time_in']; ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $sitin['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                <?php echo ucfirst($sitin['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <p class="text-gray-600 mt-4">
                        <i class="fa-solid fa-check-circle text-green-500 mr-2"></i>
                        Found <strong><?php echo $total_results; ?></strong> total result(s) 
                        (<?php echo count($search_results_users); ?> registered + <?php echo count($search_results_sitin); ?> sit-in records)
                    </p>
                    
                <?php else: ?>
                <div class="text-center py-12">
                    <i class="fa-solid fa-user-xmark text-gray-300 text-6xl mb-4"></i>
                    <p class="text-gray-500 text-lg">No records found matching "<?php echo htmlspecialchars($search_query); ?>"</p>
                    <p class="text-gray-400 text-sm mt-2">The ID may not be registered or has no sit-in records</p>
                    <a href="?tab=dashboard" class="text-blue-600 hover:text-blue-800 mt-4 inline-block">
                        <i class="fa-solid fa-arrow-left mr-1"></i>Back to Dashboard
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <a href="?tab=dashboard" class="text-green-600 hover:text-green-800">
                        <i class="fa-solid fa-arrow-left mr-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'sit_in'): ?>
        <!-- Sit-in Form Tab -->
        <div class="bg-white rounded-xl shadow-lg">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-4 rounded-t-xl">
                <h2 class="font-bold text-lg"><i class="fa-solid fa-chair mr-2"></i>Sit-in Entry Form</h2>
            </div>
            <div class="p-6">
                <?php if (isset($_GET['error'])): ?>
                <div class="mb-4 p-4 rounded-xl border-l-4 <?php 
                    echo $_GET['error'] == 'duplicate_active' ? 'bg-red-50 border-red-500 text-red-700' : '';
                    echo $_GET['error'] == 'student_not_found' ? 'bg-orange-50 border-orange-500 text-orange-700' : '';
                    echo $_GET['error'] == 'name_mismatch' ? 'bg-yellow-50 border-yellow-500 text-yellow-700' : '';
                ?>">
                    <div class="flex items-start">
                        <i class="fa-solid fa-circle-exclamation mt-1 mr-3"></i>
                        <div>
                            <p class="font-bold">Error</p>
                            <?php if ($_GET['error'] == 'duplicate_active'): ?>
                                <p>This student already has an active sit-in session!</p>
                            <?php elseif ($_GET['error'] == 'student_not_found'): ?>
                                <p>Student ID not found in database. Please register the student first.</p>
                            <?php elseif ($_GET['error'] == 'name_mismatch'): ?>
                                <p>Name doesn't match! Expected: <strong><?php echo htmlspecialchars($_GET['expected'] ?? ''); ?></strong></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
                <div class="mb-4 p-4 rounded-xl border-l-4 bg-green-50 border-green-500 text-green-700">
                    <div class="flex items-start">
                        <i class="fa-solid fa-check-circle mt-1 mr-3"></i>
                        <div>
                            <p class="font-bold">Success!</p>
                            <p>Sit-in record added successfully.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">ID Number</label>
                        <input type="text" name="student_id" required class="w-full px-4 py-3 border rounded-xl focus:border-purple-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Student Name</label>
                        <input type="text" name="student_name" required class="w-full px-4 py-3 border rounded-xl focus:border-purple-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Purpose</label>
                        <input type="text" name="purpose" placeholder="e.g., Laboratory Exercise, Project" required class="w-full px-4 py-3 border rounded-xl focus:border-purple-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Programming Language</label>
                        <select name="programming_lang" required class="w-full px-4 py-3 border rounded-xl focus:border-purple-500 focus:outline-none">
                            <option value="">Select Language</option>
                            <option value="C">C</option>
                            <option value="C#">C#</option>
                            <option value="C++">C++</option>
                            <option value="Java">Java</option>
                            <option value="Python">Python</option>
                            <option value="PHP">PHP</option>
                            <option value="JavaScript">JavaScript</option>
                            <option value="ASP.Net">ASP.Net</option>
                            <option value="HTML/CSS">HTML/CSS</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Laboratory Room</label>
                        <select name="lab_room" required class="w-full px-4 py-3 border rounded-xl focus:border-purple-500 focus:outline-none">
                            <option value="">Select Room</option>
                            <option value="524">Lab 524</option>
                            <option value="526">Lab 526</option>
                            <option value="530">Lab 530</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Remaining Session</label>
                        <input type="number" name="remaining_session" min="0" max="30" value="30" required class="w-full px-4 py-3 border rounded-xl focus:border-purple-500 focus:outline-none">
                    </div>
                    
                    <div class="md:col-span-2">
                        <button type="submit" name="submit_sit_in" class="w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-bold py-3 px-4 rounded-xl transition">
                            <i class="fa-solid fa-check-circle mr-2"></i>Submit Sit-in Entry
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'students'): ?>
        <!-- Students Tab -->
        <div class="bg-white rounded-xl shadow-lg">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-4 rounded-t-xl flex justify-between items-center">
                <h2 class="font-bold text-lg"><i class="fa-solid fa-users mr-2"></i>Student Information</h2>
                <button onclick="document.getElementById('addStudentModal').classList.remove('hidden')" class="bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition">
                    <i class="fa-solid fa-user-plus mr-2"></i>Add Student
                </button>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Program</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Year Level</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while($student = $students->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium"><?php echo $student['id_number']; ?></td>
                            <td class="px-6 py-4"><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                            <td class="px-6 py-4"><?php echo $student['program']; ?></td>
                            <td class="px-6 py-4"><?php echo $student['year_level']; ?></td>
                            <td class="px-6 py-4"><?php echo $student['email']; ?></td>
                            <td class="px-6 py-4">
                                <a href="?delete_student=<?php echo $student['id_number']; ?>" 
                                   class="text-red-600 hover:text-red-800"
                                   onclick="return confirm('Delete this student?')">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Student Modal -->
        <div id="addStudentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-2xl p-8 max-w-2xl w-full mx-4">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-800">Add New Student</h3>
                    <button onclick="document.getElementById('addStudentModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
                <form method="POST" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">ID Number</label>
                        <input type="text" name="id_number" required class="w-full px-4 py-2 border rounded-lg focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                        <input type="password" name="password" required class="w-full px-4 py-2 border rounded-lg focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">First Name</label>
                        <input type="text" name="first_name" required class="w-full px-4 py-2 border rounded-lg focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Last Name</label>
                        <input type="text" name="last_name" required class="w-full px-4 py-2 border rounded-lg focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Middle Name</label>
                        <input type="text" name="middle_name" class="w-full px-4 py-2 border rounded-lg focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                        <input type="email" name="email" required class="w-full px-4 py-2 border rounded-lg focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Program</label>
                        <select name="program" required class="w-full px-4 py-2 border rounded-lg focus:border-purple-500 focus:outline-none">
                            <option value="BS Computer Science">BS Computer Science</option>
                            <option value="BS Information Technology">BS Information Technology</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Year Level</label>
                        <select name="year_level" required class="w-full px-4 py-2 border rounded-lg focus:border-purple-500 focus:outline-none">
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Address</label>
                        <textarea name="address" required class="w-full px-4 py-2 border rounded-lg focus:border-purple-500 focus:outline-none" rows="2"></textarea>
                    </div>
                    <div class="col-span-2 flex gap-4">
                        <button type="button" onclick="document.getElementById('addStudentModal').classList.add('hidden')" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 rounded-xl transition">
                            Cancel
                        </button>
                        <button type="submit" name="add_student" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition">
                            Add Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'current_sit_in'): ?>
        <!-- Current Sit-in Tab -->
        <div class="bg-white rounded-xl shadow-lg">
            <div class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 rounded-t-xl">
                <h2 class="font-bold text-lg"><i class="fa-solid fa-clock mr-2"></i>Current Sit-in Students</h2>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Purpose</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Language</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lab Room</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time In</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Session</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while($sitin = $current_sit_in->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium"><?php echo $sitin['student_id']; ?></td>
                            <td class="px-6 py-4"><?php echo $sitin['student_name']; ?></td>
                            <td class="px-6 py-4"><?php echo $sitin['purpose']; ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800"><?php echo $sitin['programming_lang']; ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">Lab <?php echo $sitin['lab_room']; ?></span>
                            </td>
                            <td class="px-6 py-4"><?php echo $sitin['time_in']; ?></td>
                            <td class="px-6 py-4"><?php echo $sitin['remaining_session']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Chart.js Script -->
    <script>
    const ctx = document.getElementById('sitInChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Lab 524', 'Lab 526', 'Lab 530'],
            datasets: [{
                label: 'Current Sit-in by Lab Room',
                data: [<?php echo $lab_524; ?>, <?php echo $lab_526; ?>, <?php echo $lab_530; ?>],
                backgroundColor: [
                    'rgba(59, 130, 246, 0.7)',
                    'rgba(16, 185, 129, 0.7)',
                    'rgba(139, 92, 246, 0.7)'
                ],
                borderColor: [
                    'rgba(59, 130, 246, 1)',
                    'rgba(16, 185, 129, 1)',
                    'rgba(139, 92, 246, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
</script>
    </script>

    <!-- Auto-populate Student Name Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sitInForm = document.querySelector('form[method="POST"]');
        if (!sitInForm) return;
        
        const idInput = sitInForm.querySelector('input[name="student_id"]');
        const nameInput = sitInForm.querySelector('input[name="student_name"]');
        
        if (idInput && nameInput) {
            idInput.addEventListener('blur', function() {
                const idNumber = this.value.trim();
                if (idNumber.length < 5) {
                    nameInput.value = '';
                    nameInput.readOnly = false;
                    return;
                }
                
                nameInput.value = 'Searching...';
                nameInput.readOnly = true;
                
                fetch('get_student.php?id=' + encodeURIComponent(idNumber))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.found) {
                            nameInput.value = data.full_name;
                            nameInput.classList.add('bg-green-50', 'border-green-500');
                            nameInput.classList.remove('border-gray-300');
                        } else {
                            nameInput.value = '';
                            nameInput.readOnly = false;
                            nameInput.placeholder = 'Student not found - please verify ID';
                            nameInput.classList.add('bg-red-50', 'border-red-500');
                            nameInput.classList.remove('border-gray-300');
                            
                            alert('⚠️ Student ID not found in database!\n\nPlease verify the ID number or register the student first.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        nameInput.value = '';
                        nameInput.readOnly = false;
                        nameInput.placeholder = 'Error fetching student data';
                    });
            });
        }
    });
    </script>
</body>
</html>