<?php
session_start();
require_once __DIR__ . '/config/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// Role Check: Allow Chairperson or Admin
$role = strtolower(trim($_SESSION['role'] ?? ''));
if (strpos($role, 'chairperson') === false && strpos($role, 'admin') === false) {
  header('Location: role_home.php');
  exit;
}

// Get current view
$view = isset($_GET['view']) ? $_GET['view'] : 'overview';
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$selected_program_info = null;

// Check for welcome message
$show_welcome = false;
if (isset($_SESSION['show_welcome']) && $_SESSION['show_welcome']) {
    $show_welcome = true;
    unset($_SESSION['show_welcome']);
}

// Fetch Chairperson's Program Details
$chairperson_program_id = 0;
$program_name = "Unassigned Program";
$program_code = "";
$college_code = "";

$stmt = $conn->prepare("SELECT u.program_id, p.program_name, p.program_code, c.college_code
                        FROM users u 
                        LEFT JOIN programs p ON u.program_id = p.program_id 
                        LEFT JOIN colleges c ON p.college_id = c.college_id
                        WHERE u.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $chairperson_program_id = $row['program_id'];
    if ($row['program_name']) {
        $program_name = $row['program_name'];
        $program_code = $row['program_code'];
        $college_code = strtoupper($row['college_code']);
    }
}

// Filter by specific program if selected
$filter_program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;
$selected_level = isset($_GET['level']) && $_GET['level'] !== '' ? intval($_GET['level']) : null;

// Handle Feedback Submission (Chairperson's Review)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $doc_id = intval($_POST['document_id']);
    $feedback = trim($_POST['feedback_text']);
    
    if ($doc_id && $feedback) {
        $stmt = $conn->prepare("INSERT INTO document_feedback (doc_id, user_id, feedback_text) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iis", $doc_id, $user_id, $feedback);
            $stmt->execute();

            // Create notification for the uploader
            $uploader_id = null;
            $stmt_get_uploader = $conn->prepare("SELECT uploaded_by FROM documents WHERE doc_id = ?");
            $stmt_get_uploader->bind_param("i", $doc_id);
            $stmt_get_uploader->execute();
            $res_uploader = $stmt_get_uploader->get_result();
            if ($res_uploader && $row = $res_uploader->fetch_assoc()) {
                $uploader_id = $row['uploaded_by'];
            }
            if ($uploader_id && $uploader_id != $user_id) {
                $stmt_notif = $conn->prepare("INSERT INTO user_notifications (user_id, doc_id, message) VALUES (?, ?, 'Your Chairperson has left feedback on your document.')");
                if ($stmt_notif) {
                    $stmt_notif->bind_param("ii", $uploader_id, $doc_id);
                    $stmt_notif->execute();
                }
            }
            $stmt->close();
        }
    }
    header("Location: chairperson_dashboard.php?view=documents&success=1");
    exit;
}

// Handle Add Single Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignment'])) {
    $area_id = intval($_POST['area_id']);
    $user_id = intval($_POST['user_id']);
    $type_id = intval($_POST['type_id']); // 0 for All/General

    // Verify user belongs to program
    $check = $conn->query("SELECT user_id FROM users WHERE user_id = $user_id AND program_id = $chairperson_program_id");
    
    if ($check && $check->num_rows > 0) {
        // Insert ignore to prevent duplicates
        $stmt = $conn->prepare("INSERT IGNORE INTO faculty_area_assignments (user_id, area_id, type_id) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $user_id, $area_id, $type_id);
        $stmt->execute();
    }
    header("Location: chairperson_dashboard.php?view=faculty&msg=added");
    exit;
}

// Handle Remove Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_assignment'])) {
    $area_id = intval($_POST['area_id']);
    $user_id = intval($_POST['user_id']);
    $type_id = intval($_POST['type_id']);

    $stmt = $conn->prepare("DELETE FROM faculty_area_assignments WHERE user_id = ? AND area_id = ? AND type_id = ?");
    $stmt->bind_param("iii", $user_id, $area_id, $type_id);
    $stmt->execute();
    header("Location: chairperson_dashboard.php?view=faculty&msg=removed");
    exit;
}

// Fetch Overview Stats
$stats = ['faculty' => 0, 'docs' => 0, 'areas_started' => 0];
if ($chairperson_program_id) {
    // Count Faculty in this program
    $q1 = $conn->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.program_id = $chairperson_program_id AND (r.role_name LIKE '%Faculty%' OR r.role_name LIKE '%Focal%')");
    $stats['faculty'] = ($q1 && $row = $q1->fetch_row()) ? $row[0] : 0;

    // Count Total Documents for this program
    $q2 = $conn->query("SELECT COUNT(*) FROM documents d JOIN cycles c ON d.cycle_id = c.cycle_id WHERE c.program_id = $chairperson_program_id");
    $stats['docs'] = ($q2 && $row = $q2->fetch_row()) ? $row[0] : 0;

    // Count Areas with at least one upload in this program
    $q3 = $conn->query("SELECT COUNT(DISTINCT d.area_id) FROM documents d JOIN cycles c ON d.cycle_id = c.cycle_id WHERE c.program_id = $chairperson_program_id");
    $stats['areas_started'] = ($q3 && $row = $q3->fetch_row()) ? $row[0] : 0;
}

// Fetch Documents (For Overview or Full List)
$documents = [];
if ($view === 'overview' || $view === 'documents') {
    $limit = ($view === 'overview') ? "LIMIT 5" : "";
    $where_clause = "WHERE p.program_id = $chairperson_program_id";
    if ($selected_level) {
        $where_clause .= " AND c.level = $selected_level";
    }

    // Check feedback column name dynamically
    $check_fb = $conn->query("SHOW COLUMNS FROM document_feedback LIKE 'doc_id'");
    $fb_col = ($check_fb && $check_fb->num_rows > 0) ? 'doc_id' : 'document_id';

    $sql = "SELECT d.*, a.area_no, a.area_title, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) as uploader, dt.type_name, p.program_code,
            (SELECT COUNT(*) FROM document_feedback WHERE $fb_col = d.doc_id) as feedback_count
            FROM documents d
            JOIN cycles c ON d.cycle_id = c.cycle_id
            JOIN programs p ON c.program_id = p.program_id
            JOIN areas a ON d.area_id = a.area_id
            LEFT JOIN users u ON d.uploaded_by = u.user_id
            LEFT JOIN document_types dt ON d.type_id = dt.type_id
            $where_clause
            ORDER BY d.uploaded_at DESC $limit";
    
    $res = $conn->query($sql);
    if($res) while($r = $res->fetch_assoc()) $documents[] = $r;
}

// Fetch Faculty List
$faculty_assignment_data = [];
$all_faculty_in_program = [];
$faculty_map = [];
$doc_types = [];

// Fetch Document Types for Dropdown
$dt_res = $conn->query("SELECT * FROM document_types ORDER BY type_name");
if($dt_res) while($r = $dt_res->fetch_assoc()) $doc_types[] = $r;

if ($view === 'faculty' && $chairperson_program_id) {
    // 1. Get all areas
    $areas_res = $conn->query("SELECT * FROM areas ORDER BY area_no ASC");
    if ($areas_res) {
        while ($area = $areas_res->fetch_assoc()) {
            $faculty_assignment_data[$area['area_id']] = [
                'info' => $area,
                'assigned_faculty' => []
            ];
        }
    }

    // 2. Get all faculty for this program
    $faculty_res = $conn->query("SELECT user_id, full_name FROM users WHERE program_id = $chairperson_program_id AND role_id IN (SELECT role_id FROM roles WHERE role_name LIKE '%Faculty%' OR role_name LIKE '%Focal%') ORDER BY full_name ASC");
    if ($faculty_res) {
        while ($faculty = $faculty_res->fetch_assoc()) {
            $all_faculty_in_program[] = $faculty;
            $faculty_map[$faculty['user_id']] = $faculty['full_name'];
        }
    }
    
    // 3. Get all current assignments for this program's faculty
    $assignments_res = $conn->query("SELECT faa.user_id, faa.area_id, faa.type_id, dt.type_name 
                                     FROM faculty_area_assignments faa 
                                     JOIN users u ON faa.user_id = u.user_id 
                                     LEFT JOIN document_types dt ON faa.type_id = dt.type_id
                                     WHERE u.program_id = $chairperson_program_id");
    if ($assignments_res) {
        while ($assignment = $assignments_res->fetch_assoc()) {
            if (isset($faculty_assignment_data[$assignment['area_id']])) {
                // Store full assignment details including type
                $faculty_assignment_data[$assignment['area_id']]['assigned_faculty'][] = $assignment;
            }
        }
    }
}

// Fetch Area Performance Data
$area_performance = [];
if ($view === 'performance' && $chairperson_program_id) {
    // For a chairperson, we already know the program, so we fetch its details for the header.
    $prog_stmt = $conn->prepare("SELECT * FROM programs WHERE program_id = ?");
    $prog_stmt->bind_param("i", $chairperson_program_id);
    $prog_stmt->execute();
    $selected_program_info = $prog_stmt->get_result()->fetch_assoc();

    // Fetch area performance for the chairperson's program
    if ($selected_program_info) {
        $sql = "SELECT a.area_id, a.area_no, a.area_title, 
                (SELECT COUNT(*) FROM documents d JOIN cycles c ON d.cycle_id = c.cycle_id WHERE d.area_id = a.area_id AND c.program_id = ?) as doc_count,
                (SELECT GROUP_CONCAT(DISTINCT CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) SEPARATOR ', ')
                 FROM faculty_area_assignments faa 
                 JOIN users u ON faa.user_id = u.user_id 
                 WHERE faa.area_id = a.area_id AND u.program_id = ?) as assigned_faculty,
                 (SELECT COUNT(DISTINCT user_id) FROM faculty_area_assignments WHERE area_id = a.area_id) as faculty_count
                FROM areas a ORDER BY a.area_no ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $chairperson_program_id, $chairperson_program_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if($res) while($r = $res->fetch_assoc()) $area_performance[] = $r;
    }
}

// Fetch Survey Results Data
$survey_results_data = [];
$all_areas_list_survey = [];
if ($view === 'self_survey' && $chairperson_program_id) {
    // Fetch all areas for the table structure
    $a_res = $conn->query("SELECT * FROM areas ORDER BY area_no");
    if ($a_res) while($row = $a_res->fetch_assoc()) $all_areas_list_survey[$row['area_id']] = $row;
    // The main query to get all ratings for the chairperson's program
    $sql_survey = "SELECT
                p.program_id, p.program_code, p.program_name,
                r.area_id,
                r.accreditor_type,
                AVG(r.rating) as average_rating
            FROM survey_ratings r
            JOIN programs p ON r.program_id = p.program_id
            WHERE p.program_id = ?
            GROUP BY p.program_id, p.program_code, p.program_name, r.area_id, r.accreditor_type
            ORDER BY p.program_name, r.area_id";
    
    $stmt_survey = $conn->prepare($sql_survey);
    $stmt_survey->bind_param("i", $chairperson_program_id);
    $stmt_survey->execute();
    $result_survey = $stmt_survey->get_result();

    if ($result_survey) {
        while ($row = $result_survey->fetch_assoc()) {
            $survey_results_data[$row['program_id']]['details'] = ['name' => $row['program_name'], 'code' => $row['program_code']];
            $survey_results_data[$row['program_id']]['scores'][$row['area_id']][$row['accreditor_type']] = $row['average_rating'];
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chairperson Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: { colors: { bisu: '#4f46e5' } } } }
  </script>
  <?php
    // Dynamic Sidebar Theme based on College
    $sidebar_bg = '#eff6ff'; // Default Blue-50
    $sidebar_border = '#dbeafe'; // Default Blue-200
    $nav_hover_bg = '#dbeafe';
    $nav_hover_text = '#4f46e5'; // Indigo-600
    $nav_active_bg = 'linear-gradient(135deg, #4f46e5, #4338ca)';
    $nav_active_text = '#ffffff';

    // Topbar Theme Defaults
    $topbar_bg = 'linear-gradient(135deg, #6c63ff, #3b4ba8)'; // Default Indigo
    $logout_hover_bg = '#312e81'; // Indigo-900

    if ($college_code === 'COS') { // College of Science (Green)
        $sidebar_bg = '#f0fdf4'; // Green-50
        $sidebar_border = '#bbf7d0'; // Green-200
        $nav_hover_bg = '#dcfce7'; // Green-100
        $nav_hover_text = '#15803d'; // Green-700
        $nav_active_bg = 'linear-gradient(135deg, #16a34a, #15803d)'; // Green-600 -> Green-700
        
        $topbar_bg = 'linear-gradient(135deg, #22c55e, #15803d)'; // Green-500 -> Green-700
        $logout_hover_bg = '#14532d'; // Green-900
    } elseif ($college_code === 'CTE') { // College of Teacher Ed (Red/Rose)
        $sidebar_bg = '#fff1f2'; // Rose-50
        $sidebar_border = '#fecdd3'; // Rose-200
        $nav_hover_bg = '#ffe4e6'; // Rose-100
        $nav_hover_text = '#be123c'; // Rose-700
        $nav_active_bg = 'linear-gradient(135deg, #e11d48, #be123c)';
        
        $topbar_bg = 'linear-gradient(135deg, #f43f5e, #be123c)'; // Rose-500 -> Rose-700
        $logout_hover_bg = '#881337'; // Rose-900
    } elseif ($college_code === 'CBM') { // Business & Management (Amber/Orange)
        $sidebar_bg = '#fffbeb'; // Amber-50
        $sidebar_border = '#fde68a'; // Amber-200
        $nav_hover_bg = '#fef3c7'; // Amber-100
        $nav_hover_text = '#b45309'; // Amber-700
        $nav_active_bg = 'linear-gradient(135deg, #d97706, #b45309)';
        
        $topbar_bg = 'linear-gradient(135deg, #f59e0b, #b45309)'; // Amber-500 -> Amber-700
        $logout_hover_bg = '#78350f'; // Amber-900
    } elseif ($college_code === 'CFMS') { // College of Fisheries (Blue)
        $sidebar_bg = '#eff6ff'; // Blue-50
        $sidebar_border = '#bfdbfe'; // Blue-200
        $nav_hover_bg = '#dbeafe'; // Blue-100
        $nav_hover_text = '#1d4ed8'; // Blue-700
        $nav_active_bg = 'linear-gradient(135deg, #2563eb, #1e40af)'; // Blue-600 -> Blue-800
        
        $topbar_bg = 'linear-gradient(135deg, #3b82f6, #1d4ed8)'; // Blue-500 -> Blue-700
        $logout_hover_bg = '#1e3a8a'; // Blue-900
    }
  ?>
  <style>
    /* Override Sidebar Colors dynamically */
    .sidebar { background-color: <?= $sidebar_bg ?> !important; border-right-color: <?= $sidebar_border ?> !important; }
    .nav-item { color: #64748b; } /* Default text */
    .nav-item:hover { background-color: <?= $nav_hover_bg ?> !important; color: <?= $nav_hover_text ?> !important; }
    .nav-item.active { background: <?= $nav_active_bg ?> !important; color: <?= $nav_active_text ?> !important; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    /* Override Topbar Color dynamically */
    .topbar { background: <?= $topbar_bg ?> !important; }
    .logout-btn:hover { background-color: <?= $logout_hover_bg ?> !important; }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/admin-dashboard.css">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
  <header class="topbar">
    <div class="left">
      <button class="menu-toggle lg:hidden text-slate-600 hover:text-indigo-600 mr-3" onclick="document.querySelector('.sidebar').classList.toggle('show')">
        <i class="fas fa-bars fa-lg"></i>
      </button>
      <div class="brand">
        <i class="fas fa-user-tie"></i> <span>Chairperson Dashboard</span>
      </div>
    </div>
    <div class="right">
      <div class="user-profile">
        <div class="user-info hidden sm:block">
          <span class="user-name"><?= htmlspecialchars($full_name) ?></span>
          <span class="user-role">Chairperson</span>
        </div>
        <?php if (!empty($_SESSION['avatar_path']) && file_exists($_SESSION['avatar_path'])): ?>
            <img src="<?= htmlspecialchars($_SESSION['avatar_path']) ?>" alt="Avatar" class="w-8 h-8 rounded-full object-cover">
        <?php else: ?>
            <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center font-bold text-sm"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
        <?php endif; ?>
      </div>
      <div class="divider-vertical"></div>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <aside class="sidebar">
    <div class="px-6 py-4 mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Program</div>
        <div class="text-sm font-semibold text-amber-600 mt-1 leading-tight"><?= htmlspecialchars($program_name) ?></div>
    </div>
    <nav class="sidebar-nav">
      <a href="?view=overview" class="nav-item <?= $view === 'overview' ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> Overview</a>
      <a href="?view=documents" class="nav-item <?= $view === 'documents' ? 'active' : '' ?>"><i class="fas fa-folder-open"></i> Program Documents</a>
      <a href="?view=faculty" class="nav-item <?= $view === 'faculty' ? 'active' : '' ?>"><i class="fas fa-users-cog"></i> Assign Faculty</a>
      <a href="?view=performance" class="nav-item <?= $view === 'performance' ? 'active' : '' ?>"><i class="fas fa-chart-line"></i> Area Performance</a>
      <a href="?view=self_survey" class="nav-item <?= $view === 'self_survey' ? 'active' : '' ?>"><i class="fas fa-tasks"></i> Self Survey</a>
    </nav>
    <div class="mt-auto p-4">
        <a href="profile.php" class="nav-item"><i class="fas fa-user-cog"></i> Manage Profile</a>
    </div>
  </aside>

  <main class="main-content">
    <?php if ($view === 'overview'): ?>
      <section class="cards">
        <div class="card"><div class="card-title">Faculty Members</div><div class="card-value"><?= $stats['faculty'] ?></div></div>
        <div class="card"><div class="card-title">Total Documents</div><div class="card-value text-amber-600"><?= $stats['docs'] ?></div></div>
        <div class="card"><div class="card-title">Areas Started</div><div class="card-value"><?= $stats['areas_started'] ?> / 10</div></div>
      </section>

      <section class="panel">
        <h2>Recent Uploads</h2>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                <tr><th class="p-4">File</th><th class="p-4">Program</th><th class="p-4">Area</th><th class="p-4">Uploader</th><th class="p-4">Date</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach($documents as $doc): ?>
                <tr class="hover:bg-slate-50">
                    <td class="p-4 font-medium text-indigo-600"><a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank"><?= htmlspecialchars($doc['file_name']) ?></a></td>
                    <td class="p-4"><span class="bg-blue-50 text-blue-700 px-2 py-1 rounded text-xs font-bold"><?= htmlspecialchars($doc['program_code']) ?></span></td>
                    <td class="p-4">Area <?= htmlspecialchars($doc['area_no']) ?></td>
                    <td class="p-4 text-slate-600"><?= htmlspecialchars($doc['uploader']) ?></td>
                    <td class="p-4 text-slate-500"><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($documents)) echo "<tr><td colspan='4' class='p-4 text-center text-slate-500'>No recent documents found.</td></tr>"; ?>
            </tbody>
          </table>
        </div>
      </section>

    <?php elseif ($view === 'documents'): ?>
      <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Department Documents</h1>
            <div class="text-sm text-slate-500">Review and audit documents uploaded by your faculty.</div>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="view" value="documents">
            <select name="level" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" onchange="this.form.submit()">
                <option value="">All Levels</option>
                <option value="1" <?= ($selected_level == 1) ? 'selected' : '' ?>>Level 1</option>
                <option value="2" <?= ($selected_level == 2) ? 'selected' : '' ?>>Level 2</option>
                <option value="3" <?= ($selected_level == 3) ? 'selected' : '' ?>>Level 3</option>
                <option value="4" <?= ($selected_level == 4) ? 'selected' : '' ?>>Level 4</option>
            </select>
        </form>
      </div>
      
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
              <tr><th class="p-4">Document</th><th class="p-4">Program</th><th class="p-4">Area</th><th class="p-4">Type</th><th class="p-4">Uploaded By</th><th class="p-4">Date</th><th class="p-4 text-right">Action</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php if (empty($documents)): ?>
                <tr><td colspan="6" class="p-6 text-center text-slate-500">No documents found.</td></tr>
              <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                  <tr class="hover:bg-slate-50 transition-colors">
                    <td class="p-4 font-medium text-slate-800">
                      <div class="flex items-center gap-3">
                        <i class="fas fa-file-pdf text-red-500 text-lg"></i>
                        <?= htmlspecialchars($doc['file_name']) ?>
                      </div>
                    </td>
                    <td class="p-4"><span class="bg-blue-50 text-blue-700 px-2 py-1 rounded text-xs font-bold"><?= htmlspecialchars($doc['program_code']) ?></span></td>
                    <td class="p-4 text-slate-600">Area <?= htmlspecialchars($doc['area_no']) ?></td>
                    <td class="p-4"><span class="px-2 py-1 bg-amber-50 text-amber-700 rounded text-xs font-bold"><?= htmlspecialchars($doc['type_name']) ?></span></td>
                    <td class="p-4 text-slate-600"><?= htmlspecialchars($doc['uploader'] ?? 'Unknown') ?></td>
                    <td class="p-4 text-slate-500"><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
                    <td class="p-4 text-right">
                      <button onclick="openAuditModal(<?= $doc['doc_id'] ?>, '<?= htmlspecialchars($doc['file_path']) ?>', '<?= htmlspecialchars($doc['file_name']) ?>')" class="btn btn-ghost small text-indigo-600 hover:text-indigo-800">
                        <i class="fas fa-search"></i> Review
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
      </div>

    <?php elseif ($view === 'faculty'): ?>
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Assign Faculty</h1>
        <p class="text-slate-500 text-sm">Assign focal persons to specific areas.</p>
      </div>
      
      <?php if(isset($_GET['msg']) && $_GET['msg'] == 'assigned'): ?>
      <?php elseif(isset($_GET['msg']) && $_GET['msg'] == 'added'): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4">✅ Faculty assigned successfully!</div>
      <?php elseif(isset($_GET['msg']) && $_GET['msg'] == 'removed'): ?>
        <div class="bg-amber-100 text-amber-700 p-3 rounded mb-4">🗑️ Assignment removed.</div>
      <?php endif; ?>
      
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <?php if (empty($faculty_assignment_data)): ?>
            <div class="p-12 text-center">
                <p class="text-slate-500">No areas found in the system to assign faculty to.</p>
            </div>
        <?php else: ?>
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="p-4 w-1/4">Area</th>
                        <th class="p-4 w-1/2">Assigned Team</th>
                        <th class="p-4 w-1/4">Add Faculty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($faculty_assignment_data as $area_id => $data): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="p-4 align-top">
                            <div class="font-bold text-slate-800 text-base">Area <?= htmlspecialchars($data['info']['area_no']) ?></div>
                            <div class="text-xs text-slate-500 mt-1 leading-relaxed"><?= htmlspecialchars($data['info']['area_title']) ?></div>
                        </td>
                        <td class="p-4 align-top">
                            <?php if(empty($data['assigned_faculty'])): ?>
                                <div class="text-xs text-slate-400 italic p-2">No faculty assigned yet.</div>
                            <?php else: ?>
                                <div class="space-y-2">
                                <?php foreach($data['assigned_faculty'] as $assign): 
                                    $fid = $assign['user_id'];
                                    $type_label = $assign['type_name'] ? $assign['type_name'] : 'General / All';
                                    $badge_color = $assign['type_name'] ? 'bg-blue-50 text-blue-700 border-blue-100' : 'bg-slate-100 text-slate-600 border-slate-200';
                                ?>
                                    <div class="flex items-center justify-between bg-white border border-slate-200 p-2 rounded-lg shadow-sm">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-[10px] font-bold">
                                                <?= isset($faculty_map[$fid]) ? strtoupper(substr($faculty_map[$fid], 0, 1)) : '?' ?>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-sm font-medium text-slate-700"><?= isset($faculty_map[$fid]) ? htmlspecialchars($faculty_map[$fid]) : 'Unknown' ?></span>
                                                <span class="text-[10px] px-1.5 py-0.5 rounded w-fit <?= $badge_color ?>"><?= htmlspecialchars($type_label) ?></span>
                                            </div>
                                        </div>
                                        <form method="POST" onsubmit="return confirm('Remove this assignment?');">
                                            <input type="hidden" name="area_id" value="<?= $area_id ?>">
                                            <input type="hidden" name="user_id" value="<?= $fid ?>">
                                            <input type="hidden" name="type_id" value="<?= $assign['type_id'] ?>">
                                            <button type="submit" name="remove_assignment" class="text-slate-400 hover:text-red-500 transition-colors p-1"><i class="fas fa-times"></i></button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 align-top">
                            <form method="POST" class="space-y-2">
                                <input type="hidden" name="area_id" value="<?= $area_id ?>">
                                <select name="user_id" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                                    <option value="">Select Faculty...</option>
                                    <?php foreach($all_faculty_in_program as $faculty): ?>
                                        <option value="<?= $faculty['user_id'] ?>"><?= htmlspecialchars($faculty['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="type_id" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                                    <option value="0">General / All Documents</option>
                                    <?php foreach($doc_types as $dt): ?>
                                        <option value="<?= $dt['type_id'] ?>"><?= htmlspecialchars($dt['type_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="add_assignment" class="w-full py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors shadow-sm">
                                    <i class="fas fa-plus mr-1"></i> Add
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
      </div>

    <?php elseif ($view === 'performance'): ?>
        <?php if ($selected_program_info): ?>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Area Performance</h1>
                    <p class="text-slate-500 text-sm">Document submission progress for <?= htmlspecialchars($selected_program_info['program_name']) ?>.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($area_performance as $area): 
                    $display_count = $area['doc_count'];
                    $target = 15; // Mock target
                    $percent = $target > 0 ? min(100, round(($display_count / $target) * 100)) : 0;
                    $color = $percent < 30 ? 'bg-red-500' : ($percent < 70 ? 'bg-amber-500' : 'bg-emerald-500');
                ?>
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm transition-all hover:shadow-lg hover:-translate-y-1">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="font-bold text-slate-800">Area <?= $area['area_no'] ?></h3>
                            <p class="text-xs text-slate-500 mb-3 truncate"><?= htmlspecialchars($area['area_title']) ?></p>
                        </div>
                        <span class="text-sm font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full"><?= $display_count ?> / <?= $target ?></span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2.5 mb-3">
                        <div class="<?= $color ?> h-2.5 rounded-full transition-all duration-500" style="width: <?= $percent ?>%"></div>
                    </div>
                    <?php if (!empty($area['assigned_faculty'])): ?>
                        <div class="pt-3 border-t border-slate-100 mt-2">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-slate-400 mb-1.5">Assigned Team (<?= $area['faculty_count'] ?>)</p>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach(explode(', ', $area['assigned_faculty']) as $name): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                        <i class="fas fa-user mr-1.5 opacity-70"></i> <?= htmlspecialchars($name) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($view === 'self_survey'): ?>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Self Survey Results</h1>
                <p class="text-slate-500 text-sm">Average scores from Internal and External accreditors for your department.</p>
            </div>
        </div>

        <?php if (empty($survey_results_data)): ?>
            <div class="bg-white rounded-xl border border-slate-200 p-12 text-center">
                <i class="fas fa-poll text-4xl text-slate-300 mb-4"></i>
                <h3 class="text-lg font-semibold text-slate-700">No Survey Data Found</h3>
                <p class="text-slate-500">No ratings have been submitted for any program in your department yet.</p>
            </div>
        <?php else: ?>
            <div class="space-y-8">
            <?php foreach($survey_results_data as $prog_id => $prog_data): ?>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-200">
                        <h3 class="font-bold text-lg text-indigo-700"><?= htmlspecialchars($prog_data['details']['name']) ?> (<?= htmlspecialchars($prog_data['details']['code']) ?>)</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-100/50 text-slate-600 font-semibold">
                                <tr>
                                    <th class="p-4 w-1/3">Area</th>
                                    <th class="p-4 text-center">Internal Score</th>
                                    <th class="p-4 text-center">External Score</th>
                                    <th class="p-4 text-center">Overall Average</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($all_areas_list_survey as $area_id => $area_info): 
                                    $internal_score = $prog_data['scores'][$area_id]['internal'] ?? null;
                                    $external_score = $prog_data['scores'][$area_id]['external'] ?? null;
                                    
                                    $scores = array_filter([$internal_score, $external_score]);
                                    $overall_avg = !empty($scores) ? array_sum($scores) / count($scores) : null;

                                    $format_score = function($score) {
                                        if ($score === null) return '<span class="text-slate-400">-</span>';
                                        $color = 'text-slate-700';
                                        if ($score < 3) $color = 'text-red-600';
                                        elseif ($score >= 4) $color = 'text-emerald-600';
                                        return '<span class="font-bold text-base ' . $color . '">' . number_format($score, 2) . '</span>';
                                    };
                                ?>
                                <tr class="hover:bg-slate-50/70">
                                    <td class="p-4 font-medium text-slate-800">
                                        <div class="font-bold">Area <?= $area_info['area_no'] ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($area_info['area_title']) ?></div>
                                    </td>
                                    <td class="p-4 text-center"><?= $format_score($internal_score) ?></td>
                                    <td class="p-4 text-center"><?= $format_score($external_score) ?></td>
                                    <td class="p-4 text-center"><?= $format_score($overall_avg) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
  </main>

  <!-- Review Modal -->
  <div id="auditModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-7xl h-[90vh] flex flex-col md:flex-row overflow-hidden">
        
        <!-- Left Side: Document Viewer -->
        <div class="w-full md:w-2/3 bg-slate-100 border-r border-slate-200 flex flex-col relative">
            <div class="p-3 border-b border-slate-200 bg-white flex justify-between items-center shrink-0">
                <h3 class="font-bold text-slate-800 truncate pr-4" id="modalFileName">Review Document</h3>
                <a id="modalDownloadLink" href="#" target="_blank" class="text-sm text-indigo-600 hover:underline shrink-0">
                    <i class="fas fa-external-link-alt"></i> Open in new tab
                </a>
            </div>
            <div class="flex-1 relative w-full h-full">
                <iframe id="docPreview" class="w-full h-full absolute inset-0" src=""></iframe>
            </div>
        </div>

        <!-- Right Side: Feedback Form -->
        <div class="w-full md:w-1/3 bg-white flex flex-col h-full">
            <div class="p-4 border-b border-slate-200 flex justify-between items-center shrink-0">
                <h3 class="font-bold text-slate-800">Chairperson's Review</h3>
                <button onclick="document.getElementById('auditModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="flex-1 p-6 overflow-y-auto">
                <form method="POST" action="" class="flex flex-col h-full">
                    <input type="hidden" name="document_id" id="modalDocId">
                    <input type="hidden" name="submit_feedback" value="1">
                    
                    <div class="mb-4 flex-1 flex flex-col">
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Comments / Feedback</label>
                        <textarea name="feedback_text" required class="w-full p-4 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm resize-none flex-1 min-h-[200px]" placeholder="Enter your feedback for the faculty..."></textarea>
                    </div>
                    
                    <div class="mt-auto pt-4 border-t border-slate-100">
                        <button type="submit" class="btn btn-primary w-full py-3 flex justify-center items-center gap-2">
                            <i class="fas fa-paper-plane"></i> Submit Feedback
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
  </div>

  <script>
    function openAuditModal(docId, filePath, fileName) {
        document.getElementById('docPreview').src = filePath;
        document.getElementById('modalFileName').textContent = 'Review: ' + fileName;
        document.getElementById('modalDocId').value = docId;
        
        const dlLink = document.getElementById('modalDownloadLink');
        if(dlLink) dlLink.href = filePath;
        
        document.getElementById('auditModal').classList.remove('hidden');
    }
  </script>

  <?php if ($show_welcome): ?>
  <!-- Welcome Animation Overlay -->
  <div id="welcomeOverlay" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/80 backdrop-blur-sm transition-opacity duration-700">
    <div class="bg-white p-10 rounded-3xl shadow-2xl text-center transform scale-90 opacity-0 animate-welcome-in max-w-md w-full mx-4 border-4 border-white/50">
        <div class="w-24 h-24 bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-full flex items-center justify-center mx-auto mb-6 text-4xl shadow-lg animate-bounce-slow">
            <i class="fas fa-user-tie"></i>
        </div>
        <h2 class="text-3xl font-bold text-slate-800 mb-2">Welcome Back!</h2>
        <p class="text-xl text-indigo-600 font-medium mb-6"><?= htmlspecialchars($_SESSION['full_name']) ?></p>
        <p class="text-slate-500 text-sm mb-8">Loading Department Dashboard...</p>
        
        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 h-full rounded-full animate-progress-loading"></div>
        </div>
    </div>
  </div>
  <style>
    @keyframes welcome-in { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    @keyframes bounce-slow { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
    @keyframes progress-loading { 0% { width: 0%; } 100% { width: 100%; } }
    .animate-welcome-in { animation: welcome-in 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
    .animate-bounce-slow { animation: bounce-slow 3s infinite ease-in-out; }
    .animate-progress-loading { animation: progress-loading 2s linear forwards; }
  </style>
  <script>
    setTimeout(() => {
        const overlay = document.getElementById('welcomeOverlay');
        overlay.classList.add('opacity-0');
        setTimeout(() => overlay.remove(), 700);
    }, 2200);
  </script>
  <?php endif; ?>

</body>
</html>
