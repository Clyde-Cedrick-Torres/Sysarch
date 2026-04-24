<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
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
    $reservation_time = $_POST['reservation_time'];
    $duration = $_POST['duration'];
    $purpose = $_POST['purpose'];
    $notes = $_POST['notes'] ?? '';
    
    // Check if slot is available
    $check_stmt = $conn->prepare("SELECT id FROM reservations WHERE lab_room = ? AND computer_number = ? AND reservation_date = ? AND reservation_time = ? AND status != 'cancelled'");
    $check_stmt->bind_param("ssss", $lab_room, $computer_number, $reservation_date, $reservation_time);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4'>❌ This PC is already reserved for this time slot. Please choose another.</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO reservations (student_id, student_name, program, year_level, lab_room, computer_number, reservation_date, reservation_time, duration, purpose, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $student_name = $user['first_name'] . ' ' . $user['last_name'];
        $stmt->bind_param("sssssssssss", $user_id, $student_name, $user['program'], $user['year_level'], $lab_room, $computer_number, $reservation_date, $reservation_time, $duration, $purpose, $notes);
        
        if ($stmt->execute()) {
            $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4'>✅ Reservation submitted! Waiting for admin approval.</div>";
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4'>❌ Error submitting reservation. Please try again.</div>";
        }
    }
}

// Fetch user's reservation history
$reservations = $conn->prepare("SELECT * FROM reservations WHERE student_id = ? ORDER BY reservation_date DESC, reservation_time DESC LIMIT 5");
$reservations->bind_param("s", $user_id);
$reservations->execute();
$res_history = $reservations->get_result();

// Get PC occupancy status for selected lab and date
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_time = isset($_GET['time']) ? $_GET['time'] : '08:00';

$occupied_pcs = [];      // Admin approved (RED)
$pending_pcs = [];       // Pending approval (PURPLE)
$my_reserved_pcs = [];   // My pending reservations (PURPLE)

// Get PCs that are OCCUPIED (admin approved)
$occupied_query = $conn->prepare("SELECT computer_number FROM reservations WHERE lab_room = ? AND reservation_date = ? AND reservation_time = ? AND status = 'approved'");
$occupied_query->bind_param("sss", $selected_lab, $selected_date, $selected_time);
$occupied_query->execute();
$occupied_result = $occupied_query->get_result();
while($row = $occupied_result->fetch_assoc()) {
    $occupied_pcs[] = $row['computer_number'];
}

// Get PCs that are PENDING (waiting admin approval)
$pending_query = $conn->prepare("SELECT computer_number, student_id FROM reservations WHERE lab_room = ? AND reservation_date = ? AND reservation_time = ? AND status = 'pending'");
$pending_query->bind_param("sss", $selected_lab, $selected_date, $selected_time);
$pending_query->execute();
$pending_result = $pending_query->get_result();
while($row = $pending_result->fetch_assoc()) {
    $pending_pcs[] = $row['computer_number'];
    if ($row['student_id'] == $user_id) {
        $my_reserved_pcs[] = $row['computer_number'];
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
        .pc-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 8px;
        }
        .pc-slot {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .pc-slot.available {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: 2px solid #059669;
        }
        .pc-slot.available:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        .pc-slot.occupied {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: 2px solid #dc2626;
            cursor: not-allowed;
            opacity: 0.9;
        }
        .pc-slot.pending {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border: 2px solid #6d28d9;
            cursor: not-allowed;
            opacity: 0.9;
        }
        .pc-slot.pending::after {
            content: "⏳";
            position: absolute;
            top: -5px;
            right: -5px;
            background: #fbbf24;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }
        .pc-slot.occupied::after {
            content: "✓";
            position: absolute;
            top: -5px;
            right: -5px;
            background: #22c55e;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }
        .pc-slot.selected {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: 3px solid #1d4ed8;
            transform: scale(1.15);
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.5);
        }
        @media (max-width: 768px) {
            .pc-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-900 via-purple-900 to-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">
                <i class="fa-solid fa-calendar-check text-green-600 mr-2"></i>Reserve Computer
            </h2>
            <button onclick="window.close()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fa-solid fa-times text-xl"></i>
            </button>
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
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Time Slot</label>
                    <select name="time" onchange="this.form.submit()" class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:outline-none">
                        <option value="08:00" <?php echo $selected_time == '08:00' ? 'selected' : ''; ?>>08:00 AM</option>
                        <option value="09:00" <?php echo $selected_time == '09:00' ? 'selected' : ''; ?>>09:00 AM</option>
                        <option value="10:00" <?php echo $selected_time == '10:00' ? 'selected' : ''; ?>>10:00 AM</option>
                        <option value="11:00" <?php echo $selected_time == '11:00' ? 'selected' : ''; ?>>11:00 AM</option>
                        <option value="13:00" <?php echo $selected_time == '13:00' ? 'selected' : ''; ?>>01:00 PM</option>
                        <option value="14:00" <?php echo $selected_time == '14:00' ? 'selected' : ''; ?>>02:00 PM</option>
                        <option value="15:00" <?php echo $selected_time == '15:00' ? 'selected' : ''; ?>>03:00 PM</option>
                        <option value="16:00" <?php echo $selected_time == '16:00' ? 'selected' : ''; ?>>04:00 PM</option>
                        <option value="17:00" <?php echo $selected_time == '17:00' ? 'selected' : ''; ?>>05:00 PM</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 rounded-xl transition">
                        <i class="fa-solid fa-rotate mr-1"></i> Refresh
                    </button>
                </div>
            </form>
        </div>

        <!-- PC Status Grid -->
        <div class="mb-6">
            <h3 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-desktop text-blue-600"></i>
                PC Availability - Lab <?php echo $selected_lab; ?> 
                <span class="text-sm font-normal text-gray-500">(<?php echo date('M d, Y', strtotime($selected_date)); ?> at <?php echo date('g:i A', strtotime($selected_time)); ?>)</span>
            </h3>
            
            <!-- Legend -->
            <div class="flex gap-4 mb-4 text-sm flex-wrap">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-gradient-to-r from-green-500 to-green-600"></div>
                    <span>Available</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-gradient-to-r from-purple-500 to-purple-600"></div>
                    <span>Reserved (Pending)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-gradient-to-r from-red-500 to-red-600"></div>
                    <span>Occupied (Approved)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-gradient-to-r from-blue-500 to-blue-600"></div>
                    <span>Selected</span>
                </div>
            </div>
            
            <!-- PC Grid -->
            <div class="pc-grid mb-4" id="pcGrid">
                <?php 
                $total_pcs = 40;
                
                for($i = 1; $i <= $total_pcs; $i++): 
                    // Determine PC status
                    if (in_array($i, $occupied_pcs)) {
                        $pc_class = 'occupied';
                        $pc_status = 'Occupied';
                        $is_clickable = false;
                    } elseif (in_array($i, $pending_pcs)) {
                        $pc_class = 'pending';
                        $pc_status = 'Reserved';
                        $is_clickable = false;
                    } else {
                        $pc_class = 'available';
                        $pc_status = 'Free';
                        $is_clickable = true;
                    }
                ?>
                <div class="pc-slot <?php echo $pc_class; ?>" 
                     <?php echo $is_clickable ? 'onclick="selectPC('.$i.')"' : ''; ?>
                     id="pc-<?php echo $i; ?>"
                     data-pc="<?php echo $i; ?>">
                    <i class="fa-solid fa-desktop mb-1"></i>
                    <span><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></span>
                    <span class="text-xs"><?php echo $pc_status; ?></span>
                </div>
                <?php endfor; ?>
            </div>
            
            <p class="text-xs text-gray-500 text-center">
                <i class="fa-solid fa-info-circle mr-1"></i>Click on a green PC to reserve it
            </p>
        </div>
        
        <!-- Reservation Form -->
        <form method="POST" class="space-y-4" id="reservationForm">
            <input type="hidden" name="lab_room" value="<?php echo $selected_lab; ?>" id="lab_room">
            <input type="hidden" name="computer_number" id="computer_number" required>
            
            <!-- Selected PC Display -->
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                <p class="text-sm text-gray-600 mb-1">Selected Computer:</p>
                <p class="font-bold text-lg text-blue-800" id="selected_pc_display">
                    <i class="fa-solid fa-circle-question mr-2"></i>No PC selected
                </p>
            </div>
            
            <!-- Date & Time (Hidden - from selection above) -->
            <input type="hidden" name="reservation_date" value="<?php echo $selected_date; ?>">
            <input type="hidden" name="reservation_time" value="<?php echo $selected_time; ?>">
            
            <!-- Duration & Purpose -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Duration (Hours)</label>
                    <select name="duration" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:outline-none transition">
                        <option value="1">1 Hour</option>
                        <option value="2">2 Hours</option>
                        <option value="3">3 Hours</option>
                        <option value="4">4 Hours</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Purpose</label>
                    <select name="purpose" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:outline-none transition">
                        <option value="">Select Purpose</option>
                        <option value="Laboratory Exercise">Laboratory Exercise</option>
                        <option value="Project Work">Project Work</option>
                        <option value="Research">Research</option>
                        <option value="Study">Study</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            
            <!-- Notes -->
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Additional Notes (Optional)</label>
                <textarea name="notes" placeholder="Any special requirements..." rows="3"
                          class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:outline-none transition resize-none"></textarea>
            </div>
            
            <!-- Submit Buttons -->
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="window.close()" 
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" id="submitBtn" disabled
                        class="flex-1 bg-gradient-to-r from-green-600 to-blue-600 hover:from-green-700 hover:to-blue-700 text-white font-semibold py-3 rounded-xl transition shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-paper-plane mr-2"></i>Submit Reservation
                </button>
            </div>
        </form>
        
        <!-- Reservation History -->
        <?php if ($res_history->num_rows > 0): ?>
        <div class="mt-8 pt-6 border-t">
            <h3 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-blue-600"></i>My Recent Reservations
            </h3>
            <div class="space-y-2 max-h-48 overflow-y-auto">
                <?php while($res = $res_history->fetch_assoc()): ?>
                <div class="bg-gray-50 p-3 rounded-lg text-sm">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold text-gray-800">
                                Lab <?php echo $res['lab_room']; ?> • PC-<?php echo str_pad($res['computer_number'], 2, '0', STR_PAD_LEFT); ?>
                            </p>
                            <p class="text-gray-600">
                                <?php echo date('M d, Y', strtotime($res['reservation_date'])); ?> at <?php echo date('g:i A', strtotime($res['reservation_time'])); ?>
                            </p>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full font-medium
                            <?php echo $res['status'] == 'approved' ? 'bg-green-100 text-green-800' : ''; ?>
                            <?php echo $res['status'] == 'pending' ? 'bg-purple-100 text-purple-800' : ''; ?>
                            <?php echo $res['status'] == 'cancelled' ? 'bg-red-100 text-red-800' : ''; ?>">
                            <?php echo ucfirst($res['status']); ?>
                        </span>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <p class="text-xs text-gray-500 text-center mt-6">
            <i class="fa-solid fa-info-circle mr-1"></i>Reservations require admin approval before becoming occupied.
        </p>
    </div>

    <script>
        function selectPC(pcNumber) {
            document.querySelectorAll('.pc-slot').forEach(slot => {
                slot.classList.remove('selected');
            });
            
            const selectedPC = document.getElementById('pc-' + pcNumber);
            selectedPC.classList.add('selected');
            
            document.getElementById('computer_number').value = pcNumber;
            document.getElementById('selected_pc_display').innerHTML = 
                '<i class="fa-solid fa-check-circle mr-2 text-green-600"></i>Lab <?php echo $selected_lab; ?> - PC-' + String(pcNumber).padStart(2, '0');
            
            document.getElementById('submitBtn').disabled = false;
            document.getElementById('reservationForm').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>