<?php
session_start();
include 'db_connect.php';
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_num = trim($_POST['id-number']);
    $pass = trim($_POST['password']);
    
    if (empty($id_num) || empty($pass)) {
        $error = "Credentials must not be empty!";
    } else {
        // Check if admin login
        if ($id_num === 'admin' && $pass === 'admin') {
            $_SESSION['admin_id'] = 'admin';
            $_SESSION['admin_name'] = 'CCS Administrator';
            header("Location: admin_dashboard.php");
            exit();
        }
        
        // Student login
        $stmt = $conn->prepare("SELECT id_number, password, first_name FROM users WHERE id_number = ?");
        $stmt->bind_param("s", $id_num);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($pass, $user['password'])) {
                $_SESSION['user_id'] = $user['id_number'];
                $_SESSION['name'] = $user['first_name'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Wrong password. Please try again.";
            }
        } else {
            $error = "ID Number not found.";
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
    <title>College of Computer Studies</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<body class="bg-gray-800 flex flex-col min-h-screen">
    <header class="bg-gray-700 text-white p-4 flex justify-between items-center">
        <h1 class="text-xl">College of Computer Studies Sit-in Monitoring System</h1>
        <nav>
            <a href="index.php" class="px-3 hover:text-purple-400 transition">Home</a>
            <a href="#" class="px-3 hover:text-purple-400 transition">About</a>
            <a href="index.php" class="px-3 text-purple-400 font-bold underline">Login</a>
            <a href="register.php" class="px-3 hover:text-purple-400 transition">Register</a>
        </nav>
    </header>
    
    <main class="flex-grow flex flex-col md:flex-row justify-center items-center p-8 border-t-8 border-b-8 border-gray-700 gap-10">
        <div class="md:w-1/3">
            <img src="CCS_LOGO.png" alt="College Logo" class="mx-auto drop-shadow-2xl" width="100%">
        </div>
        
        <div class="md:w-1/3">
            <div class="bg-white p-8 rounded-2xl shadow-xl">
                <h2 class="text-2xl font-bold mb-6 text-gray-800 text-center">Login</h2>
                
                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-sm text-center">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form action="index.php" method="POST">
                    <div class="mb-4">
                        <label for="id-number" class="block text-gray-700 text-sm font-bold mb-2">ID Number</label>
                        <input type="text" id="id-number" name="id-number" placeholder="Enter ID number" 
                               class="shadow border rounded-xl w-full py-3 px-4 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="mb-6">
                        <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter password" 
                               class="shadow border rounded-xl w-full py-3 px-4 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl w-full transition shadow-lg" type="submit">
                        Login
                    </button>
                    
                    <p class="text-center text-sm text-gray-600 mt-4">
                        Don't have an account? <a href="register.php" class="text-red-500 hover:underline font-bold">Register</a>
                    </p>
                </form>
            </div>
        </div>
    </main>
    
    <footer class="bg-gray-700 text-white text-center p-4 italic">
        &copy; 2026 College of Computer Studies
    </footer>
</body>
</html>