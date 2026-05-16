<?php
session_start();
require_once __DIR__ . '/config/db.php';

requireAdminSessionOrExit();

$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');
$required_tables = ['repositories', 'repository_sections', 'repository_documents', 'repository_comments'];
$repo_tables_ready = true;

foreach ($required_tables as $table_name) {
    $check = $conn->query("SHOW TABLES LIKE '{$table_name}'");
    if (!$check || $check->num_rows === 0) {
        $repo_tables_ready = false;
        break;
    }
}

$msg = '';
$allowed_statuses = ['draft', 'for_review', 'finalized', 'approved'];

if ($repo_tables_ready && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_document_status'])) {
    requireValidCsrfToken();

    $repository_document_id = intval($_POST['repository_document_id'] ?? 0);
    $document_status = trim($_POST['document_status'] ?? '');

    if ($repository_document_id > 0 && in_array($document_status, $allowed_statuses, true)) {
        $stmt = $conn->prepare("UPDATE repository_documents SET document_status = ? WHERE repository_document_id = ?");
        $stmt->bind_param("si", $document_status, $repository_document_id);
        if ($stmt->execute()) {
            $msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>Document status updated.</div>";
        } else {
            $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Unable to update document status.</div>";
        }
    }
}

if ($repo_tables_ready && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_repository_document'])) {
    requireValidCsrfToken();

    $repository_document_id = intval($_POST['repository_document_id'] ?? 0);
    if ($repository_document_id > 0) {
        $stmt = $conn->prepare("SELECT file_path FROM repository_documents WHERE repository_document_id = ?");
        $stmt->bind_param("i", $repository_document_id);
        $stmt->execute();
        $document = $stmt->get_result()->fetch_assoc();

        if ($document) {
            $file_path = $document['file_path'];
            $uploads_root = realpath(__DIR__ . '/uploads');
            $file_realpath = realpath($file_path);
            if ($uploads_root && $file_realpath && strpos($file_realpath, $uploads_root) === 0 && is_file($file_realpath)) {
                unlink($file_realpath);
            }

            $delete_stmt = $conn->prepare("DELETE FROM repository_documents WHERE repository_document_id = ?");
            $delete_stmt->bind_param("i", $repository_document_id);
            if ($delete_stmt->execute()) {
                $msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>Repository document deleted.</div>";
            } else {
                $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Unable to delete repository document.</div>";
            }
        }
    }
}

$search = trim($_GET['q'] ?? '');
$repository_filter = intval($_GET['repository_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? '');

$repositories = [];
$documents = [];
$stats = [
    'documents' => 0,
    'for_review' => 0,
    'approved' => 0,
    'comments' => 0,
];

if ($repo_tables_ready) {
    $repository_res = $conn->query("SELECT repository_id, repository_name FROM repositories ORDER BY repository_name");
    if ($repository_res) {
        while ($row = $repository_res->fetch_assoc()) {
            $repositories[] = $row;
        }
    }

    $stats['documents'] = (int) ($conn->query("SELECT COUNT(*) FROM repository_documents")->fetch_row()[0] ?? 0);
    $stats['for_review'] = (int) ($conn->query("SELECT COUNT(*) FROM repository_documents WHERE document_status IN ('draft', 'for_review', 'finalized')")->fetch_row()[0] ?? 0);
    $stats['approved'] = (int) ($conn->query("SELECT COUNT(*) FROM repository_documents WHERE document_status = 'approved'")->fetch_row()[0] ?? 0);
    $stats['comments'] = (int) ($conn->query("SELECT COUNT(*) FROM repository_comments")->fetch_row()[0] ?? 0);

    $sql = "SELECT rd.repository_document_id, rd.repository_id, rd.file_name, rd.file_path, rd.document_status, rd.created_at,
            r.repository_name, r.repository_status,
            rs.section_name,
            CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) AS uploader_name,
            (SELECT COUNT(*) FROM repository_comments rc WHERE rc.repository_document_id = rd.repository_document_id) AS comment_count
            FROM repository_documents rd
            JOIN repositories r ON rd.repository_id = r.repository_id
            LEFT JOIN repository_sections rs ON rd.section_id = rs.section_id
            JOIN users u ON rd.uploaded_by = u.user_id
            WHERE 1=1";
    $params = [];
    $types = '';

    if ($search !== '') {
        $like = '%' . $search . '%';
        $sql .= " AND (rd.file_name LIKE ? OR r.repository_name LIKE ? OR rs.section_name LIKE ? OR CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) LIKE ?)";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'ssss';
    }

    if ($repository_filter > 0) {
        $sql .= " AND rd.repository_id = ?";
        $params[] = $repository_filter;
        $types .= 'i';
    }

    if (in_array($status_filter, $allowed_statuses, true)) {
        $sql .= " AND rd.document_status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }

    $sql .= " ORDER BY rd.created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $documents[] = $row;
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
  <title>Repository Documents - Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/admin-dashboard.css">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
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
          <span class="user-name"><?= $full_name ?></span>
          <span class="user-role">Admin</span>
        </div>
        <div class="user-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
      </div>
      <div class="divider-vertical"></div>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <aside class="sidebar">
    <nav class="sidebar-nav">
      <a href="admin_dashboard.php" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
      <a href="repositories.php" class="nav-item"><i class="fas fa-folder-tree"></i> Repositories</a>
      <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Manage Users</a>
      <a href="documents.php" class="nav-item active"><i class="fas fa-file-alt"></i> Documents</a>
      <a href="reports.php" class="nav-item"><i class="fas fa-chart-bar"></i> Reports / Logs</a>
    </nav>
    <div class="mt-auto p-4">
      <a href="profile.php" class="nav-item"><i class="fas fa-user-cog"></i> Manage Profile</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900">Repository Documents</h1>
        <p class="text-sm text-slate-500">Review uploaded accreditation files, update status, and open the repository workspace when comments are needed.</p>
      </div>
      <a href="repositories.php" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 text-sm font-medium">
        <i class="fas fa-folder-tree mr-2"></i>Open Repositories
      </a>
    </div>

    <?php if (!$repo_tables_ready): ?>
      <div class="bg-amber-100 text-amber-800 p-4 rounded-lg border border-amber-200">
        Repository workflow tables are not ready yet. Run <a href="update_repository_schema.php" class="underline font-bold">update_repository_schema.php</a> first.
      </div>
    <?php else: ?>
      <?= $msg ?>

      <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <div class="text-sm text-slate-500">All Files</div>
          <div class="mt-2 text-3xl font-bold text-slate-900"><?= number_format($stats['documents']) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <div class="text-sm text-slate-500">Needs Attention</div>
          <div class="mt-2 text-3xl font-bold text-slate-900"><?= number_format($stats['for_review']) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <div class="text-sm text-slate-500">Approved Files</div>
          <div class="mt-2 text-3xl font-bold text-slate-900"><?= number_format($stats['approved']) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <div class="text-sm text-slate-500">Comments Logged</div>
          <div class="mt-2 text-3xl font-bold text-slate-900"><?= number_format($stats['comments']) ?></div>
        </div>
      </section>

      <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Search</label>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="w-full p-2 border border-slate-300 rounded-lg" placeholder="Search file, repository, folder, or uploader">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Repository</label>
            <select name="repository_id" class="w-full p-2 border border-slate-300 rounded-lg">
              <option value="0">All repositories</option>
              <?php foreach ($repositories as $repository): ?>
                <option value="<?= (int) $repository['repository_id'] ?>" <?= $repository_filter === (int) $repository['repository_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($repository['repository_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
            <select name="status" class="w-full p-2 border border-slate-300 rounded-lg">
              <option value="">All statuses</option>
              <?php foreach ($allowed_statuses as $status_option): ?>
                <option value="<?= htmlspecialchars($status_option) ?>" <?= $status_filter === $status_option ? 'selected' : '' ?>>
                  <?= htmlspecialchars(str_replace('_', ' ', ucfirst($status_option))) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="md:col-span-4 flex justify-end gap-2">
            <a href="documents.php" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50">Reset</a>
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900">Apply Filters</button>
          </div>
        </form>
      </section>

      <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
              <tr>
                <th class="p-4 font-semibold">File</th>
                <th class="p-4 font-semibold">Repository / Folder</th>
                <th class="p-4 font-semibold">Uploaded By</th>
                <th class="p-4 font-semibold">Comments</th>
                <th class="p-4 font-semibold">Status</th>
                <th class="p-4 font-semibold text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php if (empty($documents)): ?>
                <tr><td colspan="6" class="p-8 text-center text-slate-500">No repository documents matched your filters.</td></tr>
              <?php else: ?>
                <?php foreach ($documents as $document): ?>
                  <tr class="hover:bg-slate-50">
                    <td class="p-4">
                      <div class="font-medium text-slate-800"><?= htmlspecialchars($document['file_name']) ?></div>
                      <div class="text-xs text-slate-400 mt-1"><?= date('M d, Y h:i A', strtotime($document['created_at'])) ?></div>
                    </td>
                    <td class="p-4">
                      <div class="text-slate-700"><?= htmlspecialchars($document['repository_name']) ?></div>
                      <div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($document['section_name'] ?: 'Repository Root') ?></div>
                    </td>
                    <td class="p-4 text-slate-600"><?= htmlspecialchars($document['uploader_name']) ?></td>
                    <td class="p-4 text-slate-600"><?= (int) $document['comment_count'] ?></td>
                    <td class="p-4">
                      <form method="POST" class="flex items-center gap-2">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="repository_document_id" value="<?= (int) $document['repository_document_id'] ?>">
                        <input type="hidden" name="update_document_status" value="1">
                        <select name="document_status" class="p-2 border border-slate-300 rounded-lg text-sm">
                          <?php foreach ($allowed_statuses as $status_option): ?>
                            <option value="<?= htmlspecialchars($status_option) ?>" <?= $document['document_status'] === $status_option ? 'selected' : '' ?>>
                              <?= htmlspecialchars(str_replace('_', ' ', ucfirst($status_option))) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                        <button type="submit" class="px-3 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900 text-xs font-medium">Save</button>
                      </form>
                    </td>
                    <td class="p-4">
                      <div class="flex items-center justify-end gap-2 flex-wrap">
                        <a href="<?= htmlspecialchars($document['file_path']) ?>" target="_blank" class="px-3 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-xs font-medium">Open</a>
                        <a href="repository_workspace.php?repository_id=<?= (int) $document['repository_id'] ?>" class="px-3 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-xs font-medium">Workspace</a>
                        <form method="POST" onsubmit="return confirm('Delete this repository file?');">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                          <input type="hidden" name="repository_document_id" value="<?= (int) $document['repository_document_id'] ?>">
                          <input type="hidden" name="delete_repository_document" value="1">
                          <button type="submit" class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-xs font-medium">Delete</button>
                        </form>
                      </div>
                    </td>
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
