<?php
session_start();
require_once __DIR__ . '/config/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$role = strtolower(trim($_SESSION['role'] ?? ''));
if (strpos($role, 'focal') === false && strpos($role, 'faculty') === false && strpos($role, 'admin') === false) {
  header('Location: role_home.php');
  exit;
}

$user_id = $_SESSION['user_id'];
$program_id = $_SESSION['program_id'] ?? 0;
$full_name = $_SESSION['full_name'];

// Check if notifications table exists to prevent fatal errors
$notifications_enabled = false;
$db_update_message = '';
try {
    // Use SHOW TABLES which is more reliable and doesn't throw an exception on missing table in all configs
    $res = $conn->query("SHOW TABLES LIKE 'user_notifications'");
    if ($res && $res->num_rows > 0) {
        $notifications_enabled = true;
    } else {
        $db_update_message = "<div class='bg-amber-100 text-amber-800 p-3 rounded mb-4'><strong>Database Update Required:</strong> The notifications feature is disabled. Please run the <a href='fix_notifications_schema.php' class='underline font-bold'>database schema fix</a> to enable it.</div>";
    }
} catch (Exception $e) {
    $db_update_message = "<div class='bg-amber-100 text-amber-800 p-3 rounded mb-4'><strong>Database Error:</strong> Could not check for notifications table. Please contact an administrator.</div>";
}

// Handle AJAX Fetch Feedback
if (isset($_GET['fetch_feedback']) && isset($_GET['doc_id'])) {
    $doc_id = intval($_GET['doc_id']);
    // Check column name dynamically just in case
    $col_check = $conn->query("SHOW COLUMNS FROM document_feedback LIKE 'doc_id'");
    $col_name = ($col_check && $col_check->num_rows > 0) ? 'doc_id' : 'document_id';
    
    $query = "SELECT f.*, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) AS full_name, r.role_name 
              FROM document_feedback f 
              JOIN users u ON f.user_id = u.user_id 
              LEFT JOIN roles r ON u.role_id = r.role_id
              WHERE f.$col_name = ? 
              ORDER BY f.created_at DESC";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $feedbacks = $result->fetch_all(MYSQLI_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($feedbacks);
        exit;
    }
}

// Handle AJAX Mark Notifications as Read
if ($notifications_enabled && isset($_GET['action']) && $_GET['action'] === 'mark_read') {
    $user_id = $_SESSION['user_id'];
    // Clear any previous output to ensure valid JSON
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    try {
        $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'cleared' => $stmt->affected_rows]);
        } else {
            echo json_encode(['success' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

// Check for welcome message
$show_welcome = false;
if (isset($_SESSION['show_welcome']) && $_SESSION['show_welcome']) {
    $show_welcome = true;
    unset($_SESSION['show_welcome']);
}

// NEW: Fetch current accreditation level and program code
$current_level = 0;
$program_code = '';
$college_code = ''; // Init college code

if ($program_id) {
    // Updated query to join colleges table
    $level_stmt = $conn->prepare("SELECT c.level, p.program_code, col.college_code 
                                  FROM cycles c 
                                  JOIN programs p ON c.program_id = p.program_id 
                                  JOIN colleges col ON p.college_id = col.college_id 
                                  WHERE c.program_id = ? AND c.status_id = 1 ORDER BY c.valid_from DESC LIMIT 1");
    if ($level_stmt) {
        $level_stmt->bind_param("i", $program_id);
        $level_stmt->execute();
        $level_res = $level_stmt->get_result();
        if ($level_row = $level_res->fetch_assoc()) {
            $current_level = $level_row['level'];
            $program_code = $level_row['program_code'];
            $college_code = strtoupper($level_row['college_code']);
        } else {
            // Fallback to get program code and college if no active cycle
            $p_res = $conn->query("SELECT p.program_code, col.college_code FROM programs p JOIN colleges col ON p.college_id = col.college_id WHERE p.program_id = $program_id");
            if($p_res && $p_row = $p_res->fetch_assoc()) {
                $program_code = $p_row['program_code'];
                $college_code = strtoupper($p_row['college_code']);
            }
        }
    }
}

// Fetch User's Assignments (New Logic)
$assigned_areas = [];
$area_constraints = []; // Maps area_id => allowed types (array or 'ALL')

$sql_assign = "SELECT faa.area_id, faa.type_id, NULL as deadline, a.area_no, a.area_title FROM faculty_area_assignments faa JOIN areas a ON faa.area_id = a.area_id WHERE faa.user_id = ? ORDER BY a.area_no";
$stmt_assign = $conn->prepare($sql_assign);
$stmt_assign->bind_param("i", $user_id);
$stmt_assign->execute();
$res_assign = $stmt_assign->get_result();

while($row = $res_assign->fetch_assoc()) {
    $aid = $row['area_id'];
    $tid = $row['type_id'];
    
    if (!isset($assigned_areas[$aid])) {
        $assigned_areas[$aid] = $row;
        $area_constraints[$aid] = [];
    }

    if ($tid == 0) $area_constraints[$aid] = 'ALL';
    elseif (is_array($area_constraints[$aid])) $area_constraints[$aid][] = $tid;
}

// Get current view
$view = isset($_GET['view']) ? $_GET['view'] : 'documents';

// Handle Document Upload
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc'])) {
    $area_id = intval($_POST['area_id']);
    $type_id = intval($_POST['type_id']);
    
    // Validate Assignment
    $is_allowed = false;
    if (isset($assigned_areas[$area_id])) {
        $allowed_types = $area_constraints[$area_id];
        if ($allowed_types === 'ALL' || in_array($type_id, $allowed_types)) {
            $is_allowed = true;
        }
    }

    if (!$is_allowed) {
        $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Error: You are not assigned to upload this document type for this area.</div>";
    } elseif (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $file_name = basename($_FILES['doc_file']['name']);
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $target_file = $target_dir . time() . "_" . $file_name;
        $uploadSuccess = false;
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Automatic Image Compression & Resizing
        if (in_array($file_ext, ['jpg', 'jpeg', 'png']) && extension_loaded('gd')) {
            $source = $_FILES['doc_file']['tmp_name'];
            $img = null;
            
            if ($file_ext == 'png') $img = @imagecreatefrompng($source);
            else $img = @imagecreatefromjpeg($source);

            if ($img) {
                // Resize if width is larger than 1600px
                $width = imagesx($img);
                $height = imagesy($img);
                $max_width = 1600;

                if ($width > $max_width) {
                    $new_width = $max_width;
                    $new_height = floor($height * ($max_width / $width));
                    $tmp_img = imagecreatetruecolor($new_width, $new_height);
                    
                    // Preserve transparency for PNG
                    if ($file_ext == 'png') {
                        imagealphablending($tmp_img, false);
                        imagesavealpha($tmp_img, true);
                    }
                    
                    imagecopyresampled($tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                    imagedestroy($img);
                    $img = $tmp_img;
                }

                // Save optimized image (Quality: 75 for JPG, 8 for PNG)
                if ($file_ext == 'png') $uploadSuccess = imagepng($img, $target_file, 8);
                else $uploadSuccess = imagejpeg($img, $target_file, 75);
                
                imagedestroy($img);
            }
        }

        // Fallback: Move original file if not an image or if compression failed
        if (!$uploadSuccess) {
            $uploadSuccess = move_uploaded_file($_FILES['doc_file']['tmp_name'], $target_file);
        }
        
        if ($uploadSuccess) {
            // Get or Create Cycle
            $cycle_query = $conn->query("SELECT cycle_id FROM cycles WHERE program_id = $program_id ORDER BY cycle_id DESC LIMIT 1");
            if ($cycle_query && $cycle_query->num_rows > 0) {
                $cycle_id = $cycle_query->fetch_assoc()['cycle_id'];
            } else {
                $conn->query("INSERT INTO cycles (program_id, level, status_id, valid_from) VALUES ($program_id, 1, 1, NOW())");
                $cycle_id = $conn->insert_id;
            }

            $stmt = $conn->prepare("INSERT INTO documents (cycle_id, area_id, file_name, file_path, type_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissii", $cycle_id, $area_id, $file_name, $target_file, $type_id, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_msg'] = "Document uploaded successfully!";
                $_SESSION['flash_type'] = "success";
                header("Location: focal_dashboard.php?view=documents");
                exit;
            } else {
                $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Database Error: " . $stmt->error . "</div>";
            }
        } else {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Failed to move file.</div>";
        }
    }
}


// Fetch Document Types (Safe Mode)
$types = $conn->query("SELECT * FROM document_types ORDER BY type_name");
$types_list = [];
if ($types) {
    while($t = $types->fetch_assoc()) $types_list[] = $t;
}

// Fetch User's Documents
$my_docs = [];

// Check if feedback table exists and which column to use (doc_id vs document_id)
$check_fb = $conn->query("SHOW COLUMNS FROM document_feedback LIKE 'doc_id'");
$fb_col = ($check_fb && $check_fb->num_rows > 0) ? 'doc_id' : 'document_id';

$unread_notifs_col = $notifications_enabled ? ", (SELECT COUNT(*) FROM user_notifications un WHERE un.doc_id = d.doc_id AND un.user_id = d.uploaded_by AND un.is_read = 0) as unread_notifs" : ", 0 as unread_notifs";

$query = "SELECT d.doc_id, d.file_name, d.file_path, d.reviewed_file_path, d.uploaded_at, d.status, d.area_id, d.type_id,
          a.area_no, a.area_title, dt.type_name,
          (SELECT COUNT(*) FROM document_feedback WHERE $fb_col = d.doc_id) as feedback_count
          $unread_notifs_col
          FROM documents d 
          JOIN areas a ON d.area_id = a.area_id 
          LEFT JOIN document_types dt ON d.type_id = dt.type_id
          WHERE d.uploaded_by = ? 
          ORDER BY d.uploaded_at DESC";
$stmt = $conn->prepare($query);
if (!$stmt) {
    // Fallback if query fails (e.g. table missing)
    $my_docs = []; 
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while($row = $result->fetch_assoc()) $my_docs[] = $row;
    }
}

// Calculate notifications (Documents with reviews or feedback)
$review_notification_count = 0;
if ($notifications_enabled) {
    try {
        $stmt_count = $conn->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0");
        if ($stmt_count) {
            $stmt_count->bind_param("i", $user_id);
            $stmt_count->execute();
            $review_notification_count = $stmt_count->get_result()->fetch_row()[0] ?? 0;
        }
    } catch (Exception $e) {
        // Ignore missing table error to prevent crash
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Faculty Dashboard</title>
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

    // Table Theme Defaults
    $table_thead_bg = 'bg-slate-50';
    $table_thead_text = 'text-slate-600';
    $table_hover_row = 'hover:bg-slate-50';

    // Topbar Theme Defaults
    $topbar_bg = 'linear-gradient(135deg, #6c63ff, #3b4ba8)'; // Default Indigo
    $logout_hover_bg = '#312e81'; // Indigo-900

    // Section Header Defaults
    $section_header_text = 'text-slate-800';
    $section_icon_color = 'text-indigo-600';

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
        
        $section_header_text = 'text-green-800';
        $section_icon_color = 'text-green-600';
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
        
        $section_header_text = 'text-rose-800';
        $section_icon_color = 'text-rose-600';
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
        
        $section_header_text = 'text-amber-800';
        $section_icon_color = 'text-amber-600';
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
        
        $section_header_text = 'text-blue-800';
        $section_icon_color = 'text-blue-600';
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
        <i class="fas fa-shield-alt"></i> <span>Faculty Dashboard</span>
      </div>
    </div>
    <div class="right">
      <?php if ($notifications_enabled): ?>
      <!-- Notification Bell -->
      <div class="relative mr-4 group">
          <button id="notifBtn" class="text-slate-500 hover:text-indigo-600 transition-colors relative">
              <i class="fas fa-bell fa-lg"></i>
              <?php if($review_notification_count > 0): ?>
                  <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center border-2 border-slate-50">
                      <?= $review_notification_count > 9 ? '9+' : $review_notification_count ?>
                  </span>
              <?php endif; ?>
          </button>
          <div class="absolute right-0 top-full mt-2 w-72 bg-white border border-slate-200 rounded-xl shadow-xl hidden group-hover:block z-50">
              <div class="p-3 border-b border-slate-100 font-bold text-xs text-slate-500 uppercase">Notifications</div>
              <div class="max-h-64 overflow-y-auto">
                  <?php 
                  $n_stmt = $conn->prepare("SELECT * FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                  $n_stmt->bind_param("i", $user_id);
                  $n_stmt->execute();
                  $n_res = $n_stmt->get_result();
                  if($n_res->num_rows > 0):
                      while($notif = $n_res->fetch_assoc()): 
                          $n_bg = $notif['is_read'] ? 'bg-white' : 'bg-indigo-50';
                  ?>
                      <div class="notification-item p-3 border-b border-slate-50 text-sm <?= $n_bg ?>">
                          <p class="text-slate-700"><?= htmlspecialchars($notif['message']) ?></p>
                          <span class="text-xs text-slate-400"><?= date('M d, h:i A', strtotime($notif['created_at'])) ?></span>
                      </div>
                  <?php endwhile; else: ?>
                      <div class="p-4 text-center text-slate-400 text-xs">No notifications</div>
                  <?php endif; ?>
              </div>
              <?php if($review_notification_count > 0): ?>
              <div class="p-2 text-center border-t border-slate-100">
                  <button type="button" id="markAsReadBtn" class="text-xs text-indigo-600 font-bold hover:underline">Mark all as read</button>
              </div>
              <?php endif; ?>
          </div>
      </div>
      <?php endif; ?>
      <div class="user-profile">
        <div class="user-info hidden sm:block">
          <span class="user-name"><?= htmlspecialchars($full_name) ?></span>
          <span class="user-role">Faculty / Focal Person</span>
        </div>
        <?php if (!empty($_SESSION['avatar_path']) && file_exists($_SESSION['avatar_path'])): ?>
            <img src="<?= htmlspecialchars($_SESSION['avatar_path']) ?>" alt="Avatar" class="user-avatar" style="object-fit: cover;">
        <?php else: ?>
            <div class="user-avatar">
              <?= strtoupper(substr($full_name, 0, 1)) ?>
            </div>
        <?php endif; ?>
      </div>
      <div class="divider-vertical"></div>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <aside class="sidebar">
    <nav class="sidebar-nav">
      <a href="?view=documents" class="nav-item <?= $view === 'documents' ? 'active' : '' ?> flex justify-between items-center group">
          <div class="flex items-center gap-3">
            <i class="fas fa-folder-plus"></i> <span>My Documents</span>
          </div>
          <?php if($review_notification_count > 0): ?>
            <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm group-hover:bg-red-600 transition-colors" title="<?= $review_notification_count ?> documents have reviews/feedback"><?= $review_notification_count ?></span>
          <?php endif; ?>
      </a>
      <a href="?view=history" class="nav-item <?= $view === 'history' ? 'active' : '' ?>"><i class="fas fa-history"></i> Upload History</a>
      <a href="?view=self_survey" class="nav-item <?= $view === 'self_survey' ? 'active' : '' ?>"><i class="fas fa-tasks"></i> Self Survey</a>
    </nav>
    <div class="mt-auto p-4">
        <a href="profile.php" class="nav-item"><i class="fas fa-user-cog"></i> Manage Profile</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="dashboard-container">
        <?php if($db_update_message) echo $db_update_message; ?>
        <?php if($message) echo $message; ?>
        
        <?php if ($view === 'documents'): ?>
        <!-- Upload Section -->
        <section class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm mb-8">
            <h2 class="text-lg font-bold <?= $section_header_text ?> mb-1"><i class="fas fa-cloud-upload-alt <?= $section_icon_color ?>"></i> Upload New Document</h2>
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
                <span>Program: <strong class="text-slate-700"><?= htmlspecialchars($program_code) ?></strong></span>
                <span class="mx-1">|</span>
                <span>Current Accreditation: 
                    <?php if($current_level > 0): ?>
                        <span class="font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-full text-xs">Level <?= $current_level ?></span>
                    <?php else: ?>
                        <span class="font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded-full text-xs">Not Started</span>
                    <?php endif; ?>
                </span>
            </div>

            <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Area</label>
                    <?php if (!empty($assigned_areas)): ?>
                        <select name="area_id" id="areaSelect" required class="w-full p-2.5 border border-slate-300 rounded-lg outline-none focus:border-indigo-500" onchange="updateDocTypes()">
                            <?php foreach($assigned_areas as $area): ?>
                                <option value="<?= $area['area_id'] ?>">Area <?= $area['area_no'] ?>: <?= htmlspecialchars($area['area_title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <div class="w-full p-2.5 border border-red-200 bg-red-50 rounded-lg text-red-600 text-sm font-medium flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i> No Area Assigned
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Document Type</label>
                    <div id="typeSelectContainer">
                        <select name="type_id" id="typeSelect" required class="w-full p-2.5 border border-slate-300 rounded-lg outline-none focus:border-indigo-500">
                            <option value="">Select Area First...</option>
                        </select>
                    </div>
                    <div id="fixedTypeDisplay" class="hidden w-full p-2.5 border border-slate-200 bg-slate-50 rounded-lg text-slate-600 font-medium flex items-center"><i class="fas fa-lock mr-2 text-slate-400"></i> <span></span></div>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Select File</label>
                    <input type="file" name="doc_file" required class="w-full p-2 border border-slate-300 rounded-lg">
                    <p class="text-xs text-slate-500 mt-1"><i class="fas fa-info-circle"></i> Images will be automatically optimized to save storage.</p>
                </div>
                <div class="md:col-span-2 text-right">
                    <?php if (!empty($assigned_areas)): ?>
                        <button type="submit" name="upload_doc" class="btn btn-primary">Upload Document</button>
                    <?php else: ?>
                        <button type="button" disabled class="btn bg-slate-300 text-slate-500 cursor-not-allowed">Upload Disabled</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- Documents List -->
        <section class="panel">
            <h2 class="text-lg font-bold <?= $section_header_text ?> mb-4">My Documents</h2>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="<?= $table_thead_bg ?> <?= $table_thead_text ?> font-semibold border-b border-slate-200">
                        <tr><th class="p-4">File</th><th class="p-4">Area</th><th class="p-4">Date</th><th class="p-4">Feedback / Review</th><th class="p-4 text-right">Action</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php $recent_docs = array_slice($my_docs, 0, 5); ?>
                        <?php foreach ($recent_docs as $doc): ?>
                        <tr class="<?= $table_hover_row ?> <?= ($doc['unread_notifs'] > 0) ? 'bg-indigo-50/50' : '' ?>">
                            <td class="p-4 font-medium relative">
                                <?php if($doc['unread_notifs'] > 0): ?><span class="absolute left-1 top-1/2 -translate-y-1/2 w-1.5 h-1.5 bg-red-500 rounded-full" title="New Activity"></span><?php endif; ?>
                                <?= htmlspecialchars($doc['file_name']) ?>
                            </td>
                            <td class="p-4">Area <?= $doc['area_no'] ?></td>
                            <td class="p-4 text-slate-500"><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
                            <td class="p-4">
                                <div class="flex flex-col items-start gap-1">
                                    <?php if(!empty($doc['reviewed_file_path'])): ?>
                                        <a href="<?= htmlspecialchars($doc['reviewed_file_path']) ?>" target="_blank" class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 px-2 py-1 rounded border border-emerald-200 font-bold hover:bg-emerald-100 transition-colors text-xs">
                                            <i class="fas fa-file-download"></i> Admin Review
                                        </a>
                                    <?php endif; ?>
                                    <?php if($doc['feedback_count'] > 0): ?>
                                        <button onclick="openFeedbackModal(<?= $doc['doc_id'] ?>, '<?= htmlspecialchars($doc['file_name']) ?>')" class="text-amber-600 text-xs font-bold hover:underline inline-flex items-center gap-1">
                                            <i class="fas fa-comment"></i> <?= $doc['feedback_count'] ?> Comments
                                        </button>
                                    <?php endif; ?>
                                    <?php if(empty($doc['reviewed_file_path']) && $doc['feedback_count'] == 0): ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-4 text-right">
                                <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="text-slate-500 hover:text-indigo-600 mr-3" title="View Original"><i class="fas fa-eye"></i></a>
                                <button onclick="reviseDocument(<?= $doc['area_id'] ?>, <?= $doc['type_id'] ?>)" class="text-indigo-600 font-semibold hover:text-indigo-800 border border-indigo-200 bg-indigo-50 px-3 py-1 rounded-lg text-xs transition-colors">
                                    <i class="fas fa-sync-alt"></i> Revise
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if(count($my_docs) > 5): ?>
                <div class="p-4 border-t border-slate-100 text-center bg-slate-50">
                    <a href="?view=history" class="text-indigo-600 font-medium text-sm hover:underline">View Full History</a>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php elseif ($view === 'history'): ?>
        <!-- History Section -->
        <section class="panel">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-slate-800">Upload History</h2>
                <div class="text-sm text-slate-500">Total Uploads: <?= count($my_docs) ?></div>
            </div>
            
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="<?= $table_thead_bg ?> <?= $table_thead_text ?> font-semibold border-b border-slate-200">
                        <tr>
                            <th class="p-4">Date & Time</th>
                            <th class="p-4">File Name</th>
                            <th class="p-4">Area</th>
                            <th class="p-4">Type</th>
                            <th class="p-4">Review / Feedback</th>
                            <th class="p-4 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($my_docs)): ?>
                            <tr><td colspan="6" class="p-6 text-center text-slate-500">No history found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($my_docs as $doc): ?>
                            <tr class="<?= $table_hover_row ?>">
                                <td class="p-4 text-slate-500 whitespace-nowrap"><?= date('M d, Y h:i A', strtotime($doc['uploaded_at'])) ?></td>
                                <td class="p-4 font-medium text-slate-800">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-file-alt text-slate-400"></i>
                                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="hover:text-indigo-600 hover:underline"><?= htmlspecialchars($doc['file_name']) ?></a>
                                    </div>
                                </td>
                                <td class="p-4">Area <?= $doc['area_no'] ?></td>
                                <td class="p-4"><span class="px-2 py-1 bg-slate-100 rounded text-xs"><?= htmlspecialchars($doc['type_name']) ?></span></td>
                                <td class="p-4">
                                    <div class="flex flex-col items-start gap-1">
                                        <?php if(!empty($doc['reviewed_file_path'])): ?>
                                            <a href="<?= htmlspecialchars($doc['reviewed_file_path']) ?>" target="_blank" class="inline-flex items-center gap-1 text-emerald-600 font-bold hover:underline text-xs">
                                                <i class="fas fa-file-download"></i> Download Review
                                            </a>
                                        <?php endif; ?>
                                        <?php if($doc['feedback_count'] > 0): ?>
                                            <button onclick="openFeedbackModal(<?= $doc['doc_id'] ?>, '<?= htmlspecialchars($doc['file_name']) ?>')" class="text-amber-600 text-xs font-bold hover:underline inline-flex items-center gap-1">
                                                <i class="fas fa-comment"></i> <?= $doc['feedback_count'] ?> Comments
                                            </button>
                                        <?php endif; ?>
                                        <?php if(empty($doc['reviewed_file_path']) && $doc['feedback_count'] == 0): ?>
                                            <span class="text-slate-400 text-xs">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-4 text-right">
                                    <?php if(($doc['status'] ?? 'pending') === 'approved'): ?>
                                        <span class="text-emerald-600 font-medium text-xs bg-emerald-50 px-2 py-1 rounded-full">Approved</span>
                                    <?php else: ?>
                                        <span class="text-slate-500 font-medium text-xs bg-slate-100 px-2 py-1 rounded-full">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php elseif ($view === 'self_survey'): ?>
        <!-- Self Survey Section -->
        <section class="panel">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-800">Self Survey Assessment</h1>
                <p class="text-slate-500 text-sm">Evaluate your program's readiness for your assigned area.</p>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg text-slate-700">Area Assessment Checklist</h3>
                    <button class="btn btn-primary small"><i class="fas fa-save"></i> Save Progress</button>
                </div>
                
                <div class="grid grid-cols-1 gap-4">
                    <?php 
                    if (!empty($assigned_areas)):
                        foreach ($assigned_areas as $assigned_area):
                            $aid = $assigned_area['area_id'];
                            $pid = $program_id;
                            $area_display = "Area " . $assigned_area['area_no'] . ": " . $assigned_area['area_title'];
                            
                            // Check if ratings exist
                            $has_internal_ratings = false;
                            try {
                                $q_rate = $conn->prepare("SELECT COUNT(*) FROM survey_ratings WHERE program_id = ? AND area_id = ? AND accreditor_type = 'internal'");
                                $q_rate->bind_param("ii", $pid, $aid);
                                $q_rate->execute();
                                $res_rate = $q_rate->get_result();
                                if ($res_rate && $res_rate->fetch_row()[0] > 0) {
                                    $has_internal_ratings = true;
                                }
                            } catch (Exception $e) {
                                // Table missing or DB error, ignore to prevent crash
                            }
                        ?>
                            <div class="p-4 border border-slate-200 rounded-lg hover:border-indigo-300 transition-colors cursor-pointer flex justify-between items-center group">
                                <span class="font-medium text-slate-700 group-hover:text-indigo-700"><?= htmlspecialchars($area_display) ?></span>
                                <?php if ($has_internal_ratings): ?>
                                    <a href="print_survey.php?program_id=<?= $pid ?>&area_id=<?= $aid ?>&type=internal" target="_blank" class="text-xs bg-emerald-100 text-emerald-700 px-3 py-1.5 rounded-full font-bold hover:bg-emerald-200 transition-colors">
                                        <i class="fas fa-file-pdf mr-1"></i> View Internal Scores (PDF)
                                    </a>
                                <?php else: ?>
                                    <span class="text-xs bg-slate-100 text-slate-500 px-2 py-1 rounded group-hover:bg-indigo-50 group-hover:text-indigo-600">Pending Internal Evaluation <i class="fas fa-clock ml-1"></i></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center border border-dashed border-slate-300 rounded-lg bg-slate-50">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-100 text-slate-400 mb-3">
                                <i class="fas fa-ban text-xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-slate-900">No Area Assigned</h3>
                            <p class="text-slate-500 mt-1">Please contact your Chairperson to be assigned an area for assessment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </div>
  </main>

  <script>
    // Data from PHP
    const areaConstraints = <?= json_encode($area_constraints) ?>;
    const allTypes = <?= json_encode($types_list) ?>;

    function updateDocTypes() {
        const areaSelect = document.getElementById('areaSelect');
        const typeSelect = document.getElementById('typeSelect');
        const typeContainer = document.getElementById('typeSelectContainer');
        const fixedDisplay = document.getElementById('fixedTypeDisplay');
        
        if (!areaSelect || !typeSelect) return;

        const selectedAreaId = areaSelect.value;
        const allowed = areaConstraints[selectedAreaId];

        // Clear current options
        typeSelect.innerHTML = '';
        let validOptions = [];

        allTypes.forEach(type => {
            // If allowed is 'ALL' or the type ID is in the allowed array
            if (allowed === 'ALL' || (Array.isArray(allowed) && allowed.includes(parseInt(type.type_id)))) {
                validOptions.push(type);
                const option = document.createElement('option');
                option.value = type.type_id;
                option.textContent = type.type_name;
                typeSelect.appendChild(option);
            }
        });

        // UX: If only one option, select it and show as fixed text
        if (validOptions.length === 1) {
             typeSelect.value = validOptions[0].type_id;
             typeContainer.classList.add('hidden');
             fixedDisplay.querySelector('span').textContent = validOptions[0].type_name;
             fixedDisplay.classList.remove('hidden');
        } else {
             typeContainer.classList.remove('hidden');
             fixedDisplay.classList.add('hidden');
             
             if (validOptions.length === 0) {
                 const option = document.createElement('option');
                 option.textContent = "No types assigned";
                 typeSelect.appendChild(option);
             }
        }
    }

    // Initialize on load
    document.addEventListener('DOMContentLoaded', updateDocTypes);

    function reviseDocument(areaId, typeId) {
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
        // Pre-fill form
        document.querySelector('select[name="area_id"]').value = areaId;
        document.querySelector('select[name="type_id"]').value = typeId;
        // Highlight form
        const form = document.querySelector('form');
        form.classList.add('ring-2', 'ring-indigo-500', 'ring-offset-2');
        setTimeout(() => form.classList.remove('ring-2', 'ring-indigo-500', 'ring-offset-2'), 2000);
    }
  </script>

  <?php if ($show_welcome): ?>
  <!-- Welcome Animation Overlay -->
  <div id="welcomeOverlay" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/80 backdrop-blur-sm transition-opacity duration-700">
    <div class="bg-white p-10 rounded-3xl shadow-2xl text-center transform scale-90 opacity-0 animate-welcome-in max-w-md w-full mx-4 border-4 border-white/50">
        <div class="w-24 h-24 bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-full flex items-center justify-center mx-auto mb-6 text-4xl shadow-lg animate-bounce-slow">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <h2 class="text-3xl font-bold text-slate-800 mb-2">Welcome Back!</h2>
        <p class="text-xl text-indigo-600 font-medium mb-6"><?= htmlspecialchars($_SESSION['full_name']) ?></p>
        <p class="text-slate-500 text-sm mb-8">Loading your documents...</p>
        
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

<!-- Feedback Modal -->
<div id="feedbackModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg flex flex-col overflow-hidden max-h-[80vh]">
        <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-white shrink-0">
            <h3 class="font-bold text-slate-800 truncate pr-4" id="feedbackModalTitle">Feedback</h3>
            <button onclick="document.getElementById('feedbackModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="feedbackModalList" class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50">
            <!-- Content loaded via JS -->
        </div>
        <div class="p-4 border-t border-slate-200 bg-white text-right shrink-0">
            <button onclick="document.getElementById('feedbackModal').classList.add('hidden')" class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-100 text-sm font-medium transition-colors">Close</button>
        </div>
    </div>
</div>

<!-- Self Survey Modal -->
<div id="surveyModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-7xl flex flex-col overflow-hidden h-[90vh]">
      <form method="POST" class="flex flex-col h-full">
      <input type="hidden" name="save_survey" value="1">
      <input type="hidden" name="program_id" value="<?= $program_id ?>">
      <input type="hidden" name="area_id" id="surveyAreaId">
      <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50 shrink-0">
          <div>
              <h3 class="font-bold text-slate-800 text-lg" id="surveyModalTitle">Self Survey Instrument</h3>
              <p class="text-xs text-slate-500">Rate parameters based on the evidence and instrument provided.</p>
          </div>
          <button type="button" onclick="document.getElementById('surveyModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-xl"></i></button>
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
          <div class="text-sm text-slate-500"></div>
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

<script>
  function openSurveyModal(areaName, areaId) {
      const modal = document.getElementById('surveyModal');
      document.getElementById('surveyModalTitle').textContent = areaName;
      document.getElementById('surveyAreaId').value = areaId;
      const container = document.getElementById('surveyContainer');
      const pdfContainer = document.getElementById('surveyPdfContainer');
      const pdfFrame = document.getElementById('surveyPdfFrame');
      const pdfLink = document.getElementById('surveyPdfLink');
      const programId = <?= $program_id ?>;

      container.innerHTML = '<div class="flex justify-center items-center h-full text-slate-400"><i class="fas fa-circle-notch fa-spin mr-2"></i> Loading parameters...</div>';
      pdfContainer.classList.add('hidden');

      fetch(`focal_dashboard.php?fetch_survey_params=1&area_id=${areaId}&program_id=${programId}`)
          .then(res => res.json())
          .then(data => {
              const params = data.params;
              const file = data.file;

              if (file) {
                  pdfFrame.src = file;
                  pdfLink.href = file;
                  pdfContainer.classList.remove('hidden');
              }

              if(params.length === 0) {
                  container.innerHTML = '<div class="text-center text-slate-400 py-10">No parameters defined for this area yet.</div>';
                  return;
              }
              
              let html = `
              <div class="border border-slate-200 rounded-lg overflow-hidden bg-white shadow-sm">
                  <table class="w-full text-sm text-left">
                      <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                          <tr>
                              <th class="px-6 py-3 w-2/3">Parameter / Criteria</th>
                              <th class="px-6 py-3 text-center">Rating</th>
                          </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100">`;

              params.forEach((item, index) => {
                  html += `
                  <tr class="hover:bg-slate-50 transition-colors">
                      <td class="px-6 py-4 align-top">
                          <div class="flex gap-3">
                              <span class="text-xs font-bold text-slate-400 mt-1">#${index + 1}</span>
                              <p class="text-slate-700 font-medium leading-relaxed">${item.parameter_text}</p>
                          </div>
                      </td>
                      <td class="px-6 py-4 align-top">
                          <div class="flex items-center justify-center gap-1 bg-slate-50/50 p-1.5 rounded-lg border border-slate-100">`;
                              
                  for(let j=1; j<=5; j++) {
                      const checked = (parseInt(item.current_rating) === j) ? 'checked' : '';
                      let activeClass = 'peer-checked:bg-indigo-500 peer-checked:border-indigo-500';
                      if(j <= 2) activeClass = 'peer-checked:bg-red-500 peer-checked:border-red-500';
                      else if(j === 3) activeClass = 'peer-checked:bg-amber-500 peer-checked:border-amber-500';
                      else activeClass = 'peer-checked:bg-emerald-500 peer-checked:border-emerald-500';

                      html += `<label class="cursor-pointer relative group">
                                  <input type="radio" name="rating_param_${item.param_id}" value="${j}" class="peer sr-only" ${checked}>
                                  <div class="w-9 h-9 flex items-center justify-center rounded-md border border-slate-200 text-slate-400 font-bold text-sm transition-all hover:bg-white hover:shadow-sm hover:border-slate-300 bg-white ${activeClass} peer-checked:text-white shadow-sm">
                                      ${j}
                                  </div>
                              </label>`;
                  }
                  
                  html += `   </div>
                      </td>
                  </tr>`;
              });
              
              html += `</tbody></table></div>`;
              container.innerHTML = html;
          })
          .catch(err => {
              console.error(err);
              container.innerHTML = '<div class="text-center text-red-500 py-4">Error loading survey parameters.</div>';
          });
          
      modal.classList.remove('hidden');
  }

  function openFeedbackModal(docId, fileName) {
      const modal = document.getElementById('feedbackModal');
      const title = document.getElementById('feedbackModalTitle');
      const list = document.getElementById('feedbackModalList');
      
      title.textContent = 'Feedback: ' + fileName;
      list.innerHTML = '<div class="flex justify-center py-8"><i class="fas fa-circle-notch fa-spin text-indigo-500 text-2xl"></i></div>';
      
      modal.classList.remove('hidden');
      
      fetch(`focal_dashboard.php?fetch_feedback=1&doc_id=${docId}`)
          .then(res => res.json())
          .then(data => {
              if (data.length === 0) {
                  list.innerHTML = '<div class="text-center text-slate-400 py-8">No feedback found.</div>';
                  return;
              }
              
              list.innerHTML = data.map(item => {
                  const date = new Date(item.created_at).toLocaleDateString('en-US', {
                      year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
                  });
                  
                  let roleBadge = 'bg-slate-200 text-slate-600';
                  if ((item.role_name || '').toLowerCase().includes('admin')) roleBadge = 'bg-red-100 text-red-700';
                  else if ((item.role_name || '').toLowerCase().includes('dean')) roleBadge = 'bg-indigo-100 text-indigo-700';
                  else if ((item.role_name || '').toLowerCase().includes('chair')) roleBadge = 'bg-amber-100 text-amber-700';
                  else if ((item.role_name || '').toLowerCase().includes('accreditor')) roleBadge = 'bg-emerald-100 text-emerald-700';

                  return `
                  <div class="bg-white p-4 rounded-lg border border-slate-200 shadow-sm">
                      <div class="flex justify-between items-start mb-2">
                          <div class="flex items-center gap-2">
                              <span class="font-bold text-slate-700 text-sm">${item.full_name || 'Unknown User'}</span>
                              <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase ${roleBadge}">${item.role_name || 'User'}</span>
                          </div>
                          <span class="text-xs text-slate-400">${date}</span>
                      </div>
                      <p class="text-sm text-slate-600 whitespace-pre-wrap leading-relaxed">${item.feedback_text}</p>
                  </div>
                  `;
              }).join('');
          })
          .catch(err => {
              console.error(err);
              list.innerHTML = '<div class="text-center text-red-500 py-8">Error loading feedback.</div>';
          });
  }

  // Mark as Read button logic
  document.addEventListener('DOMContentLoaded', function() {
      const markAsReadBtn = document.getElementById('markAsReadBtn');
      if (markAsReadBtn) {
          markAsReadBtn.addEventListener('click', function(e) {
              e.preventDefault();
              e.stopPropagation();
              
              fetch('focal_dashboard.php?action=mark_read')
                  .then(res => res.json())
                  .then(data => {
                      if (data.success) {
                          const badge = document.getElementById('notificationBadge');
                          if (badge) badge.style.display = 'none';
                          markAsReadBtn.style.display = 'none';
                          
                          // Update UI to show as read immediately
                          document.querySelectorAll('.notification-item').forEach(item => {
                              item.classList.remove('bg-indigo-50');
                              item.classList.add('bg-white');
                          });
                      }
                  })
                  .catch(err => {
                      console.error('Failed to mark notifications as read:', err);
                  });
          });
      }
  });
</script>
  <?php endif; ?>

  <?php if (isset($_SESSION['flash_msg'])): 
      $flash_msg = $_SESSION['flash_msg'];
      $flash_type = $_SESSION['flash_type'] ?? 'success';
      unset($_SESSION['flash_msg']);
      unset($_SESSION['flash_type']);

      $toast_bg = $flash_type === 'error' ? 'bg-red-600' : 'bg-emerald-600';
      $toast_icon = $flash_type === 'error' ? 'fa-times-circle' : 'fa-check-circle';
  ?>
  <div id="flashToast" class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 <?= $toast_bg ?> text-white py-6 px-10 rounded-2xl shadow-2xl flex flex-col items-center justify-center gap-3 z-[100] opacity-0 scale-90 transition-all duration-300 ease-out">
      <i class="fas <?= $toast_icon ?> text-4xl"></i>
      <span class="font-bold text-xl text-center"><?= htmlspecialchars($flash_msg) ?></span>
  </div>

  <script>
      document.addEventListener('DOMContentLoaded', function() {
          const toast = document.getElementById('flashToast');
          if (toast) {
              // Animate in
              setTimeout(() => {
                  toast.classList.remove('opacity-0', 'scale-90');
              }, 100);

              // Animate out after a delay
              setTimeout(() => {
                  toast.classList.add('opacity-0', 'scale-90');
                  setTimeout(() => toast.remove(), 300);
              }, 3500); // 3.5 seconds
          }
      });
  </script>
  <?php endif; ?>
</body>
</html>
