<?php
session_start();
require_once __DIR__ . '/config/db.php';

requireAdminSessionOrExit();

$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');
$msg = '';

$repo_tables_ready = true;
$required_tables = ['repositories', 'repository_members', 'repository_sections'];
foreach ($required_tables as $table_name) {
    $check = $conn->query("SHOW TABLES LIKE '{$table_name}'");
    if (!$check || $check->num_rows === 0) {
        $repo_tables_ready = false;
        break;
    }
}

if ($repo_tables_ready && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_repository'])) {
    requireValidCsrfToken();

    $repository_name = trim($_POST['repository_name'] ?? '');
    $school_year = trim($_POST['school_year'] ?? '');
    $accreditation_year = intval($_POST['accreditation_year'] ?? 0);
    $program_id = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
    $course_type = trim($_POST['course_type'] ?? '');
    $focal_members = array_map('intval', $_POST['focal_members'] ?? []);
    $accreditor_members = array_map('intval', $_POST['accreditor_members'] ?? []);
    $include_default_areas = isset($_POST['include_default_areas']);

    if ($repository_name === '' || $school_year === '' || $accreditation_year <= 0) {
        $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Please complete the repository name, school year, and accreditation year.</div>";
    } elseif (empty($focal_members)) {
        $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Select at least one focal person for the repository.</div>";
    } else {
        $created_by = intval($_SESSION['user_id']);
        $stmt = $conn->prepare("INSERT INTO repositories (repository_name, school_year, accreditation_year, program_id, course_type, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiisi", $repository_name, $school_year, $accreditation_year, $program_id, $course_type, $created_by);

        if ($stmt->execute()) {
            $repository_id = $conn->insert_id;

            $member_stmt = $conn->prepare("INSERT INTO repository_members (repository_id, user_id, member_role, can_upload, can_review) VALUES (?, ?, ?, ?, ?)");

            foreach ($focal_members as $user_id) {
                $member_role = 'focal';
                $can_upload = 1;
                $can_review = 0;
                $member_stmt->bind_param("iisii", $repository_id, $user_id, $member_role, $can_upload, $can_review);
                $member_stmt->execute();
            }

            foreach ($accreditor_members as $user_id) {
                $member_role = 'accreditor';
                $can_upload = 0;
                $can_review = 1;
                $member_stmt->bind_param("iisii", $repository_id, $user_id, $member_role, $can_upload, $can_review);
                $member_stmt->execute();
            }

            if ($include_default_areas) {
                $areas_res = $conn->query("SELECT area_title FROM areas ORDER BY area_no");
                $section_stmt = $conn->prepare("INSERT INTO repository_sections (repository_id, parent_section_id, section_name, section_kind, sort_order) VALUES (?, NULL, ?, 'area', ?)");
                $sort_order = 0;
                if ($areas_res) {
                    while ($area = $areas_res->fetch_assoc()) {
                        $sort_order++;
                        $section_name = $area['area_title'];
                        $section_stmt->bind_param("isi", $repository_id, $section_name, $sort_order);
                        $section_stmt->execute();
                    }
                }
            }

            $msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>Repository created successfully.</div>";
        } else {
            $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Failed to create repository: " . htmlspecialchars($conn->error) . "</div>";
        }
    }
}

$programs = $conn->query("SELECT program_id, program_code, program_name FROM programs ORDER BY program_code");
$focal_users = $conn->query("SELECT u.user_id, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) AS full_name, u.email FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'Faculty / Focal Person' ORDER BY full_name");
$accreditor_users = $conn->query("SELECT u.user_id, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) AS full_name, u.email, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name LIKE 'Accreditor%' ORDER BY full_name");

$repositories = [];
if ($repo_tables_ready) {
    $sql = "SELECT r.repository_id, r.repository_name, r.school_year, r.accreditation_year, r.course_type, r.repository_status,
            p.program_code,
            (SELECT COUNT(*) FROM repository_members rm WHERE rm.repository_id = r.repository_id AND rm.member_role = 'focal' AND rm.is_active = 1) AS focal_count,
            (SELECT COUNT(*) FROM repository_members rm WHERE rm.repository_id = r.repository_id AND rm.member_role = 'accreditor' AND rm.is_active = 1) AS accreditor_count,
            (SELECT COUNT(*) FROM repository_sections rs WHERE rs.repository_id = r.repository_id AND rs.is_active = 1) AS section_count
            FROM repositories r
            LEFT JOIN programs p ON r.program_id = p.program_id
            ORDER BY r.created_at DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $repositories[] = $row;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Repositories - Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/admin-dashboard.css">
</head>
<body>
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
        <div class="user-avatar"><?php echo strtoupper(substr($full_name, 0, 1)); ?></div>
      </div>
      <div class="divider-vertical"></div>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <aside class="sidebar">
    <nav class="sidebar-nav">
        <a href="admin_dashboard.php?view=dashboard" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="repositories.php" class="nav-item active"><i class="fas fa-folder-tree"></i> Repositories</a>
        <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Manage Users</a>
        <a href="documents.php" class="nav-item"><i class="fas fa-file-alt"></i> Documents</a>
        <a href="reports.php" class="nav-item"><i class="fas fa-chart-bar"></i> Reports / Logs</a>
    </nav>
  </aside>

  <main class="main-content">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-bisu">Repository Management</h1>
        <div class="text-sm text-slate-500">Create accreditation repositories and assign focal persons and accreditors.</div>
      </div>
    </div>

    <?= $msg ?>

    <?php if (!$repo_tables_ready): ?>
      <div class="bg-amber-100 text-amber-800 p-4 rounded-lg mb-6">
        Repository tables are not available yet. Run <a class="underline font-bold" href="update_repository_schema.php">`update_repository_schema.php`</a> first.
      </div>
    <?php else: ?>
      <section class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm mb-8">
        <h2 class="text-lg font-bold text-slate-800 mb-4">Create Repository</h2>
        <form method="POST" class="space-y-5">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
          <input type="hidden" name="create_repository" value="1">

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Repository Name</label>
              <input type="text" name="repository_name" required class="w-full p-2 border border-slate-200 rounded-lg" placeholder="Example: BSCS Level III Accreditation 2026">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">School Year</label>
              <input type="text" name="school_year" required class="w-full p-2 border border-slate-200 rounded-lg" placeholder="2026-2027">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Year of Accreditation</label>
              <input type="number" name="accreditation_year" required min="2000" max="2100" class="w-full p-2 border border-slate-200 rounded-lg" value="<?= date('Y') ?>">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Type of Course</label>
              <input type="text" name="course_type" class="w-full p-2 border border-slate-200 rounded-lg" placeholder="Example: BSCS">
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-slate-700 mb-1">Linked Program / Course</label>
              <select name="program_id" class="w-full p-2 border border-slate-200 rounded-lg">
                <option value="">Optional</option>
                <?php if ($programs) while ($program = $programs->fetch_assoc()): ?>
                  <option value="<?= (int) $program['program_id'] ?>"><?= htmlspecialchars($program['program_code']) ?> - <?= htmlspecialchars($program['program_name']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Assign Focal Persons</label>
              <div class="max-h-56 overflow-y-auto border border-slate-200 rounded-lg p-3 space-y-2">
                <?php if ($focal_users && $focal_users->num_rows > 0): ?>
                  <?php while ($user = $focal_users->fetch_assoc()): ?>
                    <label class="flex items-start gap-3 text-sm text-slate-700">
                      <input type="checkbox" name="focal_members[]" value="<?= (int) $user['user_id'] ?>" class="mt-1">
                      <span><strong><?= htmlspecialchars($user['full_name']) ?></strong><br><span class="text-slate-500"><?= htmlspecialchars($user['email']) ?></span></span>
                    </label>
                  <?php endwhile; ?>
                <?php else: ?>
                  <p class="text-sm text-slate-500">No focal users available.</p>
                <?php endif; ?>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Assign Accreditors</label>
              <div class="max-h-56 overflow-y-auto border border-slate-200 rounded-lg p-3 space-y-2">
                <?php if ($accreditor_users && $accreditor_users->num_rows > 0): ?>
                  <?php while ($user = $accreditor_users->fetch_assoc()): ?>
                    <label class="flex items-start gap-3 text-sm text-slate-700">
                      <input type="checkbox" name="accreditor_members[]" value="<?= (int) $user['user_id'] ?>" class="mt-1">
                      <span><strong><?= htmlspecialchars($user['full_name']) ?></strong><br><span class="text-slate-500"><?= htmlspecialchars($user['role_name']) ?> • <?= htmlspecialchars($user['email']) ?></span></span>
                    </label>
                  <?php endwhile; ?>
                <?php else: ?>
                  <p class="text-sm text-slate-500">No accreditors available.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="include_default_areas" checked>
            <span>Create default area folders inside the repository</span>
          </label>

          <div class="text-right">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Create Repository</button>
          </div>
        </form>
      </section>
    <?php endif; ?>

    <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="p-4 border-b border-slate-200 bg-slate-50">
        <h2 class="font-bold text-slate-800">Existing Repositories</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-white text-slate-500 border-b border-slate-100">
            <tr>
              <th class="p-4 font-semibold">Repository</th>
              <th class="p-4 font-semibold">School Year</th>
              <th class="p-4 font-semibold">Accreditation Year</th>
              <th class="p-4 font-semibold">Course</th>
              <th class="p-4 font-semibold">Focal</th>
              <th class="p-4 font-semibold">Accreditors</th>
              <th class="p-4 font-semibold">Sections</th>
              <th class="p-4 font-semibold">Status</th>
              <th class="p-4 font-semibold text-right">Open</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (empty($repositories)): ?>
              <tr><td colspan="9" class="p-6 text-center text-slate-500">No repositories created yet.</td></tr>
            <?php else: ?>
              <?php foreach ($repositories as $repo): ?>
                <tr class="hover:bg-slate-50">
                  <td class="p-4 font-medium text-slate-800"><?= htmlspecialchars($repo['repository_name']) ?></td>
                  <td class="p-4 text-slate-600"><?= htmlspecialchars($repo['school_year']) ?></td>
                  <td class="p-4 text-slate-600"><?= (int) $repo['accreditation_year'] ?></td>
                  <td class="p-4 text-slate-600"><?= htmlspecialchars($repo['course_type'] ?: ($repo['program_code'] ?: '-')) ?></td>
                  <td class="p-4 text-slate-600"><?= (int) $repo['focal_count'] ?></td>
                  <td class="p-4 text-slate-600"><?= (int) $repo['accreditor_count'] ?></td>
                  <td class="p-4 text-slate-600"><?= (int) $repo['section_count'] ?></td>
                  <td class="p-4"><span class="bg-slate-100 text-slate-700 px-2 py-1 rounded text-xs font-bold uppercase"><?= htmlspecialchars($repo['repository_status']) ?></span></td>
                  <td class="p-4 text-right">
                    <a href="repository_workspace.php?repository_id=<?= (int) $repo['repository_id'] ?>" class="inline-flex items-center px-3 py-2 text-sm border border-slate-200 rounded-lg hover:bg-slate-50">
                      Open
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
