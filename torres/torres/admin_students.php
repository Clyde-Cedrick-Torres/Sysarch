<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Handle student deletion
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM users WHERE id_number = '$id'");
    header("Location: admin_students.php");
    exit();
}

$students = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students | CCS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Include same navigation as admin_dashboard.php -->
    <nav class="bg-gradient-to-r from-blue-800 to-purple-800 text-white shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <img src="CCS_LOGO.png" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1">
                <div>
                    <h1 class="font-bold text-lg">CCS Admin Portal</h1>
                    <p class="text-xs text-blue-200">Student Management</p>
                </div>
            </div>
            <div class="flex items-center gap-6">
                <a href="admin_dashboard.php" class="hover:text-yellow-300 transition"><i class="fa-solid fa-gauge mr-1"></i>Dashboard</a>
                <a href="admin_students.php" class="text-yellow-300"><i class="fa-solid fa-users mr-1"></i>Students</a>
                <a href="admin_sit_in.php" class="hover:text-yellow-300 transition"><i class="fa-solid fa-chair mr-1"></i>Sit-in Records</a>
                <a href="admin_reports.php" class="hover:text-yellow-300 transition"><i class="fa-solid fa-chart-line mr-1"></i>Reports</a>
                <a href="admin_logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition">
                    <i class="fa-solid fa-right-from-bracket mr-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-6">
        <div class="bg-white rounded-xl shadow-lg">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-4 rounded-t-xl flex justify-between items-center">
                <h2 class="font-bold text-lg"><i class="fa-solid fa-users mr-2"></i>Registered Students</h2>
                <a href="admin_add_student.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition">
                    <i class="fa-solid fa-user-plus mr-2"></i>Add Student
                </a>
            </div>
            
            <div class="p-6">
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
                            <?php while($student = $students->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium"><?php echo $student['id_number']; ?></td>
                                <td class="px-6 py-4"><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                                <td class="px-6 py-4"><?php echo $student['program']; ?></td>
                                <td class="px-6 py-4"><?php echo $student['year_level']; ?></td>
                                <td class="px-6 py-4"><?php echo $student['email']; ?></td>
                                <td class="px-6 py-4">
                                    <a href="admin_edit_student.php?id=<?php echo $student['id_number']; ?>" 
                                       class="text-blue-600 hover:text-blue-800 mr-3">
                                        <i class="fa-solid fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $student['id_number']; ?>" 
                                       class="text-red-600 hover:text-red-800"
                                       onclick="return confirm('Are you sure you want to delete this student?')">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>