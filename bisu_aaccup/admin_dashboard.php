<?php
session_start();

// Require DB connection if present (optional for dynamic counts)
require_once __DIR__ . '/config/db.php';

if (!isset($conn)) {
    die("Fatal Error: Database connection not established. Please check config/db.php");
}

// Check for welcome message
$show_welcome = false;
if (isset($_SESSION['show_welcome']) && $_SESSION['show_welcome']) {
    $show_welcome = true;
    unset($_SESSION['show_welcome']);
}

// Protect page for Admin role only
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header('Location: login.php');
    exit;
}

$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');
$role = htmlspecialchars($_SESSION['role'] ?? 'Admin');
$view = $_GET['view'] ?? 'dashboard';

// Handle Document Deletion
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    // First get file path to delete from folder
    $q = $conn->query("SELECT file_path FROM documents WHERE doc_id = $del_id");
    if ($q && $row = $q->fetch_assoc()) {
        $file_path = $row['file_path'];
        // SECURITY: Prevent path traversal. Ensure file is within the uploads directory.
        $base_dir = realpath(__DIR__ . '/uploads');
        $file_realpath = realpath($file_path);
        if ($file_realpath && strpos($file_realpath, $base_dir) === 0 && file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Delete from database
    $conn->query("DELETE FROM documents WHERE doc_id = $del_id");
    // Redirect to remove query param
    header("Location: admin_dashboard.php?view=dashboard"); 
    exit;
}

// Handle Document Approval
if (isset($_GET['approve_id'])) {
    $approve_id = intval($_GET['approve_id']);
    $conn->query("UPDATE documents SET status = 'approved' WHERE doc_id = $approve_id");
    header("Location: admin_dashboard.php?view=dashboard&msg=approved");
    exit;
}

// Handle Feedback Submission (Admin's Review)
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
                $stmt_notif = $conn->prepare("INSERT INTO user_notifications (user_id, doc_id, message) VALUES (?, ?, 'Admin has reviewed your document.')");
                if ($stmt_notif) {
                    $stmt_notif->bind_param("ii", $uploader_id, $doc_id);
                    $stmt_notif->execute();
                }
            }
            $stmt->close();
        }
    }
    // Redirect to show success/avoid resubmission
    header("Location: admin_dashboard.php?view=dashboard&feedback_success=1");
    exit;
}

// Fetch Recent Uploads for Dashboard View
$colleges = $conn->query("SELECT * FROM colleges ORDER BY college_name");
$areas = $conn->query("SELECT * FROM areas ORDER BY area_no");

$recent_uploads = [];
$pending_files = [];
$total_docs = 0;
$pending_count = 0;

if ($view === 'dashboard') {
    $college_filter = isset($_GET['college_id']) ? intval($_GET['college_id']) : 0;
    $area_filter = isset($_GET['area_id']) ? intval($_GET['area_id']) : 0;
    $search_q = isset($_GET['q']) ? trim($_GET['q']) : '';

    // Refactored to use prepared statements for security and consistency
    $sql = "SELECT d.*, p.program_code, a.area_no, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) as uploader 
            FROM documents d
            LEFT JOIN cycles c ON d.cycle_id = c.cycle_id
            LEFT JOIN programs p ON c.program_id = p.program_id
            LEFT JOIN areas a ON d.area_id = a.area_id
            LEFT JOIN users u ON d.uploaded_by = u.user_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($college_filter) { $sql .= " AND p.college_id = ?"; $params[] = $college_filter; $types .= "i"; }
    if ($area_filter) { $sql .= " AND d.area_id = ?"; $params[] = $area_filter; $types .= "i"; }
    if ($search_q) {
        $sql .= " AND (d.file_name LIKE ? OR p.program_code LIKE ?)";
        $search_param = "%$search_q%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }

    $sql .= " ORDER BY d.uploaded_at DESC LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) while($row = $result->fetch_assoc()) $recent_uploads[] = $row;
    }

    // Fetch counts in a single query for efficiency
    $counts_sql = "SELECT 
        COUNT(d.doc_id) as total,
        COUNT(CASE WHEN d.status = 'pending' THEN 1 ELSE NULL END) as pending
        FROM documents d
        LEFT JOIN cycles c ON d.cycle_id = c.cycle_id";
    $counts_res = $conn->query($counts_sql);
    if ($counts_res && $counts_row = $counts_res->fetch_assoc()) {
        $total_docs = $counts_row['total'];
        $pending_count = $counts_row['pending'];
    }

    // Get Pending Files List (Documents pending approval)
    $sql_pending_list = "SELECT d.doc_id, d.file_name, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) as full_name, p.program_code 
                         FROM documents d 
                         LEFT JOIN users u ON d.uploaded_by = u.user_id 
                         JOIN cycles c ON d.cycle_id = c.cycle_id 
                         JOIN programs p ON c.program_id = p.program_id 
                         WHERE d.status = 'pending'
                         ORDER BY d.uploaded_at DESC LIMIT 5";
    $res_pending = $conn->query($sql_pending_list);
    if ($res_pending) while($row = $res_pending->fetch_assoc()) $pending_files[] = $row;

    // Data for Charts
    $chart_colleges = [];
    $res_c = $conn->query("SELECT c.college_code, COUNT(d.doc_id) as count FROM colleges c LEFT JOIN programs p ON c.college_id = p.college_id LEFT JOIN cycles cy ON p.program_id = cy.program_id LEFT JOIN documents d ON cy.cycle_id = d.cycle_id GROUP BY c.college_id");
    if($res_c) while($r = $res_c->fetch_assoc()) $chart_colleges[] = $r;

    $chart_areas = [];
    $res_a = $conn->query("SELECT a.area_no, COUNT(d.doc_id) as count FROM areas a LEFT JOIN documents d ON a.area_id = d.area_id GROUP BY a.area_id ORDER BY a.area_no");
    if($res_a) while($r = $res_a->fetch_assoc()) $chart_areas[] = $r;
}

$survey_results_data = [];
$all_colleges_list = [];
$selected_college_data = null;
$all_areas_list = [];

if ($view === 'self_survey') {
    $college_filter = isset($_GET['college_id']) ? intval($_GET['college_id']) : 0;

    if (!$college_filter) {
        // Fetch all colleges to display for selection
        $c_res = $conn->query("SELECT * FROM colleges ORDER BY college_name");
        if ($c_res) while($row = $c_res->fetch_assoc()) $all_colleges_list[] = $row;
    } else {
        // A college is selected, fetch detailed survey data
        $selected_college_data = $conn->query("SELECT * FROM colleges WHERE college_id = $college_filter")->fetch_assoc();

        // Fetch all areas for the table structure
        $a_res = $conn->query("SELECT * FROM areas ORDER BY area_no");
        if ($a_res) while($row = $a_res->fetch_assoc()) $all_areas_list[$row['area_id']] = $row;

        // The main query to get all ratings for the college
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
        
        $stmt = $conn->prepare($sql_survey);
        $stmt->bind_param("i", $college_filter);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $survey_results_data[$row['program_id']]['details'] = ['name' => $row['program_name'], 'code' => $row['program_code']];
                $survey_results_data[$row['program_id']]['scores'][$row['area_id']][$row['accreditor_type']] = $row['average_rating'];
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard — BISU Accreditation</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: { colors: { bisu: '#4f46e5' } } } }
  </script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
  <link rel="stylesheet" href="css/admin-dashboard.css">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">

  <!-- Fixed Topbar -->
  <header class="topbar">
    <div class="left">
      <button class="menu-toggle lg:hidden text-slate-600 hover:text-indigo-600 mr-3" onclick="document.querySelector('.sidebar').classList.toggle('show')">
        <i class="fas fa-bars fa-lg"></i>
      </button>
      <a href="admin_dashboard.php" class="brand">
        <i class="fas fa-shield-alt"></i> <span>BISU Accreditation</span>
      </a>
    </div>
    <div class="right">
      <div class="user-profile">
        <div class="user-info hidden sm:block">
          <span class="user-name"><?php echo $full_name; ?></span>
          <span class="user-role"><?php echo $role; ?></span>
        </div>
        <?php if (!empty($_SESSION['avatar_path']) && file_exists($_SESSION['avatar_path'])): ?>
            <img src="<?= htmlspecialchars($_SESSION['avatar_path']) ?>" alt="Avatar" class="user-avatar" style="object-fit: cover;">
        <?php else: ?>
            <div class="user-avatar">
              <?php echo strtoupper(substr($full_name, 0, 1)); ?>
            </div>
        <?php endif; ?>
      </div>
      <div class="divider-vertical"></div>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <!-- Fixed Sidebar -->
  <aside class="sidebar">
    <nav class="sidebar-nav">
        <a href="admin_dashboard.php?view=dashboard" class="nav-item <?= $view === 'dashboard' ? 'active' : '' ?>">
          <i class="fas fa-th-large"></i>
          Dashboard
        </a>
        <a href="users.php" class="nav-item">
          <i class="fas fa-users"></i>
          Manage Users
        </a>
        <a href="colleges.php" class="nav-item">
          <i class="fas fa-university"></i>
          Manage Colleges
        </a>
        <a href="admin_cycles.php" class="nav-item">
          <i class="fas fa-sync-alt"></i>
          Manage Levels
        </a>
        <a href="documents.php" class="nav-item">
          <i class="fas fa-file-alt"></i>
          Documents
        </a>
        <a href="reports.php" class="nav-item">
          <i class="fas fa-chart-bar"></i>
          Reports / Logs
        </a>
        <a href="admin_dashboard.php?view=self_survey" class="nav-item <?= $view === 'self_survey' ? 'active' : '' ?>">
          <i class="fas fa-tasks"></i>
          Self Survey
        </a>
    </nav>
    <div class="mt-auto p-4">
        <a href="profile.php" class="nav-item"><i class="fas fa-user-cog"></i> Manage Profile</a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
      <?php if (isset($_GET['msg']) && $_GET['msg'] == 'approved'): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4">✅ Document approved successfully!</div>
      <?php endif; ?>
      <?php if (isset($_GET['feedback_success'])): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4">✅ Feedback submitted successfully!</div>
      <?php endif; ?>
      <?php if ($view === 'dashboard'): ?>
      <!-- Top Bar -->
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
          <div class="text-2xl font-semibold text-bisu">BISU Accreditation</div>
        </div>
      </div>

      <!-- Toolbar: search + filters + actions -->
      <form method="GET" class="mb-4 flex flex-col md:flex-row gap-3 items-center justify-between">
        <input type="hidden" name="view" value="dashboard">
        <div class="flex items-center gap-3 w-full md:w-2/3">
          <div class="relative w-full">
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400"><i class="fas fa-search"></i></span>
            <input name="q" value="<?= htmlspecialchars($search_q ?? '') ?>" type="text" placeholder="Search uploads..." class="pl-10 pr-4 py-2 w-full rounded-md border border-slate-200 bg-white text-sm" />
          </div>

          <select name="college_id" class="px-3 py-2 rounded-md border border-slate-200 text-sm" onchange="this.form.submit()">
            <option value="">All Colleges</option>
            <?php if($colleges) while($c = $colleges->fetch_assoc()): ?>
                <option value="<?= $c['college_id'] ?>" <?= ($college_filter ?? 0) == $c['college_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['college_name']) ?></option>
            <?php endwhile; ?>
          </select>

          <select name="area_id" class="px-3 py-2 rounded-md border border-slate-200 text-sm" onchange="this.form.submit()">
            <option value="">All Areas</option>
            <?php if($areas) while($a = $areas->fetch_assoc()): ?>
                <option value="<?= $a['area_id'] ?>" <?= ($area_filter ?? 0) == $a['area_id'] ? 'selected' : '' ?>>
                    Area <?= $a['area_no'] ?>: <?= htmlspecialchars($a['area_title']) ?>
                </option>
            <?php endwhile; ?>
          </select>
        </div>
      </form>

      <!-- Main dashboard cards -->
      <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 flex items-center gap-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-1 cursor-default">
          <div class="p-3 bg-indigo-50 text-indigo-600 rounded-lg"><i class="fas fa-file-alt fa-lg"></i></div>
          <div>
            <div class="text-sm text-slate-500">Total Documents</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900"><?= number_format($total_docs) ?></div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 flex items-center gap-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-1 cursor-default">
          <div class="p-3 bg-amber-50 text-amber-600 rounded-lg"><i class="fas fa-hourglass-half fa-lg"></i></div>
          <div>
            <div class="text-sm text-slate-500">Pending / Draft</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900"><?= number_format($pending_count) ?></div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 flex flex-col transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
          <div class="flex items-center justify-between">
            <div class="text-sm text-slate-500">Documents per College</div>
            <div class="text-xs text-slate-400">Monthly</div>
          </div>
          <div class="mt-4 h-32 relative"><canvas id="chartCollege"></canvas></div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 flex flex-col transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
          <div class="flex items-center justify-between">
            <div class="text-sm text-slate-500">Documents per Area</div>
            <div class="text-xs text-slate-400">Monthly</div>
          </div>
          <div class="mt-4 h-32 relative"><canvas id="chartArea"></canvas></div>
        </div>
      </section>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 lg:col-span-2 transition-all duration-300 hover:shadow-md">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-slate-900">Recent Uploads</h3>
            <div class="flex items-center gap-2">
              <a href="admin_dashboard.php" class="px-3 py-2 text-sm border border-slate-200 rounded-md hover:bg-slate-50">Clear Filters</a>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table id="recentUploadsTable" class="w-full text-left text-sm">
              <thead>
                <tr class="text-slate-600">
                  <th class="py-2">File</th>
                  <th class="py-2">College</th>
                  <th class="py-2">Area</th>
                  <th class="py-2">Uploader</th>
                  <th class="py-2">Status</th>
                  <th class="py-2">Actions</th>
                </tr>
              </thead>
              <tbody class="text-slate-700">
                <?php if (empty($recent_uploads)): ?>
                    <tr><td colspan="6" class="py-4 text-center text-slate-500">No documents uploaded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_uploads as $doc): ?>
                    <tr class="border-t hover:bg-indigo-50/30 transition-colors duration-200">
                      <td class="py-3 font-medium">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-file-pdf text-red-500"></i> <?= htmlspecialchars($doc['file_name']) ?>
                        </div>
                      </td>
                      <td class="py-3"><span class="bg-blue-50 text-blue-700 px-2 py-1 rounded text-xs font-bold"><?= htmlspecialchars($doc['program_code']) ?></span></td>
                      <td class="py-3">Area <?= htmlspecialchars($doc['area_no']) ?></td>
                      <td class="py-3 text-sm text-slate-600"><?= htmlspecialchars($doc['uploader']) ?></td>
                      <td class="py-3">
                        <?php if(($doc['status'] ?? 'pending') === 'approved'): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-emerald-50 text-emerald-700">Approved</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-amber-50 text-amber-700">Pending</span>
                        <?php endif; ?>
                      </td>
                      <td class="py-3">
                        <?php if(($doc['status'] ?? 'pending') !== 'approved'): ?>
                            <a href="?view=dashboard&approve_id=<?= $doc['doc_id'] ?>" class="text-emerald-600 hover:text-emerald-800 font-medium text-xs border border-emerald-200 bg-emerald-50 px-3 py-1.5 rounded-lg transition-colors mr-2" title="Approve Document">
                                <i class="fas fa-check"></i> Approve
                            </a>
                        <?php endif; ?>
                        <button onclick='openAuditModal(<?= $doc['doc_id'] ?>, <?= json_encode($doc['file_path']) ?>, <?= json_encode($doc['file_name']) ?>)' class="text-indigo-600 hover:text-indigo-800 font-medium text-xs border border-indigo-200 bg-indigo-50 px-3 py-1.5 rounded-lg transition-colors mr-2">
                            <i class="fas fa-search"></i> Review
                        </button>
                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" download class="text-slate-400 hover:text-bisu mr-2" title="Download"><i class="fas fa-download"></i></a>
                        <a href="?view=dashboard&delete_id=<?= $doc['doc_id'] ?>" onclick="return confirm('Are you sure you want to delete this document?')" class="text-slate-400 hover:text-red-600" title="Delete"><i class="fas fa-trash"></i></a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

        <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 transition-all duration-300 hover:shadow-md">
          <h3 class="text-lg font-semibold text-slate-900 mb-3">Pending / Draft Files</h3>
          <ul class="divide-y divide-slate-100">
            <?php if(empty($pending_files)): ?>
                <li class="py-4 text-center text-slate-500 text-sm">No pending files.</li>
            <?php else: ?>
                <?php foreach($pending_files as $pf): ?>
                <li class="py-3 flex items-center justify-between gap-4 hover:bg-slate-50 px-2 rounded transition-colors cursor-pointer">
                  <div class="min-w-0 flex-1">
                    <div class="font-medium text-slate-800 truncate" title="<?= htmlspecialchars($pf['file_name']) ?>"><?= htmlspecialchars($pf['file_name']) ?></div>
                    <div class="text-xs text-slate-500 truncate" title="Uploaded by: <?= htmlspecialchars($pf['full_name']) ?> • <?= htmlspecialchars($pf['program_code']) ?>">Uploaded by: <?= htmlspecialchars($pf['full_name']) ?> • <?= htmlspecialchars($pf['program_code']) ?></div>
                  </div>
                  <div class="flex-shrink-0">
                      <a href="?view=dashboard&approve_id=<?= $pf['doc_id'] ?>" class="text-xs bg-emerald-100 text-emerald-700 px-2 py-1 rounded hover:bg-emerald-200 transition-colors">
                          Approve
                      </a>
                  </div>
                </li>
                <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </section>
      </div>

      <?php elseif ($view === 'self_survey'): ?>
        <?php if (!$college_filter): ?>
            <div class="mb-10 text-center">
                <h2 class="text-3xl font-bold text-slate-800">Self Survey Results</h2>
                <p class="text-slate-500 mt-2">Select a college to view its program assessment scores.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-5xl mx-auto">
                <?php foreach($all_colleges_list as $c): 
                    $cc = strtoupper($c['college_code']);
                    
                    // Default Theme (Indigo)
                    $border_cls = "hover:border-indigo-400";
                    $icon_cls = "text-indigo-600 bg-indigo-50 group-hover:bg-indigo-600";
                    $title_cls = "group-hover:text-indigo-700";
                    $arrow_cls = "group-hover:bg-indigo-600";

                    if ($cc === 'COS') { // Green
                        $border_cls = "hover:border-green-400";
                        $icon_cls = "text-green-600 bg-green-50 group-hover:bg-green-600";
                        $title_cls = "group-hover:text-green-700";
                        $arrow_cls = "group-hover:bg-green-600";
                    } elseif ($cc === 'CTE') { // Red
                        $border_cls = "hover:border-rose-400";
                        $icon_cls = "text-rose-600 bg-rose-50 group-hover:bg-rose-600";
                        $title_cls = "group-hover:text-rose-700";
                        $arrow_cls = "group-hover:bg-rose-600";
                    } elseif ($cc === 'CBM') { // Orange
                        $border_cls = "hover:border-orange-400";
                        $icon_cls = "text-orange-600 bg-orange-50 group-hover:bg-orange-600";
                        $title_cls = "group-hover:text-orange-700";
                        $arrow_cls = "group-hover:bg-orange-600";
                    } elseif ($cc === 'CFMS') { // Blue
                        $border_cls = "hover:border-blue-400";
                        $icon_cls = "text-blue-600 bg-blue-50 group-hover:bg-blue-600";
                        $title_cls = "group-hover:text-blue-700";
                        $arrow_cls = "group-hover:bg-blue-600";
                    }
                ?>
                <a href="?view=self_survey&college_id=<?= $c['college_id'] ?>" class="group p-6 rounded-xl border bg-white border-slate-200 shadow-sm transition-all duration-300 flex items-center gap-5 hover:shadow-xl hover:-translate-y-1 <?= $border_cls ?>">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-2xl shadow-sm transition-all duration-300 group-hover:text-white <?= $icon_cls ?>">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-lg transition-colors text-slate-800 <?= $title_cls ?>"><?= htmlspecialchars($c['college_name']) ?></h3>
                        <p class="text-sm opacity-80 text-slate-600"><?= htmlspecialchars($c['college_code']) ?> Department</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 bg-slate-50 text-slate-400 group-hover:text-white <?= $arrow_cls ?>">
                        <i class="fas fa-arrow-right transform group-hover:translate-x-1 transition-transform"></i>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Self Survey: <?= htmlspecialchars($selected_college_data['college_name']) ?></h1>
                    <p class="text-slate-500 text-sm">Average scores from Internal and External accreditors.</p>
                </div>
                <a href="?view=self_survey" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors shadow-sm">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Colleges
                </a>
            </div>

            <?php if (empty($survey_results_data)): ?>
                <div class="bg-white rounded-xl border border-slate-200 p-12 text-center">
                    <i class="fas fa-poll text-4xl text-slate-300 mb-4"></i>
                    <h3 class="text-lg font-semibold text-slate-700">No Survey Data Found</h3>
                    <p class="text-slate-500">No ratings have been submitted for any program in this college yet.</p>
                </div>
            <?php else: ?>
                <!-- Analytics Chart -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-8">
                    <h3 class="font-bold text-lg text-slate-800 mb-4">Comparative Ratings by Program</h3>
                    <div class="relative h-72 w-full">
                        <canvas id="surveyChart"></canvas>
                    </div>
                </div>
                <?php 
                // Prepare Data for Chart
                $chart_labels = [];
                $chart_int = [];
                $chart_ext = [];
                
                foreach($survey_results_data as $pid => $pdata) {
                    $chart_labels[] = $pdata['details']['code'];
                    
                    $sum_i = 0; $cnt_i = 0;
                    $sum_e = 0; $cnt_e = 0;
                    
                    foreach($pdata['scores'] as $s) {
                        if(isset($s['internal'])) { $sum_i += $s['internal']; $cnt_i++; }
                        if(isset($s['external'])) { $sum_e += $s['external']; $cnt_e++; }
                    }
                    
                    $chart_int[] = $cnt_i ? round($sum_i/$cnt_i, 2) : 0;
                    $chart_ext[] = $cnt_e ? round($sum_e/$cnt_e, 2) : 0;
                }
                ?>
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        const ctx = document.getElementById('surveyChart').getContext('2d');
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: <?= json_encode($chart_labels) ?>,
                                datasets: [
                                    { label: 'Internal Rating', data: <?= json_encode($chart_int) ?>, backgroundColor: 'rgba(79, 70, 229, 0.7)', borderColor: 'rgba(79, 70, 229, 1)', borderWidth: 1 },
                                    { label: 'External Rating', data: <?= json_encode($chart_ext) ?>, backgroundColor: 'rgba(16, 185, 129, 0.7)', borderColor: 'rgba(16, 185, 129, 1)', borderWidth: 1 }
                                ]
                            },
                            options: {
                                responsive: true, maintainAspectRatio: false,
                                scales: { y: { beginAtZero: true, max: 5, title: { display: true, text: 'Rating (1-5)' } } },
                                plugins: { legend: { position: 'top' } }
                            }
                        });
                    });
                </script>

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
                                <thead class="bg-slate-100/50 text-slate-600 font-semibold">
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
      </div>
      <?php endif; ?>

  </main>

  <script>
    // Pass PHP data to JS
    const chartDataColleges = <?= json_encode($chart_colleges ?? []) ?>;
    const chartDataAreas = <?= json_encode($chart_areas ?? []) ?>;
  </script>
  <script src="js/admin-dashboard.js"></script>

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
                <h3 class="font-bold text-slate-800">Admin's Review</h3>
                <button onclick="document.getElementById('auditModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="flex-1 p-6 overflow-y-auto">
                <form method="POST" action="" class="flex flex-col h-full">
                    <input type="hidden" name="document_id" id="modalDocId">
                    <input type="hidden" name="submit_feedback" value="1">
                    
                    <div class="mb-4 flex-1 flex flex-col">
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Admin's Comments / Feedback</label>
                        <textarea name="feedback_text" required class="w-full p-4 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm resize-none flex-1 min-h-[200px]" placeholder="Enter your feedback for the faculty..."></textarea>
                    </div>
                    
                    <div class="mt-auto pt-4 border-t border-slate-100">
                        <button type="submit" class="btn btn-primary w-full py-3 flex justify-center items-center gap-2 bg-bisu">
                            <i class="fas fa-paper-plane"></i> Submit Feedback
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
  </div>

  <!-- Modals -->
  <div id="modalAddUser" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
      <h4 class="text-lg font-semibold mb-3">Add User</h4>
      <div class="space-y-3">
        <input placeholder="Full name" class="w-full px-3 py-2 border rounded" id="inputUserName">
        <input placeholder="Email" class="w-full px-3 py-2 border rounded" id="inputUserEmail">
        <select id="inputUserRole" class="w-full px-3 py-2 border rounded">
          <option>Admin</option>
          <option>Editor</option>
          <option>Viewer</option>
        </select>
      </div>
      <div class="mt-4 flex justify-end gap-2">
        <button data-close class="px-3 py-2 border rounded">Cancel</button>
        <button id="saveUser" class="px-3 py-2 bg-bisu text-white rounded">Save</button>
      </div>
    </div>
  </div>

  <div id="modalAddCollege" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
      <h4 class="text-lg font-semibold mb-3">Add College</h4>
      <input placeholder="College name" class="w-full px-3 py-2 border rounded" id="inputCollegeName">
      <div class="mt-4 flex justify-end gap-2">
        <button data-close class="px-3 py-2 border rounded">Cancel</button>
        <button id="saveCollege" class="px-3 py-2 bg-bisu text-white rounded">Save</button>
      </div>
    </div>
  </div>

  <div id="modalAddArea" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
      <h4 class="text-lg font-semibold mb-3">Add Area</h4>
      <input placeholder="Area name" class="w-full px-3 py-2 border rounded" id="inputAreaName">
      <div class="mt-4 flex justify-end gap-2">
        <button data-close class="px-3 py-2 border rounded">Cancel</button>
        <button id="saveArea" class="px-3 py-2 bg-bisu text-white rounded">Save</button>
      </div>
    </div>
  </div>

  <div id="modalUploadDoc" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
      <h4 class="text-lg font-semibold mb-3">Upload Document</h4>
      <input type="file" id="inputDocFile" class="w-full">
      <div class="mt-4 flex justify-end gap-2">
        <button data-close class="px-3 py-2 border rounded">Cancel</button>
        <button id="saveDoc" class="px-3 py-2 bg-bisu text-white rounded">Upload</button>
      </div>
    </div>
  </div>

  <?php if ($show_welcome): ?>
  <!-- Welcome Animation Overlay -->
  <div id="welcomeOverlay" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/80 backdrop-blur-sm transition-opacity duration-700">
    <div class="bg-white p-10 rounded-3xl shadow-2xl text-center transform scale-90 opacity-0 animate-welcome-in max-w-md w-full mx-4 border-4 border-white/50">
        <div class="w-24 h-24 bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-full flex items-center justify-center mx-auto mb-6 text-4xl shadow-lg animate-bounce-slow">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h2 class="text-3xl font-bold text-slate-800 mb-2">Welcome Back!</h2>
        <p class="text-xl text-indigo-600 font-medium mb-6"><?= htmlspecialchars($_SESSION['full_name']) ?></p>
        <p class="text-slate-500 text-sm mb-8">Loading Admin Dashboard...</p>
        
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
