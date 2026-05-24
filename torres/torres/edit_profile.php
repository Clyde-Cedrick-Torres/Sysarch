<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id_number = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $last_name = $_POST['last_name'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $year_level = $_POST['year_level'];
    $program = $_POST['program'];
    $email = $_POST['email'];
    $address = $_POST['address'];

    $update_stmt = $conn->prepare("UPDATE users SET last_name=?, first_name=?, middle_name=?, year_level=?, program=?, email=?, address=? WHERE id_number=?");
    $update_stmt->bind_param("ssssssss", $last_name, $first_name, $middle_name, $year_level, $program, $email, $address, $user_id);

    if ($update_stmt->execute()) {
        $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded mb-4 text-center'>Profile updated successfully!</div>";
        // Refresh user data
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    } else {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-center'>Error updating profile.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Profile - CCS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<body class="bg-[#0f172a] min-h-screen text-white">

  <!-- Header -->
  <header class="border-b border-white/10 bg-[#0f172a]">
    <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
      <h1 class="text-xl font-bold">College of Computer Studies</h1>
      <nav class="flex gap-6 text-sm">
        <a href="dashboard.php" class="hover:text-purple-400 transition">Dashboard</a>
        <a href="index.php" class="hover:text-purple-400 transition">Home</a>
        <a href="edit_profile.php" class="text-purple-400 font-medium underline">Edit Profile</a>
        <a href="logout.php" class="hover:text-purple-400 transition">Logout</a>
      </nav>
    </div>
  </header>

  <div class="min-h-[calc(100vh-73px)] flex items-center justify-center p-6">
    <div class="max-w-6xl w-full grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

      <!-- Left Side - Logo & Illustration -->
      <div class="flex justify-center lg:justify-end">
        <div class="text-center lg:text-left">
          <img src="CCS_LOGO.png" alt="CCS Logo" class="w-80 mx-auto lg:mx-0 drop-shadow-2xl">
          <p class="mt-6 text-2xl font-bold tracking-wider text-yellow-400">COLLEGE OF COMPUTER STUDIES</p>
          <p class="text-purple-400 text-sm">INCEPTUM • INNOVATIO • MUNERIS</p>
          <div class="mt-8">
            
          </div>
        </div>
      </div>

      <!-- Right Side - Edit Profile Form -->
      <div class="bg-white rounded-3xl shadow-2xl p-8 text-gray-900 max-w-lg">
        <h2 class="text-3xl font-bold text-center mb-6 text-[#0f172a]">Edit Profile</h2>
        
        <?php echo $message; ?>

        <form class="space-y-4" method="POST" action="edit_profile.php">
          <!-- ID Number (Disabled) -->
          <div>
            <label class="block text-sm font-medium mb-1">ID Number</label>
            <input type="text" name="id_number" value="<?php echo $user['id_number']; ?>" disabled class="w-full px-4 py-2 border border-gray-300 rounded-xl bg-gray-100">
          </div>

          <!-- Name Fields -->
          <div class="grid grid-cols-3 gap-2">
            <div>
              <label class="block text-sm font-medium mb-1">Last Name</label>
              <input type="text" name="last_name" value="<?php echo $user['last_name']; ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:border-purple-600 outline-none">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">First Name</label>
              <input type="text" name="first_name" value="<?php echo $user['first_name']; ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:border-purple-600 outline-none">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">M.I.</label>
              <input type="text" name="middle_name" value="<?php echo $user['middle_name']; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:border-purple-600 outline-none">
            </div>
          </div>

          <!-- Year & Program -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Year Level</label>
              <select name="year_level" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:border-purple-600 outline-none">
                <option value="1st Year" <?php echo ($user['year_level'] == "1st Year") ? "selected" : ""; ?>>1st Year</option>
                <option value="2nd Year" <?php echo ($user['year_level'] == "2nd Year") ? "selected" : ""; ?>>2nd Year</option>
                <option value="3rd Year" <?php echo ($user['year_level'] == "3rd Year") ? "selected" : ""; ?>>3rd Year</option>
                <option value="4th Year" <?php echo ($user['year_level'] == "4th Year") ? "selected" : ""; ?>>4th Year</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Program</label>
              <select name="program" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:border-purple-600 outline-none">
                <option value="BS Computer Science" <?php echo ($user['program'] == "BS Computer Science") ? "selected" : ""; ?>>BS Computer Science</option>
                <option value="BS Information Technology" <?php echo ($user['program'] == "BS Information Technology") ? "selected" : ""; ?>>BS Information Technology</option>
              </select>
            </div>
          </div>

          <!-- Email -->
          <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" value="<?php echo $user['email']; ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:border-purple-600 outline-none">
          </div>

          <!-- Address -->
          <div>
            <label class="block text-sm font-medium mb-1">Address</label>
            <textarea name="address" required class="w-full px-4 py-2 border border-gray-300 rounded-xl h-14 outline-none"><?php echo $user['address']; ?></textarea>
          </div>

          <!-- Submit Button -->
          <div class="flex gap-4">
            <a href="dashboard.php" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 rounded-2xl transition text-center">
              Cancel
            </a>
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-2xl transition text-center">
              Update Profile
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>