<?php
session_start();
require_once __DIR__ . '/config/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');

// Fetch Documents
$search = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$college_filter = isset($_GET['college_id']) ? intval($_GET['college_id']) : 0;
$area_filter = isset($_GET['area_id']) ? intval($_GET['area_id']) : 0;

// Handle Document Approval
if (isset($_GET['approve_id'])) {
    $doc_id = intval($_GET['approve_id']);
    $stmt = $conn->prepare("UPDATE documents SET status = 'approved' WHERE doc_id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    header("Location: documents.php?msg=approved");
    exit;
}

// Handle Document Unapproval
if (isset($_GET['unapprove_id'])) {
    $doc_id = intval($_GET['unapprove_id']);
    $stmt = $conn->prepare("UPDATE documents SET status = 'pending' WHERE doc_id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    header("Location: documents.php?msg=unapproved");
    exit;
}

// Handle Document Upload
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc'])) {
    $program_id = intval($_POST['program_id']);
    $area_id = intval($_POST['area_id']);
    $type_id = intval($_POST['type_id']);
    $user_id = $_SESSION['user_id'];

    if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $file_name = basename($_FILES['doc_file']['name']);
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $target_file = $target_dir . time() . "_" . $file_name;
        
        if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $target_file)) {
            // Get or Create Cycle for the selected program
            $cycle_query = $conn->query("SELECT cycle_id FROM cycles WHERE program_id = $program_id ORDER BY cycle_id DESC LIMIT 1");
            if ($cycle_query && $cycle_query->num_rows > 0) {
                $cycle_id = $cycle_query->fetch_assoc()['cycle_id'];
            } else {
                // Create new cycle if none exists
                $conn->query("INSERT INTO cycles (program_id, level, status_id, valid_from) VALUES ($program_id, 1, 1, NOW())");
                $cycle_id = $conn->insert_id;
            }

            $stmt = $conn->prepare("INSERT INTO documents (cycle_id, area_id, file_name, file_path, type_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissii", $cycle_id, $area_id, $file_name, $target_file, $type_id, $user_id);
            
            if ($stmt->execute()) {
                $msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>✅ Document uploaded successfully!</div>";
            } else {
                $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Database Error: " . $stmt->error . "</div>";
            }
        } else {
            $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Failed to move file.</div>";
        }
    }
}

// Handle Feedback/Remarks Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_remark'])) {
    $doc_id = intval($_POST['doc_id']);
    $remark = trim($_POST['remark_text']);
    $user_id = $_SESSION['user_id'];

    if ($doc_id && $remark) {
        // Get document uploader to notify them
        $uploader_id = null;
        $stmt_get_uploader = $conn->prepare("SELECT uploaded_by FROM documents WHERE doc_id = ?");
        $stmt_get_uploader->bind_param("i", $doc_id);
        $stmt_get_uploader->execute();
        $res_uploader = $stmt_get_uploader->get_result();
        if ($res_uploader && $row = $res_uploader->fetch_assoc()) {
            $uploader_id = $row['uploaded_by'];
        }

        $stmt = $conn->prepare("INSERT INTO document_feedback (doc_id, user_id, feedback_text) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iis", $doc_id, $user_id, $remark);
            if ($stmt->execute()) {
                $msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>✅ Remark added successfully!</div>";
                // Create notification for the uploader, if it's not the admin themselves
                if ($uploader_id && $uploader_id != $user_id) {
                    $stmt_notif = $conn->prepare("INSERT INTO user_notifications (user_id, doc_id, message) VALUES (?, ?, 'Admin added a remark on your document.')");
                    $stmt_notif->bind_param("ii", $uploader_id, $doc_id);
                    $stmt_notif->execute();
                }
            }
            else $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Error: " . $stmt->error . "</div>";
        } else {
            $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Database Error: " . $conn->error . "</div>";
        }
    }
}

// Handle Review File Upload (Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_review'])) {
    $doc_id = intval($_POST['doc_id']);
    
    if (isset($_FILES['review_file']) && $_FILES['review_file']['error'] === UPLOAD_ERR_OK) {
        $file_name = "review_" . time() . "_" . basename($_FILES['review_file']['name']);
        $target_dir = "uploads/reviews/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES['review_file']['tmp_name'], $target_file)) {
            $stmt = $conn->prepare("UPDATE documents SET reviewed_file_path = ? WHERE doc_id = ?");
            $stmt->bind_param("si", $target_file, $doc_id);
            if ($stmt->execute()) {
                $msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>✅ Review file uploaded successfully!</div>";
                
                // Create notification for the uploader
                $uploader_id = null;
                $stmt_get_uploader = $conn->prepare("SELECT uploaded_by FROM documents WHERE doc_id = ?");
                $stmt_get_uploader->bind_param("i", $doc_id);
                $stmt_get_uploader->execute();
                $res_uploader = $stmt_get_uploader->get_result();
                if ($res_uploader && $row = $res_uploader->fetch_assoc()) {
                    $uploader_id = $row['uploaded_by'];
                }
                if ($uploader_id && $uploader_id != $_SESSION['user_id']) {
                    $stmt_notif = $conn->prepare("INSERT INTO user_notifications (user_id, doc_id, message) VALUES (?, ?, 'Admin uploaded a review for your document.')");
                    $stmt_notif->bind_param("ii", $uploader_id, $doc_id);
                    $stmt_notif->execute();
                }
            }
            else $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Database Error.</div>";
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'approved') {
        $msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>✅ Document approved and visible to Accreditors!</div>";
    } elseif ($_GET['msg'] == 'unapproved') {
        $msg = "<div class='bg-amber-100 text-amber-700 p-3 rounded mb-4'>↺ Document unapproved and reverted to pending status.</div>";
    }
}

$colleges = $conn->query("SELECT * FROM colleges ORDER BY college_name");
$areas = $conn->query("SELECT * FROM areas ORDER BY area_no");
$programs_list = $conn->query("SELECT * FROM programs ORDER BY program_code");
$types_list = $conn->query("SELECT * FROM document_types ORDER BY type_name");

$sql = "SELECT d.*, p.program_code, a.area_no, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) as uploader 
        FROM documents d
        LEFT JOIN cycles c ON d.cycle_id = c.cycle_id
        LEFT JOIN programs p ON c.program_id = p.program_id
        LEFT JOIN areas a ON d.area_id = a.area_id
        LEFT JOIN users u ON d.uploaded_by = u.user_id
        WHERE 1=1";
if ($search) {
    $sql .= " AND (d.file_name LIKE '%$search%' OR p.program_code LIKE '%$search%' OR CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) LIKE '%$search%')";
}
if ($college_filter) {
    $sql .= " AND p.college_id = $college_filter";
}
if ($area_filter) {
    $sql .= " AND d.area_id = $area_filter";
}
$sql .= " ORDER BY d.uploaded_at DESC";
$documents = $conn->query($sql);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Documents — Admin</title>
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
        <a href="users.php" class="nav-item">
          <i class="fas fa-users"></i> Manage Users
        </a>
        <a href="colleges.php" class="nav-item"><i class="fas fa-university"></i> Manage Colleges</a>
        <a href="admin_cycles.php" class="nav-item">
          <i class="fas fa-sync-alt"></i> Manage Levels
        </a>
        <a href="documents.php" class="nav-item active"><i class="fas fa-file-alt"></i> Documents</a>
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
          <h1 class="text-2xl font-semibold text-bisu">Documents</h1>
          <div class="text-sm text-slate-500">View and manage all uploaded documents</div>
        </div>
        <div>
          <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" class="btn btn-primary">+ Upload Document</button>
        </div>
      </div>

      <?= $msg ?>

      <div class="card">
        <form method="GET" class="flex items-center gap-3 mb-4">
          <input name="q" value="<?= htmlspecialchars($search) ?>" class="px-3 py-2 border rounded w-1/3" placeholder="Search documents...">
          
          <select name="college_id" class="px-3 py-2 border rounded">
            <option value="">All Colleges</option>
            <?php if($colleges) while($c = $colleges->fetch_assoc()): ?>
                <option value="<?= $c['college_id'] ?>" <?= $college_filter == $c['college_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['college_name']) ?></option>
            <?php endwhile; ?>
          </select>

          <select name="area_id" class="px-3 py-2 border rounded">
            <option value="">All Areas</option>
            <?php if($areas) while($a = $areas->fetch_assoc()): ?>
                <option value="<?= $a['area_id'] ?>" <?= $area_filter == $a['area_id'] ? 'selected' : '' ?>>
                    Area <?= $a['area_no'] ?>: <?= htmlspecialchars($a['area_title']) ?>
                </option>
            <?php endwhile; ?>
          </select>

          <button type="submit" class="btn btn-primary small">Search</button>
        </form>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="text-slate-600"><th class="py-2">Title</th><th class="py-2">College</th><th class="py-2">Area</th><th class="py-2">Status</th><th class="py-2">Date</th><th class="py-2">Admin Review</th><th class="py-2">Actions</th></tr>
            </thead>
            <tbody class="text-slate-700">
              <?php if ($documents && $documents->num_rows > 0): ?>
                <?php while($doc = $documents->fetch_assoc()): ?>
                  <tr class="border-t hover:bg-gray-50">
                    <td class="py-3 font-medium"><div class="flex items-center gap-2"><i class="fas fa-file-pdf text-red-500"></i> <?= htmlspecialchars($doc['file_name']) ?></div></td>
                    <td class="py-3"><span class="bg-blue-50 text-blue-700 px-2 py-1 rounded text-xs font-bold"><?= htmlspecialchars($doc['program_code']) ?></span></td>
                    <td class="py-3">Area <?= htmlspecialchars($doc['area_no']) ?></td>
                    <td class="py-3">
                        <?php if(($doc['status'] ?? 'pending') === 'approved'): ?>
                            <span class="bg-emerald-100 text-emerald-700 px-2 py-1 rounded text-xs font-bold">Approved</span>
                            <a href="?unapprove_id=<?= $doc['doc_id'] ?>" onclick="return confirm('Are you sure you want to unapprove this document?')" class="ml-2 text-xs text-red-500 hover:underline" title="Revert to Pending"><i class="fas fa-undo"></i></a>
                        <?php else: ?>
                            <span class="bg-amber-100 text-amber-700 px-2 py-1 rounded text-xs font-bold">Pending</span>
                            <a href="?approve_id=<?= $doc['doc_id'] ?>" class="ml-2 text-xs text-indigo-600 hover:underline">Approve</a>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 text-sm text-slate-500"><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
                    <td class="py-3">
                        <?php if(!empty($doc['reviewed_file_path'])): ?>
                            <a href="<?= htmlspecialchars($doc['reviewed_file_path']) ?>" target="_blank" class="text-emerald-600 hover:underline text-xs font-bold"><i class="fas fa-check-circle"></i> View Review</a>
                            <button onclick="openReviewUpload(<?= $doc['doc_id'] ?>)" class="ml-2 text-slate-400 hover:text-indigo-600" title="Update Review File"><i class="fas fa-pen"></i></button>
                        <?php else: ?>
                            <button onclick="openReviewUpload(<?= $doc['doc_id'] ?>)" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium"><i class="fas fa-upload"></i> Upload Review</button>
                        <?php endif; ?>
                    </td>
                    <td class="py-3">
                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="mr-3 text-slate-600 hover:text-bisu"><i class="fas fa-eye"></i></a>
                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" download class="mr-3 text-slate-600 hover:text-bisu"><i class="fas fa-download"></i></a>
                        <button onclick="openRemarkModal(<?= $doc['doc_id'] ?>, '<?= htmlspecialchars($doc['file_path']) ?>', '<?= htmlspecialchars($doc['file_name']) ?>')" class="text-slate-600 hover:text-indigo-600" title="Add Remark"><i class="fas fa-comment-dots"></i></button>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="7" class="py-4 text-center text-slate-500">No documents found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
  </main>

  <!-- Upload Modal -->
  <div id="uploadModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800">Upload Document</h3>
            <button onclick="document.getElementById('uploadModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6">
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Program</label>
                    <select name="program_id" required class="w-full p-2 border border-slate-300 rounded-lg outline-none focus:border-indigo-500">
                        <option value="">Select Program...</option>
                        <?php if($programs_list) while($p = $programs_list->fetch_assoc()): ?>
                            <option value="<?= $p['program_id'] ?>"><?= htmlspecialchars($p['program_code']) ?> - <?= htmlspecialchars($p['program_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Area</label>
                    <select name="area_id" required class="w-full p-2 border border-slate-300 rounded-lg outline-none focus:border-indigo-500">
                        <option value="">Select Area...</option>
                        <?php 
                        if($areas) {
                            $areas->data_seek(0); // Reset pointer for reuse
                            while($a = $areas->fetch_assoc()): 
                        ?>
                            <option value="<?= $a['area_id'] ?>">Area <?= $a['area_no'] ?>: <?= htmlspecialchars($a['area_title']) ?></option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Document Type</label>
                    <select name="type_id" required class="w-full p-2 border border-slate-300 rounded-lg outline-none focus:border-indigo-500">
                        <?php if($types_list) while($t = $types_list->fetch_assoc()): ?>
                            <option value="<?= $t['type_id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">File</label>
                    <input type="file" name="doc_file" required class="w-full p-2 border border-slate-300 rounded-lg">
                </div>
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" name="upload_doc" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Upload</button>
                </div>
            </form>
        </div>
    </div>
  </div>

  <!-- Remark Modal -->
  <div id="remarkModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800">Add Remark</h3>
            <button onclick="document.getElementById('remarkModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="doc_id" id="remarkDocId">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Document</label>
                    <div id="remarkDocName" class="text-sm text-slate-600 font-medium bg-slate-50 p-2 rounded border border-slate-200"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Remark / Comment</label>
                    <textarea name="remark_text" required class="w-full p-2 border border-slate-300 rounded-lg h-32 resize-none focus:border-indigo-500 outline-none" placeholder="Enter your findings or remarks..."></textarea>
                </div>
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('remarkModal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" name="submit_remark" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save Remark</button>
                </div>
            </form>
        </div>
    </div>
  </div>

  <!-- Upload Review Modal -->
  <div id="reviewUploadModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800">Upload Reviewed File</h3>
            <button onclick="document.getElementById('reviewUploadModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6">
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="doc_id" id="reviewDocId">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Select Commented File</label>
                    <input type="file" name="review_file" required class="w-full p-2 border border-slate-300 rounded-lg">
                </div>
                <button type="submit" name="upload_review" class="w-full py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Upload & Send</button>
            </form>
        </div>
    </div>
  </div>

  <script>
    function openRemarkModal(id, name) {
        document.getElementById('remarkDocId').value = id;
        document.getElementById('remarkDocName').textContent = name;
        document.getElementById('remarkModal').classList.remove('hidden');
    }
    function openReviewUpload(id) {
        document.getElementById('reviewDocId').value = id;
        document.getElementById('reviewUploadModal').classList.remove('hidden');
    }
  </script>
</body>
</html>
