
<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "torres_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

<?php
session_start(); // FIRST LINE
include 'db_connect.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_num = $_POST['id_number'];
    $lname = $_POST['last_name'];
    $fname = $_POST['first_name'];
    $mname = $_POST['middle_name'];
    $year = $_POST['year_level'];
    $program = $_POST['program'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    if (empty($id_num) || empty($pass)) {
        $message = "<p class='text-red-500 font-bold'>Please fill in all required fields.</p>";
    } elseif ($pass !== $confirm_pass) {
        $message = "<p class='text-red-500 font-bold'>Passwords do not match!</p>";
    } else {
        $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (id_number, last_name, first_name, middle_name, year_level, program, email, address, password) 
                VALUES ('$id_num', '$lname', '$fname', '$mname', '$year', '$program', '$email', '$address', '$hashed_pass')";

        if ($conn->query($sql) === TRUE) {
            $message = "<p class='text-green-500 font-bold text-center'>Registration Successful! <br><a href='index.php' class='underline'>Login Now</a></p>";
        } else {
            $message = "<p class='text-red-500 font-bold text-center'>Error: ID number or Email already taken.</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registration - CCS</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0f172a] min-h-screen text-white">

  <header class="border-b border-white/10 bg-[#0f172a]">
    <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
      <h1 class="text-xl font-bold">College of Computer Studies</h1>
      <nav class="flex gap-6 text-sm">
        <a href="index.php" class="hover:text-purple-400 transition">Home</a>
        <a href="index.php" class="hover:text-purple-400 transition">Login</a>
        <a href="register.php" class="text-purple-400 font-medium underline">Register</a>
      </nav>
    </div>
  </header>

  <div class="min-h-[calc(100vh-73px)] flex items-center justify-center p-6">
    <div class="max-w-6xl w-full grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

      <div class="flex justify-center lg:justify-end">
        <div class="text-center lg:text-left">
          <img src="CCS_LOGO.png" alt="CCS Logo" class="w-80 mx-auto lg:mx-0 drop-shadow-2xl">
          <p class="mt-6 text-2xl font-bold tracking-wider text-yellow-400">COLLEGE OF COMPUTER STUDIES</p>
          <p class="text-purple-400 text-sm">INCEPTUM • INNOVATIO • MUNERIS</p>
        </div>
      </div>

      <div class="bg-white rounded-3xl shadow-2xl p-8 text-gray-900 max-w-lg">
        <h2 class="text-3xl font-bold text-center mb-6 text-[#0f172a]">Create Account</h2>
        
        <div class="mb-4 text-center text-sm"><?php echo $message; ?></div>

        <form class="space-y-4" method="POST" action="register.php">
          <div>
            <label class="block text-sm font-medium mb-1">ID Number</label>
            <input type="text" name="id_number" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:border-purple-600 outline-none">
          </div>

          <div class="grid grid-cols-3 gap-2">
            <div class="col-span-1">
              <label class="block text-sm font-medium mb-1">Last Name</label>
              <input type="text" name="last_name" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:border-purple-600 outline-none">
            </div>
            <div class="col-span-1">
              <label class="block text-sm font-medium mb-1">First Name</label>
              <input type="text" name="first_name" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:border-purple-600 outline-none">
            </div>
            <div class="col-span-1">
              <label class="block text-sm font-medium mb-1">M.I.</label>
              <input type="text" name="middle_name" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:border-purple-600 outline-none">
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Year Level</label>
              <select name="year_level" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:border-purple-600 outline-none">
                <option>1st Year</option><option>2nd Year</option><option>3rd Year</option><option>4th Year</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Program</label>
              <select name="program" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:border-purple-600 outline-none">
                <option>BS Computer Science</option><option>BS Information Technology</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:border-purple-600 outline-none">
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Address</label>
            <textarea name="address" required class="w-full px-4 py-2 border border-gray-300 rounded-xl h-14 outline-none"></textarea>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Password</label>
              <input type="password" name="password" required class="w-full px-4 py-2 border border-gray-300 rounded-xl outline-none">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Confirm</label>
              <input type="password" name="confirm_password" required class="w-full px-4 py-2 border border-gray-300 rounded-xl outline-none">
            </div>
          </div>

          <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-2xl transition text-lg shadow-lg">
            Register
          </button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>