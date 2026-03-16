<?php
session_start();
require_once __DIR__ . '/config/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$role = strtolower(trim($_SESSION['role'] ?? ''));
if (strpos($role, 'dean') === false && strpos($role, 'admin') === false) {
  header('Location: role_home.php');
  exit;
}

// Get current view
$view = isset($_GET['view']) ? $_GET['view'] : 'overview';
$selected_level = isset($_GET['level']) && $_GET['level'] !== '' ? intval($_GET['level']) : null;
$selected_program_info = null;

// Check for welcome message
$show_welcome = false;
if (isset($_SESSION['show_welcome']) && $_SESSION['show_welcome']) {
    $show_welcome = true;
    unset($_SESSION['show_welcome']);
}

// Handle Feedback Submission (Dean's Audit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $doc_id = intval($_POST['document_id']);
    $feedback = trim($_POST['feedback_text']);
    $user_id = $_SESSION['user_id'];
    
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
                $stmt_notif = $conn->prepare("INSERT INTO user_notifications (user_id, doc_id, message) VALUES (?, ?, 'The Dean has left feedback on your document.')");
                if ($stmt_notif) {
                    $stmt_notif->bind_param("ii", $uploader_id, $doc_id);
                    $stmt_notif->execute();
                }
            }
            $stmt->close();
        }
    }
    header("Location: dean_dashboard.php?view=compliance&success=1");
    exit;
}

// Fetch Dean's College ID
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT u.college_id, p.college_id as program_college_id FROM users u 
                        LEFT JOIN programs p ON u.program_id = p.program_id 
                        WHERE u.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
// Use direct college_id if set, otherwise fallback to program's college
$dean_college_id = $user_data ? ($user_data['college_id'] ?? $user_data['program_college_id']) : null;

// Fetch College Code for Styling
$college_code = '';
if ($dean_college_id) {
    $c_res = $conn->query("SELECT college_code FROM colleges WHERE college_id = $dean_college_id");
    if ($c_res && $row = $c_res->fetch_assoc()) {
        $college_code = strtoupper($row['college_code']);
    }
}

// Fetch Programs in this Department
$dept_programs = [];
if ($dean_college_id) {
    $dp_query = $conn->query("SELECT * FROM programs WHERE college_id = $dean_college_id ORDER BY program_name");
    if ($dp_query) {
        while($dp = $dp_query->fetch_assoc()) {
            $dept_programs[] = $dp;
        }
    }
}

// Fetch Overview Stats
$stats = ['programs' => 0, 'faculty' => 0, 'docs' => 0];
if ($dean_college_id) {
    $q1 = $conn->query("SELECT COUNT(*) FROM programs WHERE college_id = $dean_college_id");
    $stats['programs'] = ($q1 && $row = $q1->fetch_row()) ? $row[0] : 0;

    $q2 = $conn->query("SELECT COUNT(*) FROM users u JOIN programs p ON u.program_id = p.program_id JOIN roles r ON u.role_id = r.role_id WHERE p.college_id = $dean_college_id AND (r.role_name LIKE '%Faculty%' OR r.role_name LIKE '%Focal%')");
    $stats['faculty'] = ($q2 && $row = $q2->fetch_row()) ? $row[0] : 0;

    $q3 = $conn->query("SELECT COUNT(*) FROM documents d JOIN cycles c ON d.cycle_id = c.cycle_id JOIN programs p ON c.program_id = p.program_id WHERE p.college_id = $dean_college_id");
    $stats['docs'] = ($q3 && $row = $q3->fetch_row()) ? $row[0] : 0;
}

// Fetch Recent Documents for Overview
$recent_docs_overview = [];
if ($view === 'overview') {
    $sql = "SELECT d.*, p.program_code, a.area_no 
            FROM documents d
            JOIN cycles c ON d.cycle_id = c.cycle_id
            JOIN programs p ON c.program_id = p.program_id
            JOIN areas a ON d.area_id = a.area_id";
    if ($dean_college_id) $sql .= " WHERE p.college_id = $dean_college_id";
    if ($selected_level) {
        $sql .= " AND c.level = $selected_level";
    }
    $sql .= " ORDER BY d.uploaded_at DESC LIMIT 5";
    $res = $conn->query($sql);
    if($res) while($r = $res->fetch_assoc()) $recent_docs_overview[] = $r;
}

// Fetch Upcoming Deadlines (For Overview)
$upcoming_events = [];
if ($view === 'overview' && $dean_college_id) {
    $sql_events = "SELECT p.program_code, c.level, c.submission_deadline, c.survey_date 
                   FROM cycles c 
                   JOIN programs p ON c.program_id = p.program_id 
                   WHERE p.college_id = $dean_college_id AND c.status_id = 1 
                   AND (c.submission_deadline >= CURDATE() OR c.survey_date >= CURDATE())
                   ORDER BY c.submission_deadline ASC LIMIT 3";
    $res_ev = $conn->query($sql_events);
    if($res_ev) while($r = $res_ev->fetch_assoc()) $upcoming_events[] = $r;
}

// Fetch Documents for Compliance Review
$compliance_docs = [];
if ($view === 'compliance') {
    $sql = "SELECT d.*, a.area_title, a.area_no, p.program_code, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) as uploader 
            FROM documents d
            JOIN areas a ON d.area_id = a.area_id
            JOIN cycles c ON d.cycle_id = c.cycle_id
            JOIN programs p ON c.program_id = p.program_id
            LEFT JOIN users u ON d.uploaded_by = u.user_id";
    
    // If Dean is assigned to a college, filter by it. Otherwise show all (or handle as needed)
    if ($dean_college_id) {
        $sql .= " WHERE p.college_id = " . intval($dean_college_id);
    }
    if ($selected_level) {
        $sql .= " AND c.level = $selected_level";
    }
    
    $sql .= " ORDER BY d.uploaded_at DESC LIMIT 50";
    $result = $conn->query($sql);
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $compliance_docs[] = $row;
        }
    }
}

// Fetch Feedback / Audit Logs
$feedbacks = [];
if ($view === 'feedback') {
    $sql = "SELECT f.*, d.file_name, d.file_path, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) as commenter, p.program_code 
            FROM document_feedback f
            JOIN documents d ON f.doc_id = d.doc_id
            JOIN cycles c ON d.cycle_id = c.cycle_id
            JOIN programs p ON c.program_id = p.program_id
            JOIN users u ON f.user_id = u.user_id";
    
    if ($dean_college_id) {
        $sql .= " WHERE p.college_id = " . intval($dean_college_id);
    }
    $sql .= " ORDER BY f.created_at DESC LIMIT 50";
    
    try {
        $result = $conn->query($sql);
        while($row = $result->fetch_assoc()) $feedbacks[] = $row;
    } catch (Exception $e) {
        $db_error = $e->getMessage();
    }
}

// Fetch Program & Area Performance Data
$area_performance = [];
if ($view === 'programs') {
    $program_id_filter = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;

    if ($program_id_filter && $dean_college_id) {
        // A program is selected, show area breakdown for it.
        
        // Get program details to verify it belongs to this dean's college
        $prog_stmt = $conn->prepare("SELECT * FROM programs WHERE program_id = ? AND college_id = ?");
        $prog_stmt->bind_param("ii", $program_id_filter, $dean_college_id);
        $prog_stmt->execute();
        $selected_program_info = $prog_stmt->get_result()->fetch_assoc();

        // If program is valid and found, fetch its area performance
        if ($selected_program_info) {
            $sql = "SELECT a.area_id, a.area_no, a.area_title, 
                    (SELECT COUNT(*) FROM documents d 
                     JOIN cycles c ON d.cycle_id = c.cycle_id 
                     WHERE d.area_id = a.area_id AND c.program_id = ?) as doc_count
                    FROM areas a ORDER BY a.area_no ASC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $program_id_filter);
            $stmt->execute();
            $res = $stmt->get_result();
            if($res) while($r = $res->fetch_assoc()) $area_performance[] = $r;
        }
    }
}


// Fetch Survey Results Data
$survey_results_data = [];
$all_areas_list = [];
if ($view === 'self_survey' && $dean_college_id) {
    // Fetch all areas for the table structure
    $a_res = $conn->query("SELECT * FROM areas ORDER BY area_no");
    if ($a_res) while($row = $a_res->fetch_assoc()) $all_areas_list[$row['area_id']] = $row;

    // The main query to get all ratings for the dean's college
    $sql_survey = "SELECT
                p.program_id, p.program_code, p.program_name,
                r.area_id,
                r.accreditor_type,
                AVG(r.rating) as average_rating
            FROM survey_ratings r
            JOIN programs p ON r.program_id = p.program_id
            WHERE p.college_id = ?
            GROUP BY p.program_id, p.program_code, p.program_name, r.area_id, r.accreditor_type
            ORDER BY p.program_name, r.area_id";
    
    $stmt_survey = $conn->prepare($sql_survey);
    $stmt_survey->bind_param("i", $dean_college_id);
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
  <title>Dean Dashboard</title>
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

    // Table Theme Defaults
    $table_thead_bg = 'bg-slate-50';
    $table_thead_text = 'text-slate-600';
    $table_hover_row = 'hover:bg-slate-50';

    if ($college_code === 'COS') { // College of Science (Green)
        $sidebar_bg = '#f0fdf4'; // Green-50
        $sidebar_border = '#bbf7d0'; // Green-200
        $nav_hover_bg = '#dcfce7'; // Green-100
        $nav_hover_text = '#15803d'; // Green-700
        $nav_active_bg = 'linear-gradient(135deg, #16a34a, #15803d)'; // Green-600 -> Green-700
        
        $topbar_bg = 'linear-gradient(135deg, #22c55e, #15803d)'; // Green-500 -> Green-700
        $logout_hover_bg = '#14532d'; // Green-900
        
        $table_thead_bg = 'bg-green-50';
        $table_thead_text = 'text-green-700';
        $table_hover_row = 'hover:bg-green-50';
    } elseif ($college_code === 'CTE') { // College of Teacher Ed (Red/Rose)
        $sidebar_bg = '#fff1f2'; // Rose-50
        $sidebar_border = '#fecdd3'; // Rose-200
        $nav_hover_bg = '#ffe4e6'; // Rose-100
        $nav_hover_text = '#be123c'; // Rose-700
        $nav_active_bg = 'linear-gradient(135deg, #e11d48, #be123c)';
        
        $topbar_bg = 'linear-gradient(135deg, #f43f5e, #be123c)'; // Rose-500 -> Rose-700
        $logout_hover_bg = '#881337'; // Rose-900
        
        $table_thead_bg = 'bg-rose-50';
        $table_thead_text = 'text-rose-700';
        $table_hover_row = 'hover:bg-rose-50';
    } elseif ($college_code === 'CBM') { // Business & Management (Amber/Orange)
        $sidebar_bg = '#fffbeb'; // Amber-50
        $sidebar_border = '#fde68a'; // Amber-200
        $nav_hover_bg = '#fef3c7'; // Amber-100
        $nav_hover_text = '#b45309'; // Amber-700
        $nav_active_bg = 'linear-gradient(135deg, #d97706, #b45309)';
        
        $topbar_bg = 'linear-gradient(135deg, #f59e0b, #b45309)'; // Amber-500 -> Amber-700
        $logout_hover_bg = '#78350f'; // Amber-900
        
        $table_thead_bg = 'bg-amber-50';
        $table_thead_text = 'text-amber-700';
        $table_hover_row = 'hover:bg-amber-50';
    } elseif ($college_code === 'CFMS') { // College of Fisheries (Blue)
        $sidebar_bg = '#eff6ff'; // Blue-50
        $sidebar_border = '#bfdbfe'; // Blue-200
        $nav_hover_bg = '#dbeafe'; // Blue-100
        $nav_hover_text = '#1d4ed8'; // Blue-700
        $nav_active_bg = 'linear-gradient(135deg, #2563eb, #1e40af)'; // Blue-600 -> Blue-800
        
        $topbar_bg = 'linear-gradient(135deg, #3b82f6, #1d4ed8)'; // Blue-500 -> Blue-700
        $logout_hover_bg = '#1e3a8a'; // Blue-900
        
        $table_thead_bg = 'bg-blue-50';
        $table_thead_text = 'text-blue-700';
        $table_hover_row = 'hover:bg-blue-50';
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
        <i class="fas fa-shield-alt"></i> <span>Dean Dashboard</span>
      </div>
    </div>
    <div class="right">
      <div class="user-profile">
        <div class="user-info hidden sm:block">
          <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Dean'); ?></span>
          <span class="user-role">Dean / Program Head</span>
        </div>
        <?php if (!empty($_SESSION['avatar_path']) && file_exists($_SESSION['avatar_path'])): ?>
            <img src="<?= htmlspecialchars($_SESSION['avatar_path']) ?>" alt="Avatar" class="w-8 h-8 rounded-full object-cover">
        <?php else: ?>
            <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-sm"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'D', 0, 1)); ?></div>
        <?php endif; ?>
      </div>
      <div class="divider-vertical"></div>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <aside class="sidebar">
    <nav class="sidebar-nav">
      <a href="?view=overview" class="nav-item <?php echo $view === 'overview' ? 'active' : ''; ?>">
        <i class="fas fa-chart-pie"></i> Overview
      </a>
      <a href="?view=compliance" class="nav-item <?php echo $view === 'compliance' ? 'active' : ''; ?>">
        <i class="fas fa-clipboard-check"></i> Compliance Review
      </a>
      <a href="?view=programs" class="nav-item <?php echo $view === 'programs' ? 'active' : ''; ?>">
        <i class="fas fa-stream"></i> Programs
      </a>
      <a href="?view=feedback" class="nav-item <?php echo $view === 'feedback' ? 'active' : ''; ?>">
        <i class="fas fa-comments"></i> Feedback / Audit
      </a>
      <a href="?view=self_survey" class="nav-item <?php echo $view === 'self_survey' ? 'active' : ''; ?>">
        <i class="fas fa-tasks"></i> Self Survey
      </a>
    </nav>
    <div class="mt-auto p-4">
        <a href="profile.php" class="nav-item"><i class="fas fa-user-cog"></i> Manage Profile</a>
    </div>
  </aside>

  <main class="main-content">
    <?php if ($view === 'overview'): ?>
      <section class="cards">
        <div class="card"><div class="card-title">Programs in College</div><div class="card-value"><?= $stats['programs'] ?></div></div>
        <div class="card"><div class="card-title">Active Faculty / Focal</div><div class="card-value"><?= $stats['faculty'] ?></div></div>
        <div class="card"><div class="card-title">Total Documents</div><div class="card-value"><?= $stats['docs'] ?></div></div>
      </section>

      <?php if (!empty($upcoming_events)): ?>
      <section class="mb-8">
        <h2 class="text-lg font-bold text-slate-800 mb-4">📅 Upcoming Accreditation Events</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach($upcoming_events as $event): 
                $deadline = $event['submission_deadline'] ? date('M d, Y', strtotime($event['submission_deadline'])) : 'Not set';
                $visit = $event['survey_date'] ? date('M d, Y', strtotime($event['survey_date'])) : 'Not set';
                
                // Calculate days remaining for deadline
                $days_left = '-';
                if ($event['submission_deadline']) {
                    $diff = (strtotime($event['submission_deadline']) - time()) / (60 * 60 * 24);
                    $days_left = ceil($diff);
                }
                $alert_color = ($days_left !== '-' && $days_left <= 7) ? 'bg-red-50 border-red-200' : 'bg-white border-slate-200';
            ?>
            <div class="p-4 rounded-xl border shadow-sm <?= $alert_color ?>">
                <div class="flex justify-between items-start mb-2">
                    <span class="font-bold text-indigo-700 bg-indigo-50 px-2 py-1 rounded text-xs"><?= htmlspecialchars($event['program_code']) ?></span>
                    <span class="text-xs font-semibold text-slate-500">Level <?= $event['level'] ?></span>
                </div>
                <div class="space-y-1">
                    <div class="text-sm text-slate-700"><strong>Deadline:</strong> <span class="<?= ($days_left <= 7) ? 'text-red-600 font-bold' : '' ?>"><?= $deadline ?></span></div>
                    <div class="text-sm text-slate-700"><strong>Visit:</strong> <?= $visit ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <section class="panel">
        <h2>Recent Incoming Documents</h2>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <table class="w-full text-left text-sm">
            <thead class="<?= $table_thead_bg ?> <?= $table_thead_text ?> font-semibold border-b border-slate-200">
                <tr><th class="p-4">File</th><th class="p-4">Program</th><th class="p-4">Area</th><th class="p-4">Date</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach($recent_docs_overview as $doc): ?>
                <tr class="<?= $table_hover_row ?>">
                    <td class="p-4 font-medium text-indigo-600"><a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank"><?= htmlspecialchars($doc['file_name']) ?></a></td>
                    <td class="p-4"><span class="bg-blue-50 text-blue-700 px-2 py-1 rounded text-xs font-bold"><?= htmlspecialchars($doc['program_code']) ?></span></td>
                    <td class="p-4">Area <?= htmlspecialchars($doc['area_no']) ?></td>
                    <td class="p-4 text-slate-500"><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($recent_docs_overview)) echo "<tr><td colspan='4' class='p-4 text-center text-slate-500'>No recent documents found.</td></tr>"; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php elseif ($view === 'compliance'): ?>
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Compliance Review</h1>
        <div class="flex gap-2">
            <form method="GET" class="flex items-center">
                <input type="hidden" name="view" value="compliance">
                <select name="level" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value="">All Levels</option>
                    <option value="1" <?= ($selected_level == 1) ? 'selected' : '' ?>>Level 1</option>
                    <option value="2" <?= ($selected_level == 2) ? 'selected' : '' ?>>Level 2</option>
                    <option value="3" <?= ($selected_level == 3) ? 'selected' : '' ?>>Level 3</option>
                    <option value="4" <?= ($selected_level == 4) ? 'selected' : '' ?>>Level 4</option>
                </select>
            </form>
            <button class="btn btn-primary"><i class="fas fa-download"></i> Export</button>
        </div>
      </div>
      
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="<?= $table_thead_bg ?> <?= $table_thead_text ?> font-semibold border-b border-slate-200">
              <tr>
                <th class="p-4">Document</th>
                <th class="p-4">Program</th>
                <th class="p-4">Area</th>
                <th class="p-4">Uploaded By</th>
                <th class="p-4">Date</th>
                <th class="p-4 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php if (empty($compliance_docs)): ?>
                <tr><td colspan="6" class="p-6 text-center text-slate-500">No documents found for review.</td></tr>
              <?php else: ?>
                <?php foreach ($compliance_docs as $doc): ?>
                  <tr class="<?= $table_hover_row ?> transition-colors">
                    <td class="p-4 font-medium text-slate-800">
                      <div class="flex items-center gap-3">
                        <i class="fas fa-file-pdf text-red-500 text-lg"></i>
                        <?= htmlspecialchars($doc['file_name']) ?>
                      </div>
                    </td>
                    <td class="p-4"><span class="px-2 py-1 bg-blue-50 text-blue-700 rounded text-xs font-semibold"><?= htmlspecialchars($doc['program_code']) ?></span></td>
                    <td class="p-4 text-slate-600">Area <?= htmlspecialchars($doc['area_no']) ?></td>
                    <td class="p-4 text-slate-600"><?= htmlspecialchars($doc['uploader'] ?? 'Unknown') ?></td>
                    <td class="p-4 text-slate-500"><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
                    <td class="p-4 text-right">
                      <button onclick="openAuditModal(<?= $doc['doc_id'] ?>, '<?= htmlspecialchars($doc['file_path']) ?>', '<?= htmlspecialchars($doc['file_name']) ?>')" class="btn btn-ghost small text-indigo-600 hover:text-indigo-800">
                        <i class="fas fa-search"></i> Audit / Review
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php elseif ($view === 'feedback'): ?>
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Feedback & Audit Trail</h1>
        <p class="text-slate-500 text-sm">Recent comments and reviews from accreditors on your documents.</p>
      </div>

      <?php if (isset($db_error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Database Error:</strong> <span class="block sm:inline"><?= htmlspecialchars($db_error) ?></span>
        </div>
      <?php endif; ?>

      <div class="space-y-4">
        <?php if (empty($feedbacks)): ?>
            <div class="p-8 text-center bg-white rounded-xl border border-slate-200">
                <i class="far fa-comments text-4xl text-slate-300 mb-3"></i>
                <p class="text-slate-500">No feedback records found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($feedbacks as $fb): ?>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex gap-4">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold">
                        <?= strtoupper(substr($fb['commenter'], 0, 1)) ?>
                    </div>
                </div>
                <div class="flex-grow">
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="font-semibold text-slate-800"><?= htmlspecialchars($fb['commenter']) ?></h4>
                            <p class="text-xs text-slate-500">commented on <span class="font-medium text-indigo-600"><?= htmlspecialchars($fb['file_name']) ?></span> (<?= htmlspecialchars($fb['program_code']) ?>)</p>
                        </div>
                        <span class="text-xs text-slate-400"><?= date('M d, Y h:i A', strtotime($fb['created_at'])) ?></span>
                    </div>
                    <div class="mt-2 text-slate-600 text-sm bg-slate-50 p-3 rounded-lg border border-slate-100">
                        <?= nl2br(htmlspecialchars($fb['feedback_text'])) ?>
                    </div>
                    <div class="mt-2">
                        <a href="<?= htmlspecialchars($fb['file_path']) ?>" target="_blank" class="text-xs font-medium text-indigo-600 hover:underline">
                            <i class="fas fa-external-link-alt"></i> View Document
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
      </div>

    <?php elseif ($view === 'programs'): ?>
        <?php 
        $program_id_filter = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;
        ?>

        <?php if ($program_id_filter && $selected_program_info): // A specific program is selected ?>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Area Performance: <?= htmlspecialchars($selected_program_info['program_code']) ?></h1>
                    <p class="text-slate-500 text-sm">Document submission progress for <?= htmlspecialchars($selected_program_info['program_name']) ?>.</p>
                </div>
                <a href="?view=programs" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors shadow-sm">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Programs
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($area_performance as $area): 
                    $display_count = $area['doc_count'];
                    $target = 15; // Mock target, can be made dynamic later
                    $percent = $target > 0 ? min(100, round(($display_count / $target) * 100)) : 0;
                    $color = $percent < 30 ? 'bg-red-500' : ($percent < 70 ? 'bg-amber-500' : 'bg-emerald-500');
                ?>
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm transition-all hover:shadow-lg hover:-translate-y-1">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="font-bold text-slate-800">Area <?= $area['area_no'] ?></h3>
                            <p class="text-xs text-slate-500 mb-3 truncate"><?= htmlspecialchars($area['area_title']) ?></p>
                        </div>
                        <span class="text-sm font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full"><?= $display_count ?> docs</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2.5">
                        <div class="<?= $color ?> h-2.5 rounded-full transition-all duration-500" style="width: <?= $percent ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php else: // No program selected, show list of programs ?>
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-800">Programs</h1>
                <p class="text-slate-500 text-sm">Select a program to view its area performance.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (empty($dept_programs)): ?>
                    <div class="col-span-full bg-white rounded-xl border border-slate-200 p-12 text-center">
                        <i class="fas fa-folder-open text-4xl text-slate-300 mb-4"></i>
                        <h3 class="text-lg font-semibold text-slate-700">No Programs Found</h3>
                        <p class="text-slate-500">There are no programs assigned to your department yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($dept_programs as $program): ?>
                        <a href="?view=programs&program_id=<?= $program['program_id'] ?>" class="group block bg-white p-6 rounded-xl border border-slate-200 shadow-sm transition-all hover:shadow-lg hover:-translate-y-1 hover:border-indigo-400">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-lg">
                                        <i class="fas fa-folder"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-800 group-hover:text-indigo-600 transition-colors"><?= htmlspecialchars($program['program_code']) ?></h3>
                                        <p class="text-xs text-slate-500 line-clamp-2"><?= htmlspecialchars($program['program_name']) ?></p>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right text-slate-300 group-hover:text-indigo-500 transition-colors"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($view === 'self_survey'): ?>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Self Survey Results</h1>
                <p class="text-slate-500 text-sm">Average scores from Internal and External accreditors for your college.</p>
            </div>
        </div>

        <?php if (empty($survey_results_data)): ?>
            <div class="bg-white rounded-xl border border-slate-200 p-12 text-center">
                <i class="fas fa-poll text-4xl text-slate-300 mb-4"></i>
                <h3 class="text-lg font-semibold text-slate-700">No Survey Data Found</h3>
                <p class="text-slate-500">No ratings have been submitted for any program in this college yet.</p>
            </div>
        <?php else: ?>
            <div class="space-y-8">
            <?php foreach($survey_results_data as $prog_id => $prog_data): ?>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-indigo-700"><?= htmlspecialchars($prog_data['details']['name']) ?> (<?= htmlspecialchars($prog_data['details']['code']) ?>)</h3>
                        <a href="print_report.php?program_id=<?= $prog_id ?>" target="_blank" class="text-sm bg-white border border-slate-300 text-slate-700 px-3 py-1.5 rounded-lg hover:bg-slate-50 transition-colors shadow-sm">
                            <i class="fas fa-file-alt mr-1"></i> View Full Report
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="<?= $table_thead_bg ?> <?= $table_thead_text ?> font-semibold">
                                <tr>
                                    <th class="p-4 w-1/3">Area</th>
                                    <th class="p-4 text-center">Internal Score</th>
                                    <th class="p-4 text-center">External Score</th>
                                    <th class="p-4 text-center">Overall Average</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($all_areas_list as $area_id => $area_info): 
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
                                <tr class="<?= $table_hover_row ?>">
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

  <!-- Audit / Feedback Modal -->
  <div id="auditModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-7xl h-[90vh] flex flex-col md:flex-row overflow-hidden">
        
        <!-- Left Side: Document Viewer -->
        <div class="w-full md:w-2/3 bg-slate-100 border-r border-slate-200 flex flex-col relative">
            <div class="p-3 border-b border-slate-200 bg-white flex justify-between items-center shrink-0">
                <h3 class="font-bold text-slate-800 truncate pr-4" id="modalFileName">Audit Document</h3>
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
                <h3 class="font-bold text-slate-800">Audit Findings</h3>
                <button onclick="document.getElementById('auditModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="flex-1 p-6 overflow-y-auto">
                <form method="POST" action="" class="flex flex-col h-full">
                    <input type="hidden" name="document_id" id="modalDocId">
                    <input type="hidden" name="submit_feedback" value="1">
                    
                    <div class="mb-4 flex-1 flex flex-col">
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Dean's Remarks / Feedback</label>
                        <textarea name="feedback_text" required class="w-full p-4 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm resize-none flex-1 min-h-[200px]" placeholder="Enter your audit findings, compliance gaps, or approval notes here..."></textarea>
                    </div>
                    
                    <div class="mt-auto pt-4 border-t border-slate-100">
                        <button type="submit" class="btn btn-primary w-full py-3 flex justify-center items-center gap-2">
                            <i class="fas fa-paper-plane"></i> Submit Findings
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
        document.getElementById('modalFileName').textContent = 'Audit: ' + fileName;
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
            <i class="fas fa-user-graduate"></i>
        </div>
        <h2 class="text-3xl font-bold text-slate-800 mb-2">Welcome Back!</h2>
        <p class="text-xl text-indigo-600 font-medium mb-6"><?= htmlspecialchars($_SESSION['full_name']) ?></p>
        <p class="text-slate-500 text-sm mb-8">Loading Dean's Dashboard...</p>
        
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
