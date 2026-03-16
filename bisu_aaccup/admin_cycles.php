<?php
session_start();
require_once __DIR__ . '/config/db.php';

// Admin Check
if (!isset($_SESSION['user_id']) || (strtolower($_SESSION['role'] ?? '') !== 'admin' && strpos(strtolower($_SESSION['role'] ?? ''),'admin')===false)) {
    header('Location: login.php');
    exit;
}

$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');
$msg = "";

// Check if database schema is up to date
$check_schema = $conn->query("SHOW COLUMNS FROM cycles LIKE 'survey_date'");
$has_schema = ($check_schema && $check_schema->num_rows > 0);

if (!$has_schema) {
    $msg = "<div class='bg-amber-100 text-amber-800 p-3 rounded mb-4'>⚠️ <strong>Database Update Required:</strong> Please run <a href='fix_cycles_schema.php' class='underline font-bold'>fix_cycles_schema.php</a> to enable scheduling features.</div>";
}

// Handle New Cycle Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_cycle'])) {
    $program_id = intval($_POST['program_id']);
    $level = intval($_POST['level']);
    $start_date = $_POST['valid_from'];
    $survey_date = !empty($_POST['survey_date']) ? $_POST['survey_date'] : NULL;
    $deadline = !empty($_POST['submission_deadline']) ? $_POST['submission_deadline'] : NULL;
    
    if ($program_id && $level && $start_date) {
        // 1. Mark previous active cycles for this program as Completed (Status 4)
        $conn->query("UPDATE cycles SET status_id = 4 WHERE program_id = $program_id AND status_id = 1");
        
        // 2. Insert new Active Cycle (Status 1)
        if ($has_schema) {
            $stmt = $conn->prepare("INSERT INTO cycles (program_id, level, status_id, valid_from, survey_date, submission_deadline) VALUES (?, ?, 1, ?, ?, ?)");
            $stmt->bind_param("iissss", $program_id, $level, $start_date, $survey_date, $deadline);
        } else {
            $stmt = $conn->prepare("INSERT INTO cycles (program_id, level, status_id, valid_from) VALUES (?, ?, 1, ?)");
            $stmt->bind_param("iis", $program_id, $level, $start_date);
        }
        
        if ($stmt->execute()) {
            $msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>✅ New Level $level Cycle started successfully!</div>";
        } else {
            $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Error: " . $conn->error . "</div>";
        }
    }
}

// Fetch Programs and their Current Active Level
$survey_col = $has_schema ? "survey_date" : "NULL";
$deadline_col = $has_schema ? "submission_deadline" : "NULL";

$sql = "SELECT p.program_id, p.program_name, p.program_code, c.college_code,
        (SELECT level FROM cycles WHERE program_id = p.program_id AND status_id = 1 ORDER BY valid_from DESC LIMIT 1) as current_level,
        (SELECT valid_from FROM cycles WHERE program_id = p.program_id AND status_id = 1 ORDER BY valid_from DESC LIMIT 1) as start_date,
        (SELECT $survey_col FROM cycles WHERE program_id = p.program_id AND status_id = 1 ORDER BY valid_from DESC LIMIT 1) as survey_date,
        (SELECT $deadline_col FROM cycles WHERE program_id = p.program_id AND status_id = 1 ORDER BY valid_from DESC LIMIT 1) as deadline
        FROM programs p
        JOIN colleges c ON p.college_id = c.college_id
        ORDER BY c.college_code, p.program_name";
$programs = $conn->query($sql);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Levels — Admin</title>
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
          <span class="user-name"><?= $full_name ?></span>
          <span class="user-role">Admin</span>
        </div>
        <div class="user-avatar">
          <?= strtoupper(substr($full_name, 0, 1)) ?>
        </div>
      </div>
      <div class="divider-vertical"></div>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <!-- Fixed Sidebar -->
  <aside class="sidebar">
    <nav class="sidebar-nav">
        <a href="admin_dashboard.php?view=dashboard" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Manage Users</a>
        <a href="colleges.php" class="nav-item"><i class="fas fa-university"></i> Manage Colleges</a>
        <a href="admin_cycles.php" class="nav-item active">
          <i class="fas fa-sync-alt"></i>
          Manage Levels
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
          <h1 class="text-2xl font-semibold text-bisu">Accreditation Levels</h1>
          <div class="text-sm text-slate-500">Manage current accreditation levels for each program</div>
        </div>
      </div>

      <?= $msg ?>

      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                <tr>
                    <th class="p-4">College</th>
                    <th class="p-4">Program</th>
                    <th class="p-4">Current Level</th>
                    <th class="p-4">Schedule</th>
                    <th class="p-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php while($p = $programs->fetch_assoc()): 
                    $level = $p['current_level'] ?? 0;
                    $level_badge = $level > 0 
                        ? "<span class='bg-indigo-100 text-indigo-700 px-2 py-1 rounded text-xs font-bold'>Level $level</span>" 
                        : "<span class='bg-slate-100 text-slate-500 px-2 py-1 rounded text-xs'>Not Started</span>";
                    
                    $schedule_info = "<div class='text-xs text-slate-500'>Start: " . ($p['start_date'] ? date('M d, Y', strtotime($p['start_date'])) : '-') . "</div>";
                    if ($p['survey_date']) {
                        $schedule_info .= "<div class='text-xs font-bold text-emerald-600'>Visit: " . date('M d, Y', strtotime($p['survey_date'])) . "</div>";
                    }
                    if ($p['deadline']) {
                        $schedule_info .= "<div class='text-xs font-bold text-red-500'>Due: " . date('M d, Y', strtotime($p['deadline'])) . "</div>";
                    }
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="p-4 font-bold text-slate-400"><?= htmlspecialchars($p['college_code']) ?></td>
                    <td class="p-4">
                        <div class="font-medium text-slate-800"><?= htmlspecialchars($p['program_code']) ?></div>
                        <div class="text-xs text-slate-500"><?= htmlspecialchars($p['program_name']) ?></div>
                    </td>
                    <td class="p-4"><?= $level_badge ?></td>
                    <td class="p-4"><?= $schedule_info ?></td>
                    <td class="p-4 text-right">
                        <button onclick="openCycleModal(<?= $p['program_id'] ?>, '<?= htmlspecialchars($p['program_code']) ?>', <?= $level ?>)" class="text-indigo-600 hover:text-indigo-800 font-medium text-xs border border-indigo-200 bg-indigo-50 px-3 py-1.5 rounded-lg transition-colors">
                            <i class="fas fa-level-up-alt mr-1"></i> Update Level
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
      </div>
  </main>

  <!-- Update Level Modal -->
  <div id="cycleModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800">Update Accreditation Level</h3>
            <button onclick="document.getElementById('cycleModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="create_cycle" value="1">
                <input type="hidden" name="program_id" id="modalProgramId">
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Program</label>
                    <div id="modalProgramName" class="text-lg font-bold text-indigo-700"></div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">New Level</label>
                    <select name="level" id="modalLevelSelect" class="w-full p-2 border border-slate-300 rounded-lg outline-none focus:border-indigo-500">
                        <option value="1">Level 1</option>
                        <option value="2">Level 2</option>
                        <option value="3">Level 3</option>
                        <option value="4">Level 4</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Start Date</label>
                    <input type="date" name="valid_from" value="<?= date('Y-m-d') ?>" required class="w-full p-2 border border-slate-300 rounded-lg">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Submission Deadline</label>
                        <input type="date" name="submission_deadline" class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Survey Visit Date</label>
                        <input type="date" name="survey_date" class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                </div>

                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('cycleModal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
  </div>

  <script>
    function openCycleModal(id, code, currentLevel) {
        document.getElementById('modalProgramId').value = id;
        document.getElementById('modalProgramName').textContent = code;
        
        // Set default selection to next level
        let nextLevel = currentLevel + 1;
        if(nextLevel > 4) nextLevel = 4;
        document.getElementById('modalLevelSelect').value = nextLevel;
        
        document.getElementById('cycleModal').classList.remove('hidden');
    }
  </script>
</body>
</html>