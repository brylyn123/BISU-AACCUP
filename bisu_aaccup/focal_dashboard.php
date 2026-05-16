<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = strtolower(trim($_SESSION['role'] ?? ''));
if (strpos($role, 'focal') === false && strpos($role, 'faculty') === false && strpos($role, 'admin') === false) {
    header('Location: role_home.php');
    exit;
}

$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Focal Person');
$user_id = intval($_SESSION['user_id']);
$required_tables = ['repositories', 'repository_members', 'repository_sections', 'repository_documents'];
$repo_tables_ready = true;
foreach ($required_tables as $table_name) {
    $check = $conn->query("SHOW TABLES LIKE '{$table_name}'");
    if (!$check || $check->num_rows === 0) {
        $repo_tables_ready = false;
        break;
    }
}

$repositories = [];
if ($repo_tables_ready) {
    if (strpos($role, 'admin') !== false) {
        $sql = "SELECT r.*, p.program_code,
                (SELECT COUNT(*) FROM repository_sections rs WHERE rs.repository_id = r.repository_id AND rs.is_active = 1) AS section_count,
                (SELECT COUNT(*) FROM repository_documents rd WHERE rd.repository_id = r.repository_id) AS document_count
                FROM repositories r
                LEFT JOIN programs p ON r.program_id = p.program_id
                ORDER BY r.created_at DESC";
        $res = $conn->query($sql);
    } else {
        $stmt = $conn->prepare("SELECT r.*, p.program_code,
                                (SELECT COUNT(*) FROM repository_sections rs WHERE rs.repository_id = r.repository_id AND rs.is_active = 1) AS section_count,
                                (SELECT COUNT(*) FROM repository_documents rd WHERE rd.repository_id = r.repository_id) AS document_count
                                FROM repository_members rm
                                JOIN repositories r ON rm.repository_id = r.repository_id
                                LEFT JOIN programs p ON r.program_id = p.program_id
                                WHERE rm.user_id = ? AND rm.member_role = 'focal' AND rm.is_active = 1
                                ORDER BY r.created_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
    }

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
  <title>Focal Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/admin-dashboard.css">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
  <header class="topbar">
    <div class="left">
      <div class="brand">
        <i class="fas fa-folder-open"></i> <span>Focal Repository Workspace</span>
      </div>
    </div>
    <div class="right">
      <div class="user-profile">
        <div class="user-info hidden sm:block">
          <span class="user-name"><?= $full_name ?></span>
          <span class="user-role"><?= htmlspecialchars($_SESSION['role'] ?? 'Focal Person') ?></span>
        </div>
        <div class="user-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
      </div>
      <div class="divider-vertical"></div>
      <a href="profile.php" class="logout-btn" title="Manage Profile"><i class="fas fa-user-cog"></i></a>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 py-24">
    <div class="mb-10 text-center">
      <h1 class="text-3xl font-bold text-slate-800">Assigned Repositories</h1>
      <p class="text-slate-500 mt-2">Open a repository to upload files, organize folders, and read review comments.</p>
    </div>

    <?php if (!$repo_tables_ready): ?>
      <div class="max-w-3xl mx-auto bg-amber-100 text-amber-800 p-4 rounded-xl">
        Repository workflow is not ready yet. Please ask the admin to run <a href="update_repository_schema.php" class="underline font-bold">update_repository_schema.php</a>.
      </div>
    <?php elseif (empty($repositories)): ?>
      <div class="max-w-3xl mx-auto bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-500 shadow-sm">
        <i class="fas fa-folder-open text-5xl mb-4 opacity-30"></i>
        <p class="text-lg font-medium">No repositories assigned yet.</p>
        <p class="mt-2">Once an admin assigns you to a repository, it will appear here.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php foreach ($repositories as $repository): ?>
          <a href="repository_workspace.php?repository_id=<?= (int) $repository['repository_id'] ?>" class="group bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all p-6">
            <div class="flex items-start justify-between gap-4">
              <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                <i class="fas fa-folder-tree"></i>
              </div>
              <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-slate-100 text-slate-700"><?= htmlspecialchars($repository['repository_status']) ?></span>
            </div>
            <h2 class="mt-5 text-xl font-bold text-slate-800 group-hover:text-indigo-700 transition-colors"><?= htmlspecialchars($repository['repository_name']) ?></h2>
            <p class="mt-2 text-sm text-slate-500">
              <?= htmlspecialchars($repository['school_year']) ?> • Accreditation <?= (int) $repository['accreditation_year'] ?>
              <?php if (!empty($repository['program_code'])): ?> • <?= htmlspecialchars($repository['program_code']) ?><?php endif; ?>
            </p>
            <div class="mt-5 flex items-center gap-4 text-sm text-slate-500">
              <span><i class="fas fa-layer-group mr-1"></i><?= (int) $repository['section_count'] ?> sections</span>
              <span><i class="fas fa-file-alt mr-1"></i><?= (int) $repository['document_count'] ?> files</span>
            </div>
            <div class="mt-6 text-sm font-semibold text-indigo-600 group-hover:translate-x-1 transition-transform">
              Open Workspace <i class="fas fa-arrow-right ml-1"></i>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
