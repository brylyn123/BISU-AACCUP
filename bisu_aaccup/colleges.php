<?php
session_start();
require_once __DIR__ . '/config/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');

// Handle Actions
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_college'])) {
        $name = sanitize($_POST['college_name']);
        $code = sanitize($_POST['college_code']);
        if ($name && $code) {
            $stmt = $conn->prepare("INSERT INTO colleges (college_name, college_code) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $code);
            if ($stmt->execute()) $msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>✅ College added successfully!</div>";
            else $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Error: " . $conn->error . "</div>";
        }
    } elseif (isset($_POST['edit_college'])) {
        $id = intval($_POST['college_id']);
        $name = sanitize($_POST['college_name']);
        $code = sanitize($_POST['college_code']);
        if ($id && $name && $code) {
            $stmt = $conn->prepare("UPDATE colleges SET college_name = ?, college_code = ? WHERE college_id = ?");
            $stmt->bind_param("ssi", $name, $code, $id);
            if ($stmt->execute()) $msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>✅ College updated successfully!</div>";
            else $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Error: " . $conn->error . "</div>";
        }
    } elseif (isset($_POST['delete_college'])) {
        $id = intval($_POST['college_id']);
        // Check for dependencies (programs)
        $check = $conn->query("SELECT count(*) as c FROM programs WHERE college_id = $id")->fetch_assoc();
        if ($check['c'] > 0) {
            $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Cannot delete: This college has associated programs.</div>";
        } else {
            $conn->query("DELETE FROM colleges WHERE college_id = $id");
            $msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>✅ College deleted successfully!</div>";
        }
    }
}

// View Logic
$view = isset($_GET['view']) ? $_GET['view'] : 'list';
$college_id = isset($_GET['college_id']) ? intval($_GET['college_id']) : 0;
$college_data = null;
$grouped_data = [];

if ($view === 'documents' && $college_id) {
    // Fetch College Details
    $c_res = $conn->query("SELECT * FROM colleges WHERE college_id = $college_id");
    if ($c_res && $c_res->num_rows > 0) {
        $college_data = $c_res->fetch_assoc();
        $level_filter = isset($_GET['level']) ? intval($_GET['level']) : 0;

        // Initialize all Document Types
        $t_res = $conn->query("SELECT * FROM document_types ORDER BY type_name");
        while($t = $t_res->fetch_assoc()) {
            $grouped_data[$t['type_id']] = [
                'info' => $t,
                'docs' => []
            ];
        }

        // Fetch Documents for this College
        $sql = "SELECT d.*, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) as full_name, p.program_code, a.area_no, a.area_title 
                FROM documents d
                JOIN cycles c ON d.cycle_id = c.cycle_id
                JOIN programs p ON c.program_id = p.program_id
                JOIN areas a ON d.area_id = a.area_id
                LEFT JOIN users u ON d.uploaded_by = u.user_id
                WHERE p.college_id = $college_id";
        
        if ($level_filter) {
            $sql .= " AND c.level = $level_filter AND d.status = 'approved'";
        }
        
        $sql .= " ORDER BY d.uploaded_at DESC";
        
        $d_res = $conn->query($sql);
        if ($d_res) {
            while($doc = $d_res->fetch_assoc()) {
                $tid = $doc['type_id'];
                if (isset($grouped_data[$tid])) {
                    $grouped_data[$tid]['docs'][] = $doc;
                }
            }
        }
    }
}

if ($view === 'compliance_matrix' && $college_id) {
    // Fetch College Details
    $c_res = $conn->query("SELECT * FROM colleges WHERE college_id = $college_id");
    $college_data = $c_res->fetch_assoc();

    // Fetch All Areas
    $all_areas = [];
    $a_res = $conn->query("SELECT * FROM areas ORDER BY area_no");
    while($a = $a_res->fetch_assoc()) $all_areas[] = $a;

    // Fetch Documents for Dropdowns
    $doc_sql = "SELECT d.doc_id, d.file_name, d.area_id, p.program_code 
                FROM documents d
                JOIN cycles c ON d.cycle_id = c.cycle_id
                JOIN programs p ON c.program_id = p.program_id
                WHERE p.college_id = $college_id
                ORDER BY d.uploaded_at DESC";
    $d_res = $conn->query($doc_sql);
    $area_docs = [];
    if($d_res) while($d = $d_res->fetch_assoc()) $area_docs[$d['area_id']][] = $d;
}

// Fetch Colleges
$colleges = $conn->query("SELECT * FROM colleges ORDER BY college_name");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Colleges — Admin</title>
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
        <a href="colleges.php" class="nav-item active"><i class="fas fa-university"></i> Manage Colleges</a>
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
      <?php if ($view === 'list'): ?>
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-bisu">Manage Colleges</h1>
          <div class="text-sm text-slate-500">Add or edit college entries</div>
        </div>
        <div>
          <button onclick="document.getElementById('addCollegeModal').classList.remove('hidden')" class="btn btn-primary">+ Add College</button>
        </div>
      </div>

      <?= $msg ?>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if ($colleges && $colleges->num_rows > 0): ?>
          <?php while($c = $colleges->fetch_assoc()): 
            $cc = strtoupper($c['college_code']);
            
            // Default Theme (Indigo)
            $hover_border = "hover:border-indigo-400";
            $icon_style = "bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600";
            $badge_style = "bg-indigo-50 text-indigo-700";

            // College Specific Themes
            if ($cc === 'COS') { // Green
                $hover_border = "hover:border-green-400";
                $icon_style = "bg-green-50 text-green-600 group-hover:bg-green-600";
                $badge_style = "bg-green-50 text-green-700";
            } elseif ($cc === 'CTE') { // Red
                $hover_border = "hover:border-rose-400";
                $icon_style = "bg-rose-50 text-rose-600 group-hover:bg-rose-600";
                $badge_style = "bg-rose-50 text-rose-700";
            } elseif ($cc === 'CBM') { // Orange
                $hover_border = "hover:border-orange-400";
                $icon_style = "bg-orange-50 text-orange-600 group-hover:bg-orange-600";
                $badge_style = "bg-orange-50 text-orange-700";
            } elseif ($cc === 'CFMS') { // Blue
                $hover_border = "hover:border-blue-400";
                $icon_style = "bg-blue-50 text-blue-600 group-hover:bg-blue-600";
                $badge_style = "bg-blue-50 text-blue-700";
            }
          ?>
            <a href="?view=documents&college_id=<?= $c['college_id'] ?>" class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all group flex flex-col items-center text-center cursor-pointer hover:-translate-y-1 <?= $hover_border ?>">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mb-4 group-hover:text-white transition-colors <?= $icon_style ?>">
                    <i class="fas fa-university fa-2x"></i>
                </div>
                <h3 class="font-bold text-slate-800 text-lg mb-1"><?= htmlspecialchars($c['college_name']) ?></h3>
                <span class="px-3 py-1 rounded-full text-xs font-bold <?= $badge_style ?>"><?= htmlspecialchars($c['college_code']) ?></span>
            </a>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="col-span-full text-center py-12 bg-white rounded-xl border border-slate-200">
            <i class="fas fa-folder-open text-slate-300 text-4xl mb-3"></i>
            <p class="text-slate-500">No colleges found.</p>
          </div>
        <?php endif; ?>
      </div>

      <?php elseif ($view === 'documents' && $college_data): ?>
        <?php 
            $cc = strtoupper($college_data['college_code']);
            $bg_theme = "bg-slate-50";
            if ($cc === 'COS') $bg_theme = "bg-green-50";
            elseif ($cc === 'CTE') $bg_theme = "bg-rose-50";
            elseif ($cc === 'CBM') $bg_theme = "bg-orange-50";
            elseif ($cc === 'CFMS') $bg_theme = "bg-blue-50";
        ?>
        <div class="<?= $bg_theme ?> rounded-3xl p-8 border border-slate-200/60 shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <a href="colleges.php" class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-slate-200 text-slate-500 hover:text-indigo-600 hover:border-indigo-600 transition-colors">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-bisu"><?= htmlspecialchars($college_data['college_name']) ?></h1>
                    <div class="text-sm text-slate-500">Document Repository</div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <form method="GET" class="flex items-center">
                    <input type="hidden" name="view" value="documents">
                    <input type="hidden" name="college_id" value="<?= $college_id ?>">
                    <select name="level" onchange="this.form.submit()" class="pl-4 pr-8 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-600 focus:outline-none focus:border-indigo-500 bg-white shadow-sm cursor-pointer">
                        <option value="">All Files (Uploads)</option>
                        <option value="1" <?= $level_filter == 1 ? 'selected' : '' ?>>Level 1 (Approved)</option>
                        <option value="2" <?= $level_filter == 2 ? 'selected' : '' ?>>Level 2 (Approved)</option>
                        <option value="3" <?= $level_filter == 3 ? 'selected' : '' ?>>Level 3 (Approved)</option>
                        <option value="4" <?= $level_filter == 4 ? 'selected' : '' ?>>Level 4 (Approved)</option>
                    </select>
                </form>
                <a href="?view=compliance_matrix&college_id=<?= $college_id ?>" class="btn btn-primary bg-emerald-600 hover:bg-emerald-700 border-emerald-600">
                    <i class="fas fa-clipboard-check mr-2"></i> Compliance Matrix
                </a>
            </div>
        </div>

        <div class="space-y-4">
            <?php foreach($grouped_data as $type_id => $data): 
                $has_files = !empty($data['docs']);
            ?>
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <!-- Type Header (Accordion Toggle) -->
                <button onclick="toggleAccordion('type-<?= $type_id ?>')" class="w-full flex items-center justify-between p-4 bg-slate-50 hover:bg-slate-100 transition-colors text-left">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-lg">
                            <i class="fas fa-folder"></i>
                        </div>
                        <span class="font-semibold text-slate-700 text-lg"><?= htmlspecialchars($data['info']['type_name']) ?></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <?php if($has_files): ?>
                            <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full font-medium"><?= count($data['docs']) ?> Files</span>
                        <?php endif; ?>
                        <i class="fas fa-chevron-down text-slate-400 transition-transform" id="icon-type-<?= $type_id ?>"></i>
                    </div>
                </button>

                <!-- Type Content -->
                <div id="type-<?= $type_id ?>" class="hidden border-t border-slate-200">
                    <?php if(!$has_files): ?>
                        <div class="p-6 text-center text-slate-400 italic">
                            <?php if($level_filter): ?>
                                <div class="flex flex-col items-center gap-2">
                                    <i class="fas fa-check-circle text-slate-200 text-3xl"></i>
                                    <span>No files Approved for Level <?= $level_filter ?></span>
                                </div>
                            <?php else: ?>
                                No documents of this type uploaded yet.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-0">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                                    <tr>
                                        <th class="p-4">File Name</th>
                                        <th class="p-4">Area</th>
                                        <th class="p-4">Program</th>
                                        <th class="p-4">Uploaded By</th>
                                        <th class="p-4">Date</th>
                                        <th class="p-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach($data['docs'] as $doc): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="p-4 font-medium text-slate-800">
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-file-pdf text-red-500"></i> <?= htmlspecialchars($doc['file_name']) ?>
                                                <?php if($level_filter): ?>
                                                    <i class="fas fa-check-circle text-emerald-500 text-xs" title="Approved"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="p-4 text-slate-600">Area <?= $doc['area_no'] ?></td>
                                        <td class="p-4"><span class="bg-blue-50 text-blue-700 px-2 py-1 rounded text-xs font-bold"><?= htmlspecialchars($doc['program_code']) ?></span></td>
                                        <td class="p-4 text-slate-500"><?= htmlspecialchars($doc['full_name'] ?? 'Unknown') ?></td>
                                        <td class="p-4 text-slate-500"><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
                                        <td class="p-4 text-right">
                                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        </div>
      <?php elseif ($view === 'compliance_matrix' && $college_data): ?>
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <a href="?view=documents&college_id=<?= $college_id ?>" class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-slate-200 text-slate-500 hover:text-indigo-600 hover:border-indigo-600 transition-colors">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-bisu">Compliance Matrix</h1>
                    <div class="text-sm text-slate-500"><?= htmlspecialchars($college_data['college_name']) ?></div>
                </div>
            </div>
            <button class="btn btn-primary" onclick="alert('Report Saved (Demo)')"><i class="fas fa-save mr-2"></i> Save Report</button>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                        <tr>
                            <th class="p-4 w-1/4">Area</th>
                            <th class="p-4 w-1/3">Description</th>
                            <th class="p-4">Select Compliance Document</th>
                            <th class="p-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($all_areas as $area): 
                            $docs = $area_docs[$area['area_id']] ?? [];
                            $has_docs = count($docs) > 0;
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="p-4 font-medium text-slate-800">Area <?= $area['area_no'] ?></td>
                            <td class="p-4 text-slate-500"><?= htmlspecialchars($area['area_title']) ?></td>
                            <td class="p-4">
                                <select class="w-full p-2.5 border border-slate-300 rounded-lg outline-none focus:border-indigo-500 bg-white text-slate-700">
                                    <option value="">-- Select Document --</option>
                                    <?php foreach($docs as $d): ?>
                                        <option value="<?= $d['doc_id'] ?>">
                                            [<?= htmlspecialchars($d['program_code']) ?>] <?= htmlspecialchars($d['file_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if(!$has_docs): ?>
                                    <div class="text-xs text-red-400 mt-1"><i class="fas fa-exclamation-circle"></i> No documents available</div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center">
                                <?php if($has_docs): ?>
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-50 text-blue-600"><i class="fas fa-check"></i></span>
                                <?php else: ?>
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-slate-100 text-slate-400"><i class="fas fa-minus"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
      <?php endif; ?>
  </main>

  <!-- Add College Modal -->
  <div id="addCollegeModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800">Add College</h3>
            <button onclick="document.getElementById('addCollegeModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">College Name</label><input type="text" name="college_name" required class="w-full p-2 border border-slate-300 rounded-lg"></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Short Code (e.g., COS)</label><input type="text" name="college_code" required class="w-full p-2 border border-slate-300 rounded-lg"></div>
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('addCollegeModal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" name="add_college" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save</button>
                </div>
            </form>
        </div>
    </div>
  </div>

  <script>
    function toggleAccordion(id) {
        const content = document.getElementById(id);
        const icon = document.getElementById('icon-' + id);
        content.classList.toggle('hidden');
        if(icon) icon.classList.toggle('rotate-180');
    }
  </script>

</body>
</html>
