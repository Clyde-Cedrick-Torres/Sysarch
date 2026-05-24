<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please login first'); window.close();</script>";
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$selected_lab = isset($_GET['lab']) ? $_GET['lab'] : '524';

// Fetch user info
$stmt = $conn->prepare("SELECT first_name, last_name, program, year_level FROM users WHERE id_number = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle reservation submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lab_room = $_POST['lab_room'];
    $computer_number = $_POST['computer_number'];
    $reservation_date = $_POST['reservation_date'];
    $start_time = $_POST['start_time'];
    $duration = $_POST['duration'];
    $purpose = $_POST['purpose'];
    $notes = $_POST['notes'] ?? '';
    
    // Calculate end time
    $start_datetime = new DateTime($reservation_date . ' ' . $start_time);
    $end_datetime = clone $start_datetime;
    $end_datetime->modify("+{$duration} hours");
    $end_time = $end_datetime->format('H:i:s');
    
    // Check if slot is available
    $check_stmt = $conn->prepare("
        SELECT id FROM reservations 
        WHERE lab_room = ? AND computer_number = ? AND reservation_date = ? 
        AND status != 'cancelled'
        AND NOT (end_time <= ? OR start_time >= ?)
    ");
    $check_stmt->bind_param("sssss", $lab_room, $computer_number, $reservation_date, $start_time, $end_time);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4'>❌ This PC is already reserved for this time slot.</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO reservations (student_id, student_name, program, year_level, lab_room, computer_number, reservation_date, start_time, end_time, duration, purpose, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $student_name = $user['first_name'] . ' ' . $user['last_name'];
        $stmt->bind_param("ssssssssssss", $user_id, $student_name, $user['program'], $user['year_level'], $lab_room, $computer_number, $reservation_date, $start_time, $end_time, $duration, $purpose, $notes);
        
        if ($stmt->execute()) {
            $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4'>✅ Reservation submitted! Waiting for admin approval.</div>";
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4'>❌ Error submitting reservation.</div>";
        }
    }
}

// ✅ FIXED LINE 53 - Use start_time instead of reservation_time
$reservations = $conn->prepare("SELECT * FROM reservations WHERE student_id = ? ORDER BY reservation_date DESC, start_time DESC LIMIT 5");
$reservations->bind_param("s", $user_id);
$reservations->execute();
$res_history = $reservations->get_result();

// Get PC status for selected lab and date
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$current_time = date('H:i:s');

$occupied_pcs = [];
$pending_pcs = [];

$time_check_query = $conn->prepare("
    SELECT computer_number, start_time, end_time, status 
    FROM reservations 
    WHERE lab_room = ? AND reservation_date = ? AND status != 'cancelled'
");
$time_check_query->bind_param("ss", $selected_lab, $selected_date);
$time_check_query->execute();
$time_result = $time_check_query->get_result();

while($row = $time_result->fetch_assoc()) {
    $is_within_time = ($current_time >= $row['start_time'] && $current_time <= $row['end_time']);
    
    if ($row['status'] == 'approved' && $is_within_time) {
        $occupied_pcs[] = $row['computer_number'];
    } elseif ($row['status'] == 'pending') {
        $pending_pcs[] = $row['computer_number'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserve Computer | CCS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        .pc-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 8px; }
        .pc-slot { aspect-ratio: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: 8px; font-size: 12px; font-weight: bold; cursor: pointer; transition: all 0.2s; position: relative; }
        .pc-slot.available { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: 2px solid #059669; }
        .pc-slot.available:hover { transform: scale(1.1); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4); }
        .pc-slot.occupied { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: 2px solid #dc2626; cursor: not-allowed; }
        .pc-slot.occupied::after { content: "✓"; position: absolute; top: -5px; right: -5px; background: #22c55e; color: white; width: 20px; height: 20px; border-radius: 50%; font-size: 10px; display: flex; align-items: center; justify-content: center; border: 2px solid white; }
        .pc-slot.pending { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; border: 2px solid #6d28d9; cursor: not-allowed; }
        .pc-slot.pending::after { content: "⏳"; position: absolute; top: -5px; right: -5px; background: #fbbf24; color: white; width: 20px; height: 20px; border-radius: 50%; font-size: 10px; display: flex; align-items: center; justify-content: center; border: 2px solid white; }
        .pc-slot.selected { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; border: 3px solid #1d4ed8; transform: scale(1.15); }
        @media (max-width: 768px) { .pc-grid { grid-template-columns: repeat(5, 1fr); } }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-900 via-purple-900 to-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800"><i class="fa-solid fa-calendar-check text-green-600 mr-2"></i>Reserve Computer</h2>
            <button onclick="window.close()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        
        <?php echo $message; ?>
        
        <!-- Lab & Date Selection -->
        <div class="mb-6 p-4 bg-gray-50 rounded-xl">
            <form method="GET" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Laboratory Room</label>
                    <select name="lab" onchange="this.form.submit()" class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:outline-none">
                        <option value="524" <?php echo $selected_lab == '524' ? 'selected' : ''; ?>>Lab 524</option>
                        <option value="526" <?php echo $selected_lab == '526' ? 'selected' : ''; ?>>Lab 526</option>
                        <option value="530" <?php echo $selected_lab == '530' ? 'selected' : ''; ?>>Lab 530</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Date</label>
                    <input type="date" name="date" value="<?php echo $selected_date; ?>" min="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
                <div class="md:col-span-2 flex items-end">
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 rounded-xl transition"><i class="fa-solid fa-rotate mr-1"></i> Refresh</button>
                </div>
            </form>
        </div>

        <!-- PC Status Grid -->
        <div class="mb-6">
            <h3 class="font-bold text-gray-800 mb-3"><i class="fa-solid fa-desktop text-blue-600"></i> PC Availability - Lab <?php echo $selected_lab; ?></h3>
            
            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-xl">
                <p class="text-sm text-blue-800"><i class="fa-solid fa-clock mr-2"></i>Current Time: <strong><?php echo date('h:i A'); ?></strong></p>
            </div>
            
            <div class="flex gap-4 mb-4 text-sm">
                <div class="flex items-center gap-2"><div class="w-4 h-4 rounded bg-gradient-to-r from-green-500 to-green-600"></div><span>Available</span></div>
                <div class="flex items-center gap-2"><div class="w-4 h-4 rounded bg-gradient-to-r from-purple-500 to-purple-600"></div><span>Pending</span></div>
                <div class="flex items-center gap-2"><div class="w-4 h-4 rounded bg-gradient-to-r from-red-500 to-red-600"></div><span>Occupied</span></div>
            </div>
            
            <div class="pc-grid mb-4" id="pcGrid">
                <?php for($i = 1; $i <= 40; $i++): 
                    if (in_array($i, $occupied_pcs)) {
                        $pc_class = 'occupied'; $pc_status = 'Occupied'; $is_clickable = false;
                    } elseif (in_array($i, $pending_pcs)) {
                        $pc_class = 'pending'; $pc_status = 'Reserved'; $is_clickable = false;
                    } else {
                        $pc_class = 'available'; $pc_status = 'Free'; $is_clickable = true;
                    }
                ?>
                <div class="pc-slot <?php echo $pc_class; ?>" <?php echo $is_clickable ? 'onclick="selectPC('.$i.')"' : ''; ?>>
                    <i class="fa-solid fa-desktop mb-1"></i>
                    <span><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></span>
                    <span class="text-xs"><?php echo $pc_status; ?></span>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- Reservation Form -->
        <form method="POST" class="space-y-4" id="reservationForm">
            <input type="hidden" name="lab_room" value="<?php echo $selected_lab; ?>">
            <input type="hidden" name="computer_number" id="computer_number" required>
            
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                <p class="text-sm text-gray-600 mb-1">Selected Computer:</p>
                <p class="font-bold text-lg text-blue-800" id="selected_pc_display"><i class="fa-solid fa-circle-question mr-2"></i>No PC selected</p>
            </div>
            
            <input type="hidden" name="reservation_date" value="<?php echo $selected_date; ?>">
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Start Time</label>
                    <select name="start_time" id="start_time" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:outline-none" onchange="updateEndTime()">
                        <option value="">Select</option>
                        <option value="08:00">08:00 AM</option>
                        <option value="09:00">09:00 AM</option>
                        <option value="10:00">10:00 AM</option>
                        <option value="11:00">11:00 AM</option>
                        <option value="13:00">01:00 PM</option>
                        <option value="14:00">02:00 PM</option>
                        <option value="15:00">03:00 PM</option>
                        <option value="16:00">04:00 PM</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Duration</label>
                    <select name="duration" id="duration" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:outline-none" onchange="updateEndTime()">
                        <option value="1">1 Hour</option>
                        <option value="2">2 Hours</option>
                        <option value="3">3 Hours</option>
                    </select>
                </div>
            </div>
            
            <div class="bg-green-50 border-2 border-green-200 rounded-xl p-4">
                <p class="text-sm text-gray-600 mb-1">End Time:</p>
                <p class="font-bold text-lg text-green-800" id="end_time_display"><i class="fa-solid fa-clock mr-2"></i>Auto-calculated</p>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Purpose</label>
                <select name="purpose" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:outline-none">
                    <option value="">Select</option>
                    <option value="Laboratory Exercise">Laboratory Exercise</option>
                    <option value="Project Work">Project Work</option>
                    <option value="Research">Research</option>
                    <option value="Study">Study</option>
                </select>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="window.close()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 rounded-xl">Cancel</button>
                <button type="submit" id="submitBtn" disabled class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-xl disabled:opacity-50">Submit</button>
            </div>
        </form>
        
        <!-- Reservation History -->
        <?php if ($res_history->num_rows > 0): ?>
        <div class="mt-8 pt-6 border-t">
            <h3 class="font-bold text-gray-800 mb-3"><i class="fa-solid fa-clock-rotate-left"></i> My Reservations</h3>
            <div class="space-y-2 max-h-48 overflow-y-auto">
                <?php while($res = $res_history->fetch_assoc()): ?>
                <div class="bg-gray-50 p-3 rounded-lg text-sm">
                    <div class="flex justify-between">
                        <div>
                            <p class="font-semibold">Lab <?php echo $res['lab_room']; ?> • PC-<?php echo str_pad($res['computer_number'], 2, '0', STR_PAD_LEFT); ?></p>
                            <p class="text-gray-600"><?php echo date('M d, Y', strtotime($res['reservation_date'])); ?> • <?php echo date('g:i A', strtotime($res['start_time'])); ?> - <?php echo date('g:i A', strtotime($res['end_time'])); ?></p>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $res['status'] == 'approved' ? 'bg-green-100 text-green-800' : ($res['status'] == 'pending' ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800'); ?>"><?php echo ucfirst($res['status']); ?></span>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function selectPC(pcNumber) {
            document.querySelectorAll('.pc-slot').forEach(slot => slot.classList.remove('selected'));
            event.target.classList.add('selected');
            document.getElementById('computer_number').value = pcNumber;
            document.getElementById('selected_pc_display').innerHTML = '<i class="fa-solid fa-check-circle mr-2 text-green-600"></i>PC-' + String(pcNumber).padStart(2, '0');
            document.getElementById('submitBtn').disabled = false;
        }
        function updateEndTime() {
            const start = document.getElementById('start_time').value;
            const duration = document.getElementById('duration').value;
            if (start && duration) {
                const [hours] = start.split(':').map(Number);
                const endHours = hours + parseInt(duration);
                const period = endHours >= 12 ? 'PM' : 'AM';
                const displayHours = endHours > 12 ? endHours - 12 : endHours;
                document.getElementById('end_time_display').innerHTML = '<i class="fa-solid fa-flag-checkered mr-2"></i>' + displayHours + ':00 ' + period;
            }
        }
    </script>
</body>
</html>