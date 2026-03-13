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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | CCS Sit-in</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Top Navbar -->
    <nav class="bg-blue-800 text-white px-4 py-2 flex justify-between items-center">
        <h1 class="font-bold text-lg">Dashboard</h1>
        <div class="flex items-center gap-4">
            <a href="#" class="hover:text-yellow-300 transition flex items-center gap-1">
                <i class="fa-solid fa-bell"></i> Notification <i class="fa-solid fa-chevron-down text-xs"></i>
            </a>
            <a href="#" class="hover:text-yellow-300 transition">Home</a>
            <a href="edit_profile.php" class="hover:text-yellow-300 transition">Edit Profile</a>
            <a href="#" class="hover:text-yellow-300 transition">History</a>
            <a href="#" class="hover:text-yellow-300 transition">Reservation</a>
            <a href="logout.php" class="bg-yellow-400 text-black px-3 py-1 rounded font-bold hover:bg-yellow-500 transition">
                Log out
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mx-auto p-4 grid grid-cols-12 gap-4">
        <!-- Student Info Card -->
        <div class="col-span-3 bg-white rounded-lg shadow-md border border-gray-200">
            <div class="bg-blue-800 text-white p-2 font-bold rounded-t-lg">Student Information</div>
            <div class="p-4 text-center">
                <img src="avatar1.png" alt="Avatar" class="w-32 h-32 rounded-full mx-auto border-2 border-blue-800 mb-4">
                <div class="text-left space-y-2 mt-4">
                    <p><i class="fa-solid fa-user mr-2 text-blue-800"></i> <strong>Name:</strong> <?php echo $user['first_name'] . " " . $user['middle_name'] . " " . $user['last_name']; ?></p>
                    <p><i class="fa-solid fa-graduation-cap mr-2 text-blue-800"></i> <strong>Course:</strong> <?php echo $user['program']; ?></p>
                    <p><i class="fa-solid fa-calendar-days mr-2 text-blue-800"></i> <strong>Year:</strong> <?php echo str_replace("th Year", "", $user['year_level']); ?></p>
                    <p><i class="fa-solid fa-envelope mr-2 text-blue-800"></i> <strong>Email:</strong> <?php echo $user['email']; ?></p>
                    <p><i class="fa-solid fa-location-dot mr-2 text-blue-800"></i> <strong>Address:</strong> <?php echo $user['address']; ?></p>
                    <p><i class="fa-solid fa-clock mr-2 text-blue-800"></i> <strong>Session:</strong> 30</p>
                </div>
            </div>
        </div>

        <!-- Announcements Card -->
        <div class="col-span-4 bg-white rounded-lg shadow-md border border-gray-200">
            <div class="bg-blue-800 text-white p-2 font-bold rounded-t-lg flex items-center gap-2">
                <i class="fa-solid fa-bullhorn"></i> Announcement
            </div>
            <div class="p-4 space-y-4">
                <div class="border-b border-gray-200 pb-3">
                    <p class="text-sm text-gray-600">CCS Admin | 2026-Feb-11</p>
                    <div class="h-10 bg-gray-100 rounded mt-2"></div>
                </div>
                <div>
                    <p class="text-sm text-gray-600">CCS Admin | 2024-May-08</p>
                    <p class="text-sm mt-2 bg-gray-100 p-3 rounded">
                        Important Announcement We are excited to announce the launch of our new website! 🎉 Explore our latest products and services now!
                    </p>
                </div>
            </div>
        </div>

        <!-- Rules & Regulations Card -->
        <div class="col-span-5 bg-white rounded-lg shadow-md border border-gray-200">
            <div class="bg-blue-800 text-white p-2 font-bold rounded-t-lg">Rules and Regulation</div>
            <div class="p-4 max-h-[400px] overflow-y-auto pr-2">
                <h3 class="text-center font-bold text-lg mb-2">University of Cebu</h3>
                <h4 class="text-center font-bold mb-4">COLLEGE OF INFORMATION & COMPUTER STUDIES</h4>
                
                <h5 class="font-bold mb-2">LABORATORY RULES AND REGULATIONS</h5>
                <p class="text-sm mb-3">To avoid embarrassment and maintain camaraderie with your friends and superiors at our laboratories, please observe the following:</p>
                
                <ol class="list-decimal pl-5 space-y-3 text-sm">
                    <li>Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal pieces of equipment must be switched off.</li>
                    <li>Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.</li>
                    <li>Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing of software are strictly prohibited.</li>
                    <li>Food and drinks are not allowed inside the laboratory at all times.</li>
                    <li>Always keep the laboratory clean. Dispose of waste materials properly in the designated trash bins.</li>
                    <li>Report any malfunctioning equipment to the laboratory instructor immediately. Do not attempt to repair any equipment without proper authorization.</li>
                    <li>All students must wear their proper ID when entering the laboratory.</li>
                    <li>Always log off from your account before leaving the laboratory to prevent unauthorized access.</li>
                    <li>Respect the laboratory equipment and other property. Any damage caused by negligence will be subject to disciplinary action.</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Logout Handler -->
    <?php if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: index.php");
        exit();
    } ?>
</body>
</html>