<?php
session_start();
require_once __DIR__ . '/config/db.php';

// --- AJAX Handler for Dynamic Form ---
if (isset($_GET['action'])) {
    // Security check: only admin should be able to fetch this data
    if (!isset($_SESSION['user_id']) || (strpos(strtolower($_SESSION['role'] ?? ''), 'admin') === false)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Access Denied']);
        exit;
    }

    $action = $_GET['action'];
    
    // Action to get colleges that do NOT have a Dean
    if ($action === 'get_available_colleges_for_dean') {
        $role_res = $conn->query("SELECT role_id FROM roles WHERE role_name = 'Dean' LIMIT 1");
        $dean_role_id = $role_res->fetch_row()[0] ?? 0;

        $stmt = $conn->prepare("SELECT college_id FROM users WHERE role_id = ? AND college_id IS NOT NULL");
        $stmt->bind_param("i", $dean_role_id);
        $stmt->execute();
        $taken_colleges = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $taken_ids = array_column($taken_colleges, 'college_id');

        $query = "SELECT college_id, college_name FROM colleges";
        if (!empty($taken_ids)) {
            $placeholders = implode(',', array_fill(0, count($taken_ids), '?'));
            $query .= " WHERE college_id NOT IN ($placeholders)";
        }
        $query .= " ORDER BY college_name";
        
        $stmt_avail = $conn->prepare($query);
        if (!empty($taken_ids)) {
            $stmt_avail->bind_param(str_repeat('i', count($taken_ids)), ...$taken_ids);
        }
        $stmt_avail->execute();
        $available_colleges = $stmt_avail->get_result()->fetch_all(MYSQLI_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($available_colleges);
        exit;
    }

    // Action to get programs that do NOT have a Chairperson
    if ($action === 'get_available_programs_for_chair') {
        $role_res = $conn->query("SELECT role_id FROM roles WHERE role_name = 'Chairperson' LIMIT 1");
        $chair_role_id = $role_res->fetch_row()[0] ?? 0;

        $stmt = $conn->prepare("SELECT program_id FROM users WHERE role_id = ? AND program_id IS NOT NULL");
        $stmt->bind_param("i", $chair_role_id);
        $stmt->execute();
        $taken_programs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $taken_ids = array_column($taken_programs, 'program_id');

        $query = "SELECT p.program_id, p.program_name, p.program_code, c.college_code FROM programs p JOIN colleges c ON p.college_id = c.college_id";
        if (!empty($taken_ids)) {
            $placeholders = implode(',', array_fill(0, count($taken_ids), '?'));
            $query .= " WHERE p.program_id NOT IN ($placeholders)";
        }
        $query .= " ORDER BY c.college_code, p.program_name";
        
        $stmt_avail = $conn->prepare($query);
        if (!empty($taken_ids)) {
            $stmt_avail->bind_param(str_repeat('i', count($taken_ids)), ...$taken_ids);
        }
        $stmt_avail->execute();
        $available_programs = $stmt_avail->get_result()->fetch_all(MYSQLI_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($available_programs);
        exit;
    }
}

// Admin Check
if (!isset($_SESSION['user_id']) || (strtolower($_SESSION['role'] ?? '') !== 'admin' && strpos(strtolower($_SESSION['role'] ?? ''),'admin')===false)) {
    header('Location: login.php'); exit;
}
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');
$msg = "";

// Handle User Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $first_name_input = trim($_POST['first_name']);
    $middle_name_input = trim($_POST['middle_name'] ?? '');
    $last_name_input = trim($_POST['last_name']);
    $email_input = trim($_POST['email']);
    $role_id = intval($_POST['role_id']);
    $college_id = !empty($_POST['college_id']) ? intval($_POST['college_id']) : null;
    $program_id = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
    $password = password_hash('12345678', PASSWORD_BCRYPT); // Default password

    // Get role name for validation
    $role_res = $conn->query("SELECT role_name FROM roles WHERE role_id = $role_id");
    $role_name = $role_res->fetch_row()[0] ?? '';

    $error_msg = '';

    // Server-Side Validation
    if (strpos(strtolower($role_name), 'dean') !== false) {
        if (!$college_id) $error_msg = "A college must be selected for a Dean.";
        else {
            $check = $conn->query("SELECT user_id FROM users WHERE role_id = $role_id AND college_id = $college_id");
            if ($check->num_rows > 0) $error_msg = "This college already has an assigned Dean.";
        }
    } elseif (strpos(strtolower($role_name), 'chairperson') !== false) {
        if (!$program_id) $error_msg = "A program must be selected for a Chairperson.";
        else {
            $check = $conn->query("SELECT user_id FROM users WHERE role_id = $role_id AND program_id = $program_id");
            if ($check->num_rows > 0) $error_msg = "This program already has an assigned Chairperson.";
        }
    }


    if ($error_msg) {
        $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>âŒ $error_msg</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (firstname, middlename, lastname, email, password, role_id, college_id, program_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssiii", $first_name_input, $middle_name_input, $last_name_input, $email_input, $password, $role_id, $college_id, $program_id);
        if ($stmt->execute()) {
            $full_name_input = trim(implode(' ', array_filter([$first_name_input, $middle_name_input, $last_name_input], fn($part) => $part !== '')));
            $msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>âœ… User "$full_name_input" created successfully! Default password: <strong>12345678</strong></div>";
        } else {
            $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>âŒ Error: " . $conn->error . "</div>";
        }
    }


// Handle User Deletion
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    // Prevent deleting self
    if ($del_id != $_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE user_id = $del_id");
    }
    header("Location: users.php");
    exit;
}

// Fetch All Users with College Grouping
$sql = "SELECT u.*, r.role_name, p.program_code,
        CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) AS full_name, 
        COALESCE(c.college_id, cp.college_id, 0) as sort_college_id,
        COALESCE(c.college_name, cp.college_name, 'Administration / Unassigned') as sort_college_name,
        COALESCE(c.college_code, cp.college_code, 'ADMIN') as sort_college_code
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.role_id 
        LEFT JOIN programs p ON u.program_id = p.program_id 
        LEFT JOIN colleges c ON u.college_id = c.college_id 
        LEFT JOIN colleges cp ON p.college_id = cp.college_id
        ORDER BY sort_college_name, full_name ASC";

$result = $conn->query($sql);
$grouped_users = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        // Skip the currently logged-in admin (self)
        if ($row['user_id'] == $_SESSION['user_id']) continue;

        // Check if user is an Accreditor
        if (stripos($row['role_name'] ?? '', 'Accreditor') !== false) {
            $cid = 'ACC';
            $cname = 'Accreditors';
            $ccode = 'ACC';
        } else {
            $cid = $row['sort_college_id'];
            $cname = $row['sort_college_name'];
            $ccode = $row['sort_college_code'];
        }

        if (!isset($grouped_users[$cid])) {
            $grouped_users[$cid] = [
                'name' => $cname,
                'code' => $ccode,
                'users' => []
            ];
        }
        $grouped_users[$cid]['users'][] = $row;
    }
    
    // Move Accreditors to the top of the list
    if (isset($grouped_users['ACC'])) {
        $acc_group = $grouped_users['ACC'];
        unset($grouped_users['ACC']);
        $grouped_users = array_merge(['ACC' => $acc_group], $grouped_users);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Users — Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/admin-dashboard.css">
</head>
<body>

  <!-- Fixed Topbar -->
  <header class="topbar">
    <div class="flex items-center gap-4">
      <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('show')">
        <i class="fas fa-bars"></i>
      </button>
      <a href="admin_dashboard.php" class="topbar-brand">
        <i class="fas fa-shield-alt"></i> BISU Accreditation
      </a>
    </div>
    <div class="topbar-right">
      <div class="user-profile">
        <div class="user-info hidden sm:block">
          <span class="user-name"><?php echo $full_name; ?></span>
          <span class="user-role">Admin</span>
        </div>
        <div class="user-avatar">
          <?php echo strtoupper(substr($full_name, 0, 1)); ?>
        </div>
      </div>
      <div class="divider-vertical"></div>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <!-- Fixed Sidebar -->
  <aside class="sidebar">
    <nav class="sidebar-nav">
        <a href="admin_dashboard.php?view=dashboard" class="nav-item">
          <i class="fas fa-th-large"></i> Dashboard
        </a>
        <a href="users.php" class="nav-item active">
          <i class="fas fa-users"></i> Manage Users
        </a>
        <a href="colleges.php" class="nav-item"><i class="fas fa-university"></i> Manage Colleges</a>
        <a href="admin_cycles.php" class="nav-item">
          <i class="fas fa-sync-alt"></i> Manage Levels
        </a>
        <a href="documents.php" class="nav-item"><i class="fas fa-file-alt"></i> Documents</a>
        <a href="reports.php" class="nav-item"><i class="fas fa-chart-bar"></i> Reports / Logs</a>
        <a href="admin_dashboard.php?view=self_survey" class="nav-item"><i class="fas fa-tasks"></i> Self Survey</a>
    </nav>
    <div class="mt-auto p-4">
        <a href="profile.php" class="nav-item"><i class="fas fa-user-cog"></i> Manage Profile</a>
    </div>
  </aside>

  <main class="main-content">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-bisu">Manage Users</h1>
          <div class="text-sm text-slate-500">View and manage registered user accounts</div>
        </div>
      </div>
      
      <?= $msg ?>

      <!-- Create User Form -->
      <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm mb-8">
        <h2 class="text-lg font-bold text-slate-800 mb-4">Create New User</h2>
        <form id="addUserForm" method="POST" class="space-y-4">
            <input type="hidden" name="create_user" value="1">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">First Name</label>
                    <input type="text" name="first_name" required class="w-full p-2 border border-slate-200 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Middle Name</label>
                    <input type="text" name="middle_name" class="w-full p-2 border border-slate-200 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Last Name</label>
                    <input type="text" name="last_name" required class="w-full p-2 border border-slate-200 rounded-lg">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="email" required class="w-full p-2 border border-slate-200 rounded-lg">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                <select id="roleSelect" name="role_id" required class="w-full p-2 border border-slate-200 rounded-lg">
                    <option value="">Select a role...</option>
                    <?php 
                        $roles_res = $conn->query("SELECT * FROM roles ORDER BY role_name");
                        while($role = $roles_res->fetch_assoc()) {
                            echo "<option value='{$role['role_id']}' data-role-name='{$role['role_name']}'>{$role['role_name']}</option>";
                        }
                    ?>
                </select>
            </div>

            <!-- College selection for Deans -->
            <div id="collegeContainer" class="hidden">
                <label class="block text-sm font-medium text-slate-700 mb-1">Assign to College</label>
                <select id="collegeSelect" name="college_id" class="w-full p-2 border border-slate-200 rounded-lg"></select>
                <p id="collegeMessage" class="text-xs text-amber-600 mt-1"></p>
            </div>

            <!-- Program selection for Chairpersons -->
            <div id="programContainer" class="hidden">
                <label class="block text-sm font-medium text-slate-700 mb-1">Assign to Program</label>
                <select id="programSelect" name="program_id" class="w-full p-2 border border-slate-200 rounded-lg"></select>
                <p id="programMessage" class="text-xs text-amber-600 mt-1"></p>
            </div>
            
            <div class="text-right pt-2">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Create User</button>
            </div>
        </form>
      </div>

      <?php if (empty($grouped_users)): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-500">
            <i class="fas fa-users text-4xl mb-3 opacity-30"></i>
            <p>No users found.</p>
        </div>
      <?php else: ?>
        <?php foreach($grouped_users as $group): 
            $is_acc = ($group['code'] === 'ACC');
            $icon_color = $is_acc ? 'text-emerald-600' : 'text-indigo-600';
            $icon_bg = $is_acc ? 'bg-emerald-50' : 'bg-white';
        ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-8 overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg <?= $icon_bg ?> border border-slate-200 flex items-center justify-center <?= $icon_color ?> font-bold shadow-sm">
                        <?php if($is_acc): ?><i class="fas fa-user-check"></i><?php else: ?><?= htmlspecialchars($group['code']) ?><?php endif; ?>
                    </div>
                    <h3 class="font-bold text-slate-700 text-lg"><?= htmlspecialchars($group['name']) ?></h3>
                </div>
                <span class="text-xs font-bold px-3 py-1 bg-white border border-slate-200 rounded-full text-slate-500"><?= count($group['users']) ?> Users</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-white text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="p-4 font-semibold">Name</th>
                            <th class="p-4 font-semibold">Email</th>
                            <th class="p-4 font-semibold">Role</th>
                            <th class="p-4 font-semibold">Program / Affiliation</th>
                            <th class="p-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($group['users'] as $u): 
                             $affiliation = '-';
                             if ($u['program_code']) $affiliation = $u['program_code'];
                             elseif ($u['sort_college_code'] !== 'ADMIN') $affiliation = $u['sort_college_code'];
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="p-4 font-medium text-slate-800">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xs">
                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                    </div>
                                    <?= htmlspecialchars($u['full_name']) ?>
                                </div>
                            </td>
                            <td class="p-4 text-slate-600"><?= htmlspecialchars($u['email']) ?></td>
                            <td class="p-4"><span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-xs font-bold"><?= htmlspecialchars($u['role_name']) ?></span></td>
                            <td class="p-4 text-slate-500"><?= htmlspecialchars($affiliation) ?></td>
                            <td class="p-4 text-right">
                                <a href="?delete_id=<?= $u['user_id'] ?>" onclick="return confirm('Are you sure you want to delete this user?')" class="text-red-400 hover:text-red-600 transition-colors p-2 hover:bg-red-50 rounded-full"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
  </main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('roleSelect');
    const collegeContainer = document.getElementById('collegeContainer');
    const collegeSelect = document.getElementById('collegeSelect');
    const collegeMessage = document.getElementById('collegeMessage');
    const programContainer = document.getElementById('programContainer');
    const programSelect = document.getElementById('programSelect');
    const programMessage = document.getElementById('programMessage');

    roleSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const roleName = selectedOption.dataset.roleName.toLowerCase();

        // Hide all conditional containers first
        collegeContainer.classList.add('hidden');
        programContainer.classList.add('hidden');
        collegeSelect.required = false;
        programSelect.required = false;
        collegeMessage.textContent = '';
        programMessage.textContent = '';

        if (roleName.includes('dean')) {
            collegeContainer.classList.remove('hidden');
            collegeSelect.required = true;
            populateCollegesForDean();
        } else if (roleName.includes('chairperson')) {
            programContainer.classList.remove('hidden');
            programSelect.required = true;
            populateProgramsForChair();
        }
    });

    function populateCollegesForDean() {
        collegeSelect.innerHTML = '<option>Loading available colleges...</option>';
        fetch('users.php?action=get_available_colleges_for_dean')
            .then(response => response.json())
            .then(data => {
                collegeSelect.innerHTML = '';
                if (data.length === 0) {
                    collegeMessage.textContent = 'All colleges already have an assigned Dean.';
                    collegeSelect.innerHTML = '<option value="">No available colleges</option>';
                } else {
                    collegeSelect.innerHTML = '<option value="">Select a college...</option>';
                    data.forEach(college => {
                        const option = document.createElement('option');
                        option.value = college.college_id;
                        option.textContent = college.college_name;
                        collegeSelect.appendChild(option);
                    });
                }
            });
    }

    function populateProgramsForChair() {
        programSelect.innerHTML = '<option>Loading available programs...</option>';
        fetch('users.php?action=get_available_programs_for_chair')
            .then(response => response.json())
            .then(data => {
                programSelect.innerHTML = '';
                if (data.length === 0) {
                    programMessage.textContent = 'All programs already have an assigned Chairperson.';
                    programSelect.innerHTML = '<option value="">No available programs</option>';
                } else {
                    programSelect.innerHTML = '<option value="">Select a program...</option>';
                    data.forEach(program => {
                        const option = document.createElement('option');
                        option.value = program.program_id;
                        option.textContent = `[${program.college_code}] ${program.program_name}`;
                        programSelect.appendChild(option);
                    });
                }
            });
    }
});
</script>
</body>
</html>
