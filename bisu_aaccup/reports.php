<?php
session_start();
require_once __DIR__ . '/config/db.php';

requireAdminSessionOrExit();

$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');
$repo_tables_ready = true;
$required_tables = ['repositories', 'repository_members', 'repository_sections', 'repository_documents', 'repository_comments'];
foreach ($required_tables as $table_name) {
    $check = $conn->query("SHOW TABLES LIKE '{$table_name}'");
    if (!$check || $check->num_rows === 0) {
        $repo_tables_ready = false;
        break;
    }
}

$stats = [
    'repositories' => 0,
    'documents' => 0,
    'comments' => 0,
    'approved' => 0
];
$repository_rows = [];

if ($repo_tables_ready) {
    $stats['repositories'] = (int) ($conn->query("SELECT COUNT(*) FROM repositories")->fetch_row()[0] ?? 0);
    $stats['documents'] = (int) ($conn->query("SELECT COUNT(*) FROM repository_documents")->fetch_row()[0] ?? 0);
    $stats['comments'] = (int) ($conn->query("SELECT COUNT(*) FROM repository_comments")->fetch_row()[0] ?? 0);
    $stats['approved'] = (int) ($conn->query("SELECT COUNT(*) FROM repositories WHERE repository_status = 'approved'")->fetch_row()[0] ?? 0);

    $sql = "SELECT r.repository_id, r.repository_name, r.school_year, r.accreditation_year, r.repository_status,
            p.program_code,
            (SELECT COUNT(*) FROM repository_members rm WHERE rm.repository_id = r.repository_id AND rm.member_role = 'focal' AND rm.is_active = 1) AS focal_count,
            (SELECT COUNT(*) FROM repository_members rm WHERE rm.repository_id = r.repository_id AND rm.member_role = 'accreditor' AND rm.is_active = 1) AS accreditor_count,
            (SELECT COUNT(*) FROM repository_sections rs WHERE rs.repository_id = r.repository_id AND rs.is_active = 1) AS section_count,
            (SELECT COUNT(*) FROM repository_documents rd WHERE rd.repository_id = r.repository_id) AS document_count,
            (SELECT COUNT(*) FROM repository_comments rc JOIN repository_documents rd2 ON rc.repository_document_id = rd2.repository_document_id WHERE rd2.repository_id = r.repository_id) AS comment_count
            FROM repositories r
            LEFT JOIN programs p ON r.program_id = p.program_id
            ORDER BY r.created_at DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $repository_rows[] = $row;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Repository Reports - Admin</title>
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
      <a href="repositories.php" class="topbar-brand">
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

  <aside class="sidebar">
    <nav class="sidebar-nav">
        <a href="repositories.php" class="nav-item">
          <i class="fas fa-folder-tree"></i> Repositories
        </a>
        <a href="users.php" class="nav-item">
          <i class="fas fa-users"></i> Manage Users
        </a>
        <a href="documents.php" class="nav-item">
          <i class="fas fa-file-alt"></i> Documents
        </a>
        <a href="reports.php" class="nav-item active">
          <i class="fas fa-chart-bar"></i> Reports / Logs
        </a>
    </nav>
    <div class="mt-auto p-4">
        <a href="profile.php" class="nav-item"><i class="fas fa-user-cog"></i> Manage Profile</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-bisu">Repository Reports</h1>
        <div class="text-sm text-slate-500">Repository activity summary for the new accreditation workflow</div>
      </div>
    </div>

    <?php if (!$repo_tables_ready): ?>
      <div class="bg-amber-100 text-amber-800 p-4 rounded-lg mb-6">
        Repository workflow tables are not ready yet. Run <a href="update_repository_schema.php" class="underline font-bold">update_repository_schema.php</a> first.
      </div>
    <?php else: ?>
      <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <div class="text-sm text-slate-500">Repositories</div>
          <div class="mt-2 text-3xl font-bold text-slate-900"><?= number_format($stats['repositories']) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <div class="text-sm text-slate-500">Files</div>
          <div class="mt-2 text-3xl font-bold text-slate-900"><?= number_format($stats['documents']) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <div class="text-sm text-slate-500">Review Comments</div>
          <div class="mt-2 text-3xl font-bold text-slate-900"><?= number_format($stats['comments']) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <div class="text-sm text-slate-500">Approved Repositories</div>
          <div class="mt-2 text-3xl font-bold text-slate-900"><?= number_format($stats['approved']) ?></div>
        </div>
      </section>

      <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200">
          <h2 class="font-bold text-slate-800">Repository Summary</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-white text-slate-500 border-b border-slate-100">
              <tr>
                <th class="p-4 font-semibold">Repository</th>
                <th class="p-4 font-semibold">School Year</th>
                <th class="p-4 font-semibold">Program</th>
                <th class="p-4 font-semibold">Focal</th>
                <th class="p-4 font-semibold">Accreditors</th>
                <th class="p-4 font-semibold">Sections</th>
                <th class="p-4 font-semibold">Files</th>
                <th class="p-4 font-semibold">Comments</th>
                <th class="p-4 font-semibold">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php if (empty($repository_rows)): ?>
                <tr><td colspan="9" class="p-6 text-center text-slate-500">No repositories available yet.</td></tr>
              <?php else: ?>
                <?php foreach ($repository_rows as $row): ?>
                  <tr class="hover:bg-slate-50">
                    <td class="p-4 font-medium text-slate-800"><?= htmlspecialchars($row['repository_name']) ?></td>
                    <td class="p-4 text-slate-600"><?= htmlspecialchars($row['school_year']) ?> / <?= (int) $row['accreditation_year'] ?></td>
                    <td class="p-4 text-slate-600"><?= htmlspecialchars($row['program_code'] ?: '-') ?></td>
                    <td class="p-4 text-slate-600"><?= (int) $row['focal_count'] ?></td>
                    <td class="p-4 text-slate-600"><?= (int) $row['accreditor_count'] ?></td>
                    <td class="p-4 text-slate-600"><?= (int) $row['section_count'] ?></td>
                    <td class="p-4 text-slate-600"><?= (int) $row['document_count'] ?></td>
                    <td class="p-4 text-slate-600"><?= (int) $row['comment_count'] ?></td>
                    <td class="p-4"><span class="bg-slate-100 text-slate-700 px-2 py-1 rounded text-xs font-bold uppercase"><?= htmlspecialchars($row['repository_status']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
