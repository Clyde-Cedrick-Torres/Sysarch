<?php
session_start();
include 'db_connect.php';
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admin_username = trim($_POST['username']);
    $admin_password = trim($_POST['password']);
    
    if (empty($admin_username) || empty($admin_password)) {
        $error = "Credentials must not be empty!";
    } else {
        // Check admins table (NOT users table)
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->bind_param("s", $admin_username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            if (password_verify($admin_password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['name'];
                header("Location: admin_dashboard.php");
                exit();
            } else {
                $error = "Wrong password. Please try again.";
            }
        } else {
            $error = "Admin account not found.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | CCS Sit-in Monitoring</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-900 via-purple-900 to-gray-900 min-h-screen flex flex-col">
    <header class="bg-gray-800/50 backdrop-blur-sm text-white p-4 flex justify-between items-center border-b border-white/10">
        <h1 class="text-xl font-bold"><i class="fa-solid fa-shield-halved mr-2"></i>CCS Admin Portal</h1>
        <nav>
            <a href="index.php" class="px-3 hover:text-purple-400 transition"><i class="fa-solid fa-user mr-1"></i>Student Login</a>
        </nav>
    </header>
    
    <main class="flex-grow flex items-center justify-center p-8">
        <div class="bg-white/95 backdrop-blur-sm p-10 rounded-3xl shadow-2xl max-w-md w-full">
            <div class="text-center mb-8">
                <img src="CCS_LOGO.png" alt="CCS Logo" class="w-32 mx-auto mb-4 drop-shadow-lg">
                <h2 class="text-3xl font-bold text-gray-800">Admin Login</h2>
                <p class="text-gray-600 mt-2">College of Computer Studies</p>
            </div>
            
            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
                <i class="fa-solid fa-circle-exclamation mr-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form action="admin_login.php" method="POST" class="space-y-6">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fa-solid fa-user-shield mr-2"></i>Username
                    </label>
                    <input type="text" name="username" required 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-600 focus:outline-none transition"
                           placeholder="Enter admin username">
                </div>
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fa-solid fa-lock mr-2"></i>Password
                    </label>
                    <input type="password" name="password" required 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-600 focus:outline-none transition"
                           placeholder="Enter admin password">
                </div>
                
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-bold py-3 px-4 rounded-xl transition transform hover:scale-105 shadow-lg">
                    <i class="fa-solid fa-right-to-bracket mr-2"></i>Login as Admin
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-xs text-gray-500">Admin access only • Contact IT for credentials</p>
            </div>
        </div>
    </main>
    
    <footer class="bg-gray-800/50 text-white text-center p-4 border-t border-white/10">
        <p>&copy; 2026 College of Computer Studies - Admin Portal</p>
    </footer>
</body>
</html>