<?php
session_start();
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$role = strtolower(trim($_SESSION['role'] ?? ''));
if (strpos($role, 'accreditor') === false && strpos($role, 'admin') === false) {
  header('Location: role_home.php');
  exit;
}

// Fetch Programs for filter
$programs = $conn->query("SELECT * FROM programs ORDER BY program_name");
if (!$programs) { die("Error fetching programs: " . $conn->error); }

// Fetch Areas for filter
$areas = $conn->query("SELECT * FROM areas ORDER BY area_no");
if (!$areas) { die("Error fetching areas: " . $conn->error); }

// Fetch Years for filter
$years_list = [];
$y_res = $conn->query("SELECT DISTINCT YEAR(valid_from) as year FROM cycles WHERE valid_from IS NOT NULL ORDER BY year DESC");
if ($y_res) {
    while($row = $y_res->fetch_assoc()) {
        $years_list[] = $row['year'];
    }
}

// Handle Feedback Submission
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
                $stmt_notif = $conn->prepare("INSERT INTO user_notifications (user_id, doc_id, message) VALUES (?, ?, 'An Accreditor has left feedback on your document.')");
                if ($stmt_notif) {
                    $stmt_notif->bind_param("ii", $uploader_id, $doc_id);
                    $stmt_notif->execute();
                }
            }
        }
    }
    // Redirect to avoid resubmission
    $redirect_url = "accreditor_dashboard.php?view=documents";
    if (isset($_GET['college_id'])) $redirect_url .= "&college_id=" . $_GET['college_id'];
    if (isset($_GET['program_id'])) $redirect_url .= "&program_id=" . $_GET['program_id'];
    if (isset($_GET['area_id'])) $redirect_url .= "&area_id=" . $_GET['area_id'];
    if (isset($_GET['year'])) $redirect_url .= "&year=" . $_GET['year'];
    header("Location: " . $redirect_url);
    exit;
}

// Handle AJAX Fetch Feedback
if (isset($_GET['fetch_feedback']) && isset($_GET['doc_id'])) {
    $doc_id = intval($_GET['doc_id']);
    $query = "SELECT f.*, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) AS full_name, r.role_name 
              FROM document_feedback f 
              JOIN users u ON f.user_id = u.user_id 
              LEFT JOIN roles r ON u.role_id = r.role_id
              WHERE f.doc_id = ? 
              ORDER BY f.created_at DESC";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $feedbacks = [];
        while ($row = $result->fetch_assoc()) {
            $feedbacks[] = $row;
        }
    } else {
        $feedbacks = [];
    }
    header('Content-Type: application/json');
    echo json_encode($feedbacks);
    exit;
}

// Handle AJAX Fetch Survey Parameters
if (isset($_GET['fetch_survey_params']) && isset($_GET['area_id'])) {
    $area_id = intval($_GET['area_id']);
    $program_id = intval($_GET['program_id']);
    $user_id = $_SESSION['user_id'];
    
    $response = ['params' => [], 'file' => null];

    // 1. Fetch all existing ratings for this user/program/area at once to prevent N+1 queries
    $existing_ratings = [];
    $rating_stmt = $conn->prepare("SELECT parameter_index, rating FROM survey_ratings WHERE program_id = ? AND area_id = ? AND rated_by = ?");
    $rating_stmt->bind_param("iii", $program_id, $area_id, $user_id);
    $rating_stmt->execute();
    $rating_res = $rating_stmt->get_result();
    while ($rating_row = $rating_res->fetch_assoc()) {
        $existing_ratings[$rating_row['parameter_index']] = $rating_row['rating'];
    }

    // 2. Fetch parameters for this area using a prepared statement
    $param_stmt = $conn->prepare("SELECT * FROM survey_parameters WHERE area_id = ? ORDER BY parameter_order ASC");
    $param_stmt->bind_param("i", $area_id);
    $param_stmt->execute();
    $q = $param_stmt->get_result();

    if ($q) {
        while($row = $q->fetch_assoc()) {
            // Look up the rating from our pre-fetched map instead of querying in a loop
            $row['current_rating'] = $existing_ratings[$row['param_id']] ?? 0;
            $response['params'][] = $row;
        }
    }

    // 3. Fetch Survey Instrument File (PDF) using a prepared statement
    $type_stmt = $conn->prepare("SELECT type_id FROM document_types WHERE type_name = 'Survey Instrument' LIMIT 1");
    $type_stmt->execute();
    $type_res = $type_stmt->get_result();

    if ($type_res && $type_row = $type_res->fetch_assoc()) {
        $type_id = $type_row['type_id'];
        // Find the latest uploaded survey instrument for this cycle/program
        $doc_sql = "SELECT d.file_path FROM documents d 
                    JOIN cycles c ON d.cycle_id = c.cycle_id 
                    WHERE c.program_id = ? AND d.area_id = ? AND d.type_id = ? 
                    ORDER BY d.uploaded_at DESC LIMIT 1";
        $doc_stmt = $conn->prepare($doc_sql);
        $doc_stmt->bind_param("iii", $program_id, $area_id, $type_id);
        $doc_stmt->execute();
        $doc_res = $doc_stmt->get_result();
        if ($doc_res && $doc_row = $doc_res->fetch_assoc()) {
            $response['file'] = $doc_row['file_path'];
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle Survey Rating Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_survey'])) {
    $prog_id = intval($_POST['program_id']);
    $area_id = intval($_POST['area_id']);
    $user_id = $_SESSION['user_id'];
    $role = strtolower(trim($_SESSION['role'] ?? ''));

    // Determine accreditor type from role name
    $accreditor_type = (strpos($role, 'external') !== false) ? 'external' : 'internal';
    
    // Clear previous ratings for this specific user and area/program to allow updates
    $del_stmt = $conn->prepare("DELETE FROM survey_ratings WHERE program_id = ? AND area_id = ? AND rated_by = ?");
    $del_stmt->bind_param("iii", $prog_id, $area_id, $user_id);
    $del_stmt->execute();
    
    // Prepare insert statement
    $ins_stmt = $conn->prepare("INSERT INTO survey_ratings (program_id, area_id, parameter_index, rating, rated_by, accreditor_type) VALUES (?, ?, ?, ?, ?, ?)");

    // Save new ratings dynamically based on POST keys
    foreach ($_POST as $key => $val) {
        if (strpos($key, 'rating_param_') === 0) {
            $param_id = intval(substr($key, 13)); // Remove 'rating_param_' prefix
            $rating = intval($val);
            $ins_stmt->bind_param("iiiiss", $prog_id, $area_id, $param_id, $rating, $user_id, $accreditor_type);
            $ins_stmt->execute();
        }
    }
    header("Location: accreditor_dashboard.php?view=self_survey&college_id=" . $_GET['college_id'] . "&program_id=$prog_id&saved=1");
    exit;
}

// Determine View
$view = isset($_GET['view']) ? $_GET['view'] : 'documents';

// Handle Filters
$selected_college = isset($_GET['college_id']) && $_GET['college_id'] !== '' ? (int)$_GET['college_id'] : null;
$selected_program = isset($_GET['program_id']) && $_GET['program_id'] !== '' ? (int)$_GET['program_id'] : null;
$selected_area = isset($_GET['area_id']) && $_GET['area_id'] !== '' ? (int)$_GET['area_id'] : null;
$selected_type = isset($_GET['type_id']) && $_GET['type_id'] !== '' ? (int)$_GET['type_id'] : null;
$selected_year = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : null;
$selected_level = isset($_GET['level']) && $_GET['level'] !== '' ? (int)$_GET['level'] : null;
$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Fetch All Colleges for Dropdown (Always needed for the filter)
$all_colleges_list = [];
$ac_res = $conn->query("SELECT * FROM colleges ORDER BY college_name");
if ($ac_res) {
    while($row = $ac_res->fetch_assoc()) {
        $all_colleges_list[] = $row;
    }
}

// Fetch data for dashboard view
$colleges_with_stats = [];
if ($view === 'documents' && !$selected_college) {
    $sql_colleges = "SELECT 
                col.college_id, 
                col.college_name, 
                col.college_code,
                COUNT(DISTINCT p.program_id) as program_count,
                COUNT(d.doc_id) as doc_count
            FROM colleges col
            LEFT JOIN programs p ON col.college_id = p.college_id
            LEFT JOIN cycles c ON p.program_id = c.program_id
            LEFT JOIN documents d ON c.cycle_id = d.cycle_id
            GROUP BY col.college_id, col.college_name, col.college_code
            ORDER BY col.college_name";
    $college_res = $conn->query($sql_colleges);
    if ($college_res) {
        while($row = $college_res->fetch_assoc()) {
            $colleges_with_stats[] = $row;
        }
    }
}

// Fetch Global Stats (Always needed for root view)
$total_docs = $conn->query("SELECT COUNT(*) FROM documents")->fetch_row()[0] ?? 0;
$total_programs = $conn->query("SELECT COUNT(*) FROM programs")->fetch_row()[0] ?? 0;
$total_areas = $conn->query("SELECT COUNT(*) FROM areas")->fetch_row()[0] ?? 0;

// Check if 'status' column exists to prevent fatal errors
$check_status = $conn->query("SHOW COLUMNS FROM documents LIKE 'status'");
$has_status_col = ($check_status && $check_status->num_rows > 0);

// Build Query
$sql = "SELECT d.*, a.area_title, a.area_no, p.program_code, p.program_name, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) as uploader_name, dt.type_name,
        (SELECT COUNT(*) FROM document_feedback WHERE doc_id = d.doc_id) as feedback_count
        FROM documents d
        JOIN cycles c ON d.cycle_id = c.cycle_id
        JOIN programs p ON c.program_id = p.program_id
        JOIN areas a ON d.area_id = a.area_id
        LEFT JOIN users u ON d.uploaded_by = u.user_id
        LEFT JOIN document_types dt ON d.type_id = dt.type_id
        WHERE 1=1";

$params = [];
$types = "";

if ($has_status_col) {
    $sql .= " AND d.status = 'approved'";
}

if ($selected_college) {
    $sql .= " AND p.college_id = ?";
    $params[] = $selected_college;
    $types .= "i";
}

if ($selected_program) {
    $sql .= " AND p.program_id = ?";
    $params[] = $selected_program;
    $types .= "i";
}

if ($selected_area) {
    $sql .= " AND d.area_id = ?";
    $params[] = $selected_area;
    $types .= "i";
}

if ($selected_type) {
    $sql .= " AND d.type_id = ?";
    $params[] = $selected_type;
    $types .= "i";
}

if ($selected_year) {
    $sql .= " AND YEAR(c.valid_from) = ?";
    $params[] = $selected_year;
    $types .= "i";
}

if ($selected_level) {
    $sql .= " AND c.level = ?";
    $params[] = $selected_level;
    $types .= "i";
}

if ($search_q) {
    $sql .= " AND (d.file_name LIKE ? OR p.program_code LIKE ?)";
    $params[] = "%$search_q%";
    $params[] = "%$search_q%";
    $types .= "ss";
}

$sql .= " ORDER BY p.program_name ASC, a.area_no ASC, d.uploaded_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Database Error: " . $conn->error . "<br><strong>Please run 'update_schema_status.php' and 'fix_db_schema.php' to update your database.</strong>");
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$documents = $stmt->get_result();
if (!$documents) { die("Error fetching documents: " . $conn->error); }

// Check for welcome message
$show_welcome = false;
if (isset($_SESSION['show_welcome']) && $_SESSION['show_welcome']) {
    $show_welcome = true;
    unset($_SESSION['show_welcome']);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Accreditor Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: { colors: { bisu: '#4f46e5' } } } }
  </script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/admin-dashboard.css">
  <style>
    /* Override dashboard CSS to remove sidebar spacing */
    .main-content { margin-left: 0 !important; width: 100%; max-width: 1600px; margin: 64px auto 0; padding: 40px; }
    .sidebar { display: none !important; }
  </style>
</head>
<body class="min-h-screen">
  <header class="topbar">
    <div class="left">
      <a href="accreditor_dashboard.php" class="brand">
        <i class="fas fa-user-check"></i> <span>Accreditor Dashboard</span>
      </a>
    </div>
    <div class="right">
      <div class="user-profile">
        <div class="user-info hidden sm:block">
          <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Accreditor'); ?></span>
          <span class="user-role">Accreditor</span>
        </div>
        <?php if (!empty($_SESSION['avatar_path']) && file_exists($_SESSION['avatar_path'])): ?>
            <img src="<?= htmlspecialchars($_SESSION['avatar_path']) ?>" alt="Avatar" class="user-avatar" style="object-fit: cover;">
        <?php else: ?>
            <div class="user-avatar">
              <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)); ?>
            </div>
        <?php endif; ?>
      </div>
      <div class="divider-vertical"></div>
      <a href="profile.php" class="logout-btn" title="Manage Profile" aria-label="Manage Profile"><i class="fas fa-user-cog"></i></a>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <main class="main-content">
    <?php if ($view === 'documents'): ?>

    <?php if (!$selected_college): ?>
      <div class="mb-10 text-center">
        <h1 class="text-3xl font-bold text-slate-800">Accreditor Dashboard</h1>
        <p class="text-slate-500 mt-2 text-lg">Overview of accreditation status and document repositories.</p>
      </div>

      <!-- Global Stats Section -->
      <section class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16 max-w-6xl mx-auto">
        <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
            <div class="text-slate-500 text-sm font-medium uppercase">Total Documents</div>
            <div class="text-4xl font-bold text-indigo-600 mt-2"><?= number_format($total_docs) ?></div>
        </div>
        <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
            <div class="text-slate-500 text-sm font-medium uppercase">Total Programs</div>
            <div class="text-4xl font-bold text-amber-500 mt-2"><?= number_format($total_programs) ?></div>
        </div>
        <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
            <div class="text-slate-500 text-sm font-medium uppercase">Total Areas</div>
            <div class="text-4xl font-bold text-slate-800 mt-2"><?= number_format($total_areas) ?></div>
        </div>
      </section>

      <div class="mb-12 text-center">
          <h2 class="text-2xl font-bold text-slate-800">College Repositories</h2>
          <p class="text-slate-500 mt-2">Select a department below to access its documents.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-7xl mx-auto">
        <?php foreach($all_colleges_list as $c): 
            $cc = strtoupper($c['college_code']);
            
            // Default styles
            $card_class = "bg-white border-slate-200 hover:border-indigo-400 hover:shadow-2xl hover:-translate-y-2";
            $icon_class = "text-indigo-600 bg-indigo-50 group-hover:bg-indigo-600 group-hover:text-white";
            $text_class = "text-slate-800 group-hover:text-indigo-700";

            // College specific styles
            if ($cc === 'COS') { 
                // Using the new palette but keeping the structure
                $card_class = "bg-green-50 border-green-200 hover:bg-green-100 hover:border-green-400 hover:shadow-2xl hover:-translate-y-2";
                $icon_class = "text-green-600 bg-white group-hover:bg-green-600 group-hover:text-white";
                $text_class = "text-green-900";
            }
            elseif ($cc === 'CTE') { 
                $card_class = "bg-rose-50 border-rose-200 hover:bg-rose-100 hover:border-rose-400 hover:shadow-2xl hover:-translate-y-2";
                $icon_class = "text-rose-600 bg-white group-hover:bg-rose-600 group-hover:text-white";
                $text_class = "text-rose-900";
            }
            elseif ($cc === 'CBM') { 
                $card_class = "bg-amber-50 border-amber-200 hover:bg-amber-100 hover:border-amber-400 hover:shadow-2xl hover:-translate-y-2";
                $icon_class = "text-amber-600 bg-white group-hover:bg-amber-600 group-hover:text-white";
                $text_class = "text-amber-900";
            }
            elseif ($cc === 'CFMS') { 
                $card_class = "bg-blue-50 border-blue-200 hover:bg-blue-100 hover:border-blue-400 hover:shadow-2xl hover:-translate-y-2";
                $icon_class = "text-blue-600 bg-white group-hover:bg-blue-600 group-hover:text-white";
                $text_class = "text-blue-900";
            }
        ?>
        <a href="?view=documents&college_id=<?= $c['college_id'] ?>" class="group relative p-10 rounded-3xl border-2 shadow-lg transition-all duration-300 flex flex-col md:flex-row items-center gap-8 <?= $card_class ?>">
            <div class="w-24 h-24 rounded-3xl flex items-center justify-center text-4xl shadow-md transition-all duration-300 <?= $icon_class ?>">
                <i class="fas fa-university"></i>
            </div>
            <div class="flex-1 text-center md:text-left">
                <h3 class="font-bold text-2xl transition-colors mb-2 <?= $text_class ?>"><?= htmlspecialchars($c['college_name']) ?></h3>
                <p class="text-lg opacity-80 font-medium <?= $text_class ?>"><?= htmlspecialchars($c['college_code']) ?> Department</p>
                
                <div class="mt-6 inline-flex items-center text-sm font-bold uppercase tracking-wider opacity-60 <?= $text_class ?>">
                    <span>View Repository</span> <i class="fas fa-arrow-right ml-2 group-hover:translate-x-2 transition-transform"></i>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
      </div>
    
    <?php elseif ($selected_college && !$selected_program): ?>
      <?php
        // Fetch Programs for this college
        $prog_stats = [];
        $stmt_progs = $conn->prepare("SELECT p.program_id, p.program_code, p.program_name, col.college_code,
            (SELECT COUNT(*) 
             FROM documents d 
             JOIN cycles c ON d.cycle_id = c.cycle_id 
             WHERE c.program_id = p.program_id) as doc_count
        FROM programs p
        JOIN colleges col ON p.college_id = col.college_id
        WHERE p.college_id = ?
        ORDER BY p.program_name");
        $stmt_progs->bind_param("i", $selected_college);
        $stmt_progs->execute();
        $res_progs = $stmt_progs->get_result();
        while($row = $res_progs->fetch_assoc()) {
            $prog_stats[] = $row;
        }
        
        // Get College Name and Code for Page Styling
        $c_query = $conn->query("SELECT college_name, college_code FROM colleges WHERE college_id = $selected_college");
        $c_data = $c_query->fetch_assoc();
        $c_name = $c_data['college_name'] ?? 'College';
        $c_code = strtoupper($c_data['college_code'] ?? '');

        // Dynamic Theme Colors based on College (Gradient Style)
        $theme_gradient = 'from-slate-700 to-slate-900';
        
        if ($c_code === 'COS') { 
            $theme_gradient = 'from-green-600 to-green-900';
        } elseif ($c_code === 'CTE') { 
            $theme_gradient = 'from-red-600 to-rose-900';
        } elseif ($c_code === 'CBM') { 
            $theme_gradient = 'from-amber-500 to-yellow-700';
        } elseif ($c_code === 'CFMS') { 
            $theme_gradient = 'from-blue-600 to-cyan-900';
        }
      ?>
      
      <!-- Enhanced College Landing Header -->
      <div class="relative rounded-2xl mb-12">
        <!-- Decorative Background Pattern -->
        <div class="absolute top-0 left-0 w-full h-64 bg-gradient-to-r <?= $theme_gradient ?> rounded-2xl">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"></div>
        </div>
        
        <div class="relative h-64 px-6 md:px-10 pb-8 flex items-end justify-between">
            <div class="flex items-center gap-6">
                <div class="hidden md:flex w-24 h-24 bg-white/20 backdrop-blur-sm rounded-2xl items-center justify-center text-5xl font-bold text-white border-2 border-white/30">
                    <i class="fas fa-university"></i>
                </div>
                <div>
                    <h1 class="text-4xl font-bold text-white drop-shadow-lg"><?= htmlspecialchars($c_code) ?></h1>
                    <h2 class="text-lg md:text-xl text-white/90 font-medium mt-1 mb-2 max-w-3xl leading-tight"><?= htmlspecialchars($c_name) ?></h2>
                    <span class="mt-2 inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-white/20 text-white backdrop-blur-sm border border-white/30">
                        Department Repository
                    </span>
                </div>
            </div>
            <a href="accreditor_dashboard.php?view=documents" class="hidden md:inline-flex items-center justify-center px-4 py-2 bg-white/20 border border-white/30 backdrop-blur-sm rounded-lg text-white font-medium hover:bg-white/30 transition-colors shadow-sm">
                <i class="fas fa-arrow-left mr-2"></i> Back to Colleges
            </a>
        </div>
      </div>

      <!-- Description Card -->
      <div class="relative px-6 md:px-10 -mt-16 z-10 mb-10">
        <div class="bg-white p-8 rounded-xl shadow-lg border border-slate-200">
             <p class="text-slate-600 text-lg">Select a program below to view its accreditation documents, compliance reports, and evidence files.</p>
        </div>
      </div>

      <div class="px-6 md:px-10 pb-10">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 w-full">
                <?php foreach($prog_stats as $prog): 
                    $theme_bg_class = 'bg-white hover:bg-slate-50';
                    $theme_icon_bg_class = 'bg-slate-100';
                    $theme_icon_text_class = 'text-slate-600';
                    $theme_hover_icon_bg_class = 'group-hover:bg-slate-600';

                    if ($c_code === 'COS') { // Green
                        $theme_bg_class = 'bg-white hover:bg-green-100 border-green-100';
                        $theme_icon_bg_class = 'bg-green-100';
                        $theme_icon_text_class = 'text-green-700';
                        $theme_hover_icon_bg_class = 'group-hover:bg-green-600';
                    } elseif ($c_code === 'CTE') { // Red
                        $theme_bg_class = 'bg-white hover:bg-red-100 border-red-100';
                        $theme_icon_bg_class = 'bg-red-100';
                        $theme_icon_text_class = 'text-red-700';
                        $theme_hover_icon_bg_class = 'group-hover:bg-red-600';
                    } elseif ($c_code === 'CBM') { // Orange
                        $theme_bg_class = 'bg-white hover:bg-amber-50 border-amber-100';
                        $theme_icon_bg_class = 'bg-amber-100';
                        $theme_icon_text_class = 'text-amber-700';
                        $theme_hover_icon_bg_class = 'group-hover:bg-amber-500';
                    } elseif ($c_code === 'CFMS') { // Blue
                        $theme_bg_class = 'bg-white hover:bg-blue-100 border-blue-100';
                        $theme_icon_bg_class = 'bg-blue-100';
                        $theme_icon_text_class = 'text-blue-700';
                        $theme_hover_icon_bg_class = 'group-hover:bg-blue-600';
                    }
                ?>
                    <a href="accreditor_dashboard.php?view=documents&college_id=<?= $selected_college ?>&program_id=<?= $prog['program_id'] ?>" class="<?= $theme_bg_class ?> p-8 rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl transition-all group flex flex-col items-center justify-center text-center hover:-translate-y-2 cursor-pointer duration-300">
                        <div class="w-20 h-20 <?= $theme_icon_bg_class ?> <?= $theme_icon_text_class ?> rounded-2xl flex items-center justify-center mb-6 <?= $theme_hover_icon_bg_class ?> group-hover:text-white transition-colors shadow-inner">
                            <i class="fas fa-graduation-cap fa-3x"></i>
                        </div>
                        <h3 class="font-bold text-slate-800 text-xl mb-2"><?= htmlspecialchars($prog['program_code']) ?></h3>
                        <p class="text-sm text-slate-500 mb-4 h-10 overflow-hidden line-clamp-2 px-4"><?= htmlspecialchars($prog['program_name']) ?></p>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 group-hover:bg-white group-hover:shadow-sm transition-all">
                            <i class="fas fa-file-alt mr-2"></i> <?= $prog['doc_count'] ?> Documents
                        </span>
                    </a>
                <?php endforeach; ?>
          </div>
      </div>

    <?php elseif ($selected_college && $selected_program && !$selected_type): ?>
      <?php
        // Fetch Document Types with counts for this PROGRAM (not just college)
        $doc_types_stats = [];
        $stmt_types = $conn->prepare("SELECT dt.type_id, dt.type_name, 
            (SELECT COUNT(*) 
             FROM documents d 
             JOIN cycles c ON d.cycle_id = c.cycle_id 
             WHERE d.type_id = dt.type_id AND c.program_id = ?) as doc_count
        FROM document_types dt
        ORDER BY dt.type_name");
        $stmt_types->bind_param("i", $selected_program);
        $stmt_types->execute();
        $res_types = $stmt_types->get_result();
        while($row = $res_types->fetch_assoc()) {
            $doc_types_stats[] = $row;
        }
        
        // Get Program Details with Level and College Code for Styling
        $p_query = $conn->query("SELECT p.program_code, p.program_name, c.college_code,
                                (SELECT level FROM cycles WHERE program_id = p.program_id ORDER BY cycle_id DESC LIMIT 1) as current_level 
                                FROM programs p 
                                JOIN colleges c ON p.college_id = c.college_id
                                WHERE p.program_id = $selected_program");
        $p_data = $p_query->fetch_assoc();
        $p_code = $p_data['program_code'] ?? 'PROG';
        $p_name = $p_data['program_name'] ?? 'Unknown Program';
        $p_level = $p_data['current_level'] ?? 1;
        $c_code = strtoupper($p_data['college_code'] ?? '');

        // Dynamic Theme Colors based on College
        $theme_gradient = 'from-slate-700 to-slate-900';
        $theme_accent = 'text-indigo-600';
        
        if ($c_code === 'COS') { // College of Science - Green
            $theme_gradient = 'from-green-600 to-green-900';
            $theme_accent = 'text-green-600';
        } elseif ($c_code === 'CTE') { // College of Teacher Education - Red
            $theme_gradient = 'from-red-600 to-rose-900';
            $theme_accent = 'text-red-600';
        } elseif ($c_code === 'CBM') { // College of Business and Management - Orange
            $theme_gradient = 'from-amber-500 to-yellow-700';
            $theme_accent = 'text-amber-600';
        } elseif ($c_code === 'CFMS') { // College of Fisheries - Blue
            $theme_gradient = 'from-blue-600 to-cyan-900';
            $theme_accent = 'text-blue-600';
        }
      ?>
      
      <!-- Enhanced Program Landing Header -->
      <div class="relative rounded-2xl mb-12">
        <!-- Decorative Background Pattern -->
        <div class="absolute top-0 left-0 w-full h-64 bg-gradient-to-r <?= $theme_gradient ?>">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"></div>
        </div>
        
        <div class="relative h-64 px-6 md:px-10 pb-8 flex items-end justify-between">
            <div class="flex items-center gap-6">
                <div class="hidden md:flex w-24 h-24 bg-white/20 backdrop-blur-sm rounded-2xl items-center justify-center text-5xl font-bold text-white border-2 border-white/30">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div>
                    <h1 class="text-4xl font-bold text-white drop-shadow-lg"><?= htmlspecialchars($p_code) ?></h1>
                    <h2 class="text-lg md:text-xl text-white/90 font-medium mt-1 mb-2 max-w-3xl leading-tight"><?= htmlspecialchars($p_name) ?></h2>
                    <span class="mt-2 inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-white/20 text-white backdrop-blur-sm border border-white/30">
                        Level <?= $p_level ?> Status
                    </span>
                </div>
            </div>
            <div class="hidden md:flex gap-3">
                <a href="print_report.php?program_id=<?= $selected_program ?>" target="_blank" class="inline-flex items-center justify-center px-4 py-2 bg-white/20 border border-white/30 backdrop-blur-sm rounded-lg text-white font-medium hover:bg-white/30 transition-colors shadow-sm">
                    <i class="fas fa-file-alt mr-2"></i> Generate Report
                </a>
                <a href="accreditor_dashboard.php?view=documents&college_id=<?= $selected_college ?>" class="inline-flex items-center justify-center px-4 py-2 bg-white/20 border border-white/30 backdrop-blur-sm rounded-lg text-white font-medium hover:bg-white/30 transition-colors shadow-sm">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>
      </div>

      <!-- Program Name and Description Card -->
      <div class="relative px-6 md:px-10 -mt-16 z-10">
        <div class="bg-white p-8 rounded-xl shadow-lg border border-slate-200">
            <p class="text-slate-600 text-lg max-w-4xl">Welcome to the official accreditation repository. This dashboard provides a centralized view of all compliance documents, evidence, and self-survey reports for the <?= htmlspecialchars($p_code) ?> program.</p>
        </div>
      </div>

      <!-- Folders Grid -->
      <div class="mb-8 flex items-center gap-3">
        <div class="p-2 rounded-lg bg-slate-100 text-slate-600"><i class="fas fa-folder-open"></i></div>
        <h2 class="text-2xl font-bold text-slate-800">Document Repository</h2>
        <div class="h-px bg-slate-200 flex-1 ml-4"></div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-16">
        <!-- Self Survey Card (Prominent) -->
        <a href="accreditor_dashboard.php?view=self_survey&college_id=<?= $selected_college ?>&program_id=<?= $selected_program ?>" class="group relative bg-gradient-to-br <?= $theme_gradient ?> rounded-xl p-6 text-white shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden">
            <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/10 rounded-full blur-xl group-hover:bg-white/20 transition-all"></div>
            <div class="relative z-10">
                <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center mb-4 backdrop-blur-sm">
                    <i class="fas fa-tasks text-xl"></i>
                </div>
                <h3 class="font-bold text-lg mb-1">Self Survey</h3>
                <p class="text-white/80 text-sm mb-4">View assessment checklist</p>
                <div class="flex items-center text-xs font-bold uppercase tracking-wider">
                    <span>Open Instrument</span>
                    <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                </div>
            </div>
        </a>

        <?php foreach($doc_types_stats as $dt): 
            $icon = 'fa-folder';
            $color_class = 'text-slate-500 bg-slate-100 group-hover:bg-slate-600 group-hover:text-white';
            if(stripos($dt['type_name'], 'narrative') !== false) { $icon = 'fa-book'; }
            if(stripos($dt['type_name'], 'compliance') !== false) { $icon = 'fa-check-circle'; }
        ?>
            <a href="accreditor_dashboard.php?view=documents&college_id=<?= $selected_college ?>&program_id=<?= $selected_program ?>&type_id=<?= $dt['type_id'] ?>" class="group bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 rounded-lg <?= $color_class ?> flex items-center justify-center transition-colors duration-300">
                        <i class="fas <?= $icon ?> text-xl"></i>
                    </div>
                    <span class="bg-slate-100 text-slate-600 text-xs font-bold px-2 py-1 rounded-full transition-colors">
                        <?= $dt['doc_count'] ?> Files
                    </span>
                </div>
                <h3 class="font-bold text-slate-800 text-lg mb-1 group-hover:text-slate-600 transition-colors"><?= htmlspecialchars($dt['type_name']) ?></h3>
                <p class="text-slate-500 text-sm">View uploaded documents</p>
            </a>
        <?php endforeach; ?>
      </div>

      <!-- Program Footer -->
      <div class="mt-auto bg-gradient-to-r <?= $theme_gradient ?> rounded-t-2xl pt-8 pb-6 text-center text-white shadow-inner">
        <div class="flex items-center justify-center gap-2 mb-2 text-white/70">
            <i class="fas fa-university"></i>
            <span class="font-semibold tracking-wider text-xs uppercase"><?= htmlspecialchars($c_code) ?> Department</span>
        </div>
        <p class="text-white/90 text-sm">&copy; <?= date('Y') ?> BISU Candijay - <?= htmlspecialchars($p_name) ?> Accreditation Repository</p>
      </div>

    <?php else: ?>
    <?php 
        // Fetch College Data for Styling
        $c_name = 'Selected College';
        $c_code = '';
        $bg_class = "bg-slate-50";
        
        // Breadcrumb styles
        $crumb_bg = "bg-indigo-50";
        $crumb_border = "border-indigo-100";
        $crumb_icon = "text-indigo-600";
        $crumb_text = "text-indigo-800";
        $crumb_link = "text-indigo-600";
        
        if ($selected_college) {
            $c_query = $conn->query("SELECT college_name, college_code FROM colleges WHERE college_id = $selected_college");
            if ($c_query && $c_data = $c_query->fetch_assoc()) {
                $c_name = $c_data['college_name'];
                $c_code = strtoupper($c_data['college_code']);
                
                if ($c_code === 'COS') {
                    $bg_class = "bg-green-50";
                    $crumb_bg = "bg-white/60";
                    $crumb_border = "border-green-200";
                    $crumb_icon = "text-green-600";
                    $crumb_text = "text-green-900";
                    $crumb_link = "text-green-700";
                }
                elseif ($c_code === 'CTE') { $bg_class = "bg-rose-50"; $crumb_bg = "bg-white/60"; $crumb_border = "border-rose-200"; $crumb_icon = "text-rose-600"; $crumb_text = "text-rose-900"; $crumb_link = "text-rose-700"; }
                elseif ($c_code === 'CBM') { $bg_class = "bg-amber-50"; $crumb_bg = "bg-white/60"; $crumb_border = "border-amber-200"; $crumb_icon = "text-amber-600"; $crumb_text = "text-amber-900"; $crumb_link = "text-amber-700"; }
                elseif ($c_code === 'CFMS') { $bg_class = "bg-blue-50"; $crumb_bg = "bg-white/60"; $crumb_border = "border-blue-200"; $crumb_icon = "text-blue-600"; $crumb_text = "text-blue-900"; $crumb_link = "text-blue-700"; }
            }
        }
    ?>
    <div class="<?= $bg_class ?> rounded-3xl p-6 md:p-10 min-h-[80vh] transition-colors duration-500">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Accreditation Documents</h1>
        <p class="text-slate-500 text-sm">Select a department and area to view submitted files.</p>
      </div>
      <a href="accreditor_dashboard.php?view=documents&college_id=<?= $selected_college ?>&program_id=<?= $selected_program ?>" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors shadow-sm w-full md:w-auto">
        <i class="fas fa-arrow-left mr-2"></i> Back to Categories
      </a>
    </div>

    <?php if ($selected_college): 
        // College name fetched above
        
        $program_name_display = "";
        if ($selected_program) {
            $p_query = $conn->query("SELECT program_code FROM programs WHERE program_id = $selected_program");
            $program_name_display = $p_query ? $p_query->fetch_assoc()['program_code'] : '';
        }

        $type_name = "";
        if ($selected_type) {
            $t_query = $conn->query("SELECT type_name FROM document_types WHERE type_id = $selected_type");
            $type_name = $t_query ? $t_query->fetch_assoc()['type_name'] : '';
        }
    ?>
    <div class="<?= $crumb_bg ?> border <?= $crumb_border ?> rounded-lg p-4 mb-6 flex items-center justify-between gap-3 shadow-sm">
        <div class="flex items-center gap-3">
            <i class="fas fa-university <?= $crumb_icon ?>"></i>
            <span class="<?= $crumb_text ?> font-medium">
                <strong><?= htmlspecialchars($c_name) ?></strong>
                <?= $program_name_display ? " <i class='fas fa-chevron-right text-xs mx-1 opacity-50'></i> " . htmlspecialchars($program_name_display) : "" ?>
                <?= $type_name ? " <i class='fas fa-chevron-right text-xs mx-1 opacity-50'></i> " . htmlspecialchars($type_name) : "" ?>
            </span>
        </div>
        <a href="accreditor_dashboard.php?view=documents&college_id=<?= $selected_college ?>&program_id=<?= $selected_program ?>" class="text-xs <?= $crumb_link ?> hover:underline font-semibold">
            <i class="fas fa-times mr-1"></i>Clear Category
        </a>
    </div>
    <?php endif; ?>

    <?php $is_capsule = (stripos($type_name, 'Capsule') !== false); ?>
    <!-- Area Folders & Year Filter -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-slate-700"><?= $is_capsule ? 'Filter by Year' : 'Filter by Area' ?></h3>
            
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="view" value="documents">
                <input type="hidden" name="college_id" value="<?= $selected_college ?>">
                <input type="hidden" name="program_id" value="<?= $selected_program ?>">
                <input type="hidden" name="type_id" value="<?= $selected_type ?>">
                <?php if($selected_area): ?><input type="hidden" name="area_id" value="<?= $selected_area ?>"><?php endif; ?>
                
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400"><i class="fas fa-search"></i></span>
                    <input type="text" name="q" value="<?= htmlspecialchars($search_q) ?>" placeholder="Search files..." aria-label="Search files" class="pl-9 pr-4 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 outline-none w-48 md:w-64">
                </div>

                <select name="level" aria-label="Filter by Level" class="px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" onchange="this.form.submit()">
                    <option value="">All Levels</option>
                    <option value="1" <?= ($selected_level == 1) ? 'selected' : '' ?>>Level 1</option>
                    <option value="2" <?= ($selected_level == 2) ? 'selected' : '' ?>>Level 2</option>
                    <option value="3" <?= ($selected_level == 3) ? 'selected' : '' ?>>Level 3</option>
                    <option value="4" <?= ($selected_level == 4) ? 'selected' : '' ?>>Level 4</option>
                </select>

                <select name="year" aria-label="Filter by Year" class="px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" onchange="this.form.submit()">
                    <option value="">All Years</option>
                    <?php foreach($years_list as $y): ?>
                    <option value="<?= $y ?>" <?= ($selected_year == $y) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
                
                <?php if($selected_area || $selected_year || $selected_level || $search_q): ?>
                <a href="accreditor_dashboard.php?view=documents&college_id=<?= $selected_college ?>&program_id=<?= $selected_program ?>&type_id=<?= $selected_type ?>" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-600 bg-white hover:bg-slate-50">
                    Reset
                </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!$is_capsule): ?>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <?php 
            if ($areas->num_rows > 0) {
                $areas->data_seek(0);
                while($a = $areas->fetch_assoc()): 
                    $isActive = ($selected_area == $a['area_id']);
                    $bg = $isActive ? 'bg-indigo-600 text-white shadow-md ring-2 ring-indigo-300 ring-offset-1' : 'bg-white text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300';
                    $border = $isActive ? 'border-transparent' : 'border-slate-200';
            ?>
            <a href="?view=documents&college_id=<?= $selected_college ?>&program_id=<?= $selected_program ?>&type_id=<?= $selected_type ?>&area_id=<?= $a['area_id'] ?>&year=<?= $selected_year ?>" 
               class="p-3 rounded-xl border <?= $border ?> <?= $bg ?> transition-all duration-200 flex flex-col items-center justify-center text-center shadow-sm group h-full">
                <div class="text-[10px] font-bold uppercase tracking-wider mb-1 opacity-80">Area <?= $a['area_no'] ?></div>
                <div class="font-bold text-xs leading-tight line-clamp-2"><?= htmlspecialchars($a['area_title']) ?></div>
            </a>
            <?php endwhile; } ?>
        </div>
        <?php endif; ?>
    </div>

    <section class="panel">
      <div class="flex items-center justify-between mb-4">
        <span class="text-sm text-slate-500"><?= $documents->num_rows ?> file(s) found</span>
      </div>
      
      <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <?php if ($documents->num_rows > 0): ?>
          <?php while($doc = $documents->fetch_assoc()): ?>
            <?php
                // Determine display style based on document type
                $is_capsule_doc = (stripos($doc['type_name'] ?? '', 'Capsule') !== false);
                
                if ($is_capsule_doc) {
                    $icon_cls = "fas fa-folder fa-2x";
                    $icon_bg = "bg-amber-50 text-amber-400 group-hover:bg-amber-500 group-hover:text-white";
                    $main_text = $doc['uploader_name'] ?? 'Unknown';
                    $sub_text = $doc['program_code'];
                } else {
                    // Dynamic Icon based on extension
                    $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['pdf'])) {
                        $icon_cls = "fas fa-file-pdf fa-2x";
                        $icon_bg = "bg-red-50 text-red-400 group-hover:bg-red-600 group-hover:text-white";
                    } elseif (in_array($ext, ['doc', 'docx'])) {
                        $icon_cls = "fas fa-file-word fa-2x";
                        $icon_bg = "bg-blue-50 text-blue-400 group-hover:bg-blue-600 group-hover:text-white";
                    } elseif (in_array($ext, ['xls', 'xlsx', 'csv'])) {
                        $icon_cls = "fas fa-file-excel fa-2x";
                        $icon_bg = "bg-emerald-50 text-emerald-400 group-hover:bg-emerald-600 group-hover:text-white";
                    } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $icon_cls = "fas fa-file-image fa-2x";
                        $icon_bg = "bg-purple-50 text-purple-400 group-hover:bg-purple-600 group-hover:text-white";
                    } else {
                        $icon_cls = "fas fa-file-alt fa-2x";
                        $icon_bg = "bg-slate-50 text-slate-400 group-hover:bg-slate-600 group-hover:text-white";
                    }
                    
                    $main_text = $doc['file_name'];
                    
                    // Combine uploader and program code for subtext, which is more useful than just the uploader.
                    $sub_text_parts = [];
                    if (!empty($doc['uploader_name'])) {
                        $sub_text_parts[] = htmlspecialchars($doc['uploader_name']);
                    }
                    if (!empty($doc['program_code'])) {
                        $sub_text_parts[] = htmlspecialchars($doc['program_code']);
                    }
                    $sub_text = implode(' &middot; ', $sub_text_parts);
                }
            ?>
            <div class="bg-white p-4 rounded-lg border border-slate-200 shadow-sm hover:shadow-md transition-all group relative flex flex-col items-center text-center">
                <div class="w-12 h-12 <?= $icon_bg ?> rounded-xl flex items-center justify-center mb-3 transition-colors">
                    <i class="<?= $icon_cls ?>"></i>
                </div>
                
                <h3 class="font-bold text-slate-800 text-sm mb-0.5 truncate w-full" title="<?= htmlspecialchars($main_text) ?>">
                    <?= htmlspecialchars($main_text) ?>
                </h3>
                <p class="text-[10px] text-slate-500 font-medium mb-2 truncate w-full" title="<?= str_replace(' &middot; ', ' | ', $sub_text) ?>"><?= $sub_text ?></p>

                <button onclick="openReviewModal(<?= $doc['doc_id'] ?>, '<?= htmlspecialchars($doc['file_path']) ?>', '<?= htmlspecialchars($doc['file_name']) ?>')" class="w-full py-1.5 bg-slate-50 border border-slate-200 text-slate-600 rounded text-xs font-medium hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition-colors">
                    View
                </button>

                <?php if ($doc['feedback_count'] > 0): ?>
                    <div class="absolute top-2 right-2 bg-red-100 text-red-600 text-[10px] font-bold px-1.5 py-0.5 rounded-full shadow-sm">
                        <?= $doc['feedback_count'] ?>
                    </div>
                <?php endif; ?>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full text-center py-12 text-slate-400">
                <i class="fas fa-folder-open text-5xl mb-4 opacity-50"></i>
                <p class="text-lg font-medium mt-2">No files submitted.</p>
                <p class="text-sm opacity-75">Documents will appear here once approved by the Admin.</p>
            </div>
        <?php endif; ?>
      </div>
    </section>
    </div>
    <?php endif; ?>
    
    <?php elseif ($view === 'self_survey'): ?>
      <?php
        $p_name = "Selected Program";
        if ($selected_program) {
            $p_query = $conn->query("SELECT program_name FROM programs WHERE program_id = $selected_program");
            if ($p_query && $row = $p_query->fetch_assoc()) $p_name = $row['program_name'];
        }
      ?>
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Self Survey Instrument</h1>
            <p class="text-slate-500 text-sm">Assessment Checklist for <strong><?= htmlspecialchars($p_name) ?></strong></p>
        </div>
        <a href="accreditor_dashboard.php?view=documents&college_id=<?= $selected_college ?>&program_id=<?= $selected_program ?>" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors shadow-sm w-full md:w-auto">
            <i class="fas fa-arrow-left mr-2"></i> Back to Categories
        </a>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <div class="grid grid-cols-1 gap-4">
            <?php 
            $survey_areas = [
                'Area I: Vision, Mission, Goals and Objectives',
                'Area II: Faculty',
                'Area III: Curriculum and Instruction',
                'Area IV: Support to Students',
                'Area V: Research',
                'Area VI: Extension and Community Involvement',
                'Area VII: Library',
                'Area VIII: Physical Plant and Facilities',
                'Area IX: Laboratories',
                'Area X: Administration'
            ];
            foreach($survey_areas as $index => $area_name): ?>
            <div onclick="openSurveyModal('<?= htmlspecialchars($area_name) ?>', <?= $index + 1 ?>)" class="p-4 border border-slate-200 rounded-lg hover:border-indigo-300 transition-colors cursor-pointer flex justify-between items-center group hover:bg-indigo-50/50">
                <span class="font-medium text-slate-700 group-hover:text-indigo-700"><?= htmlspecialchars($area_name) ?></span>
                <span class="text-xs bg-slate-100 text-slate-500 px-2 py-1 rounded-full group-hover:bg-indigo-100 group-hover:text-indigo-600 font-medium">View Instrument <i class="fas fa-arrow-right ml-1"></i></span>
            </div>
            <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <!-- Review Modal -->
  <div id="reviewModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-0 md:p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="modalFileName">
    <div class="bg-white md:rounded-xl shadow-2xl w-full max-w-6xl h-full md:h-[90vh] flex flex-col md:flex-row overflow-hidden">
        <!-- Left: Document Viewer -->
        <div class="w-full md:w-2/3 bg-slate-100 border-r border-slate-200 flex flex-col">
            <div class="p-3 border-b border-slate-200 bg-white flex justify-between items-center">
                <h3 class="font-semibold text-slate-800" id="modalFileName">Document Preview</h3>
                <a id="modalDownloadLink" href="#" target="_blank" class="text-sm text-indigo-600 hover:underline"><i class="fas fa-external-link-alt"></i> Open in new tab</a>
            </div>
            <div class="flex-1 relative">
                <iframe id="docPreview" class="w-full h-full absolute inset-0 bg-slate-50" src=""></iframe>
            </div>
        </div>
        
        <!-- Right: Feedback Section -->
        <div class="w-full md:w-1/3 flex flex-col bg-white">
            <div class="p-4 border-b border-slate-200 flex justify-between items-center">
                <h3 class="font-bold text-slate-800">Feedback & Comments</h3>
                <button onclick="document.getElementById('reviewModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-100 rounded-full transition-colors" aria-label="Close modal">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Comments List -->
            <div id="feedbackList" class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50">
                <!-- Comments will be loaded here via JS -->
                <div class="text-center text-slate-400 mt-10">Loading comments...</div>
            </div>
            
            <!-- Add Comment Form -->
            <div class="p-4 border-t border-slate-200 bg-white">
                <form method="POST" action="">
                    <input type="hidden" name="document_id" id="modalDocId">
                    <input type="hidden" name="submit_feedback" value="1">
                    <label for="feedbackText" class="block text-xs font-semibold text-slate-500 uppercase mb-2">Add Feedback</label>
                    <textarea id="feedbackText" name="feedback_text" required class="w-full p-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm resize-none h-24" placeholder="Write your feedback here..."></textarea>
                    <button type="submit" class="mt-3 w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium text-sm transition-colors">
                        Submit Feedback
                    </button>
                </form>
            </div>
        </div>
    </div>
  </div>

  <!-- Self Survey Modal -->
  <div id="surveyModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="surveyModalTitle">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-7xl flex flex-col overflow-hidden h-[90vh]">
        <form method="POST" class="flex flex-col h-full">
        <input type="hidden" name="save_survey" value="1">
        <input type="hidden" name="program_id" value="<?= $selected_program ?>">
        <input type="hidden" name="area_id" id="surveyAreaId">
        <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50 shrink-0">
            <div>
                <h3 class="font-bold text-slate-800 text-lg" id="surveyModalTitle">Self Survey Instrument</h3>
                <p class="text-xs text-slate-500">Rate parameters based on the evidence and instrument provided.</p>
            </div>
            <button type="button" onclick="document.getElementById('surveyModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600" aria-label="Close modal"><i class="fas fa-times text-xl"></i></button>
        </div>
        
        <div class="flex-1 flex overflow-hidden">
            <!-- Left Side: Questions -->
            <div id="surveyContainer" class="flex-1 overflow-y-auto p-6 space-y-5 min-h-0 border-r border-slate-200 bg-slate-50/30">
                <div class="flex justify-center items-center h-full text-slate-400">
                    <i class="fas fa-circle-notch fa-spin mr-2"></i> Loading parameters...
                </div>
            </div>
            
            <!-- Right Side: PDF Viewer (Hidden by default) -->
            <div id="surveyPdfContainer" class="hidden w-1/2 bg-slate-200 flex-col border-l border-slate-300">
                <div class="p-2 bg-white border-b border-slate-200 text-xs font-bold text-slate-600 uppercase tracking-wider text-center flex justify-between items-center px-4">
                    <span><i class="fas fa-file-pdf mr-2 text-red-500"></i> Survey Instrument Reference</span>
                    <a id="surveyPdfLink" href="#" target="_blank" class="text-indigo-600 hover:underline"><i class="fas fa-external-link-alt"></i></a>
                </div>
                <iframe id="surveyPdfFrame" class="flex-1 w-full h-full" src=""></iframe>
            </div>
        </div>

        <div class="p-4 bg-white border-t border-slate-200 flex justify-between items-center shrink-0">
            <div class="text-sm text-slate-500">
                Overall Score: <span class="font-bold text-slate-800">N/A</span>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="document.getElementById('surveyModal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50 text-sm font-medium">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                    <i class="fas fa-save mr-1"></i> Save Ratings
                </button>
            </div>
        </div>
        </form>
    </div>
  </div>

  <script src="js/accreditor_dashboard.js"></script>

  <?php if ($show_welcome): ?>
  <!-- Welcome Animation Overlay -->
  <div id="welcomeOverlay" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/80 backdrop-blur-sm transition-opacity duration-700">
    <div class="bg-white p-10 rounded-3xl shadow-2xl text-center transform scale-90 opacity-0 animate-welcome-in max-w-md w-full mx-4 border-4 border-white/50">
        <div class="w-24 h-24 bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-full flex items-center justify-center mx-auto mb-6 text-4xl shadow-lg animate-bounce-slow">
            <i class="fas fa-user-check"></i>
        </div>
        <h2 class="text-3xl font-bold text-slate-800 mb-2">Welcome Back!</h2>
        <p class="text-xl text-indigo-600 font-medium mb-6"><?= htmlspecialchars($_SESSION['full_name']) ?></p>
        <p class="text-slate-500 text-sm mb-8">Preparing your dashboard...</p>
        
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
