<?php
session_start();
require_once __DIR__ . '/config/db.php';

requireAdminSessionOrExit();

$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');
$required_tables = ['repositories', 'repository_members', 'repository_sections', 'repository_documents', 'repository_comments'];
$repo_tables_ready = true;

foreach ($required_tables as $table_name) {
    $check = $conn->query("SHOW TABLES LIKE '{$table_name}'");
    if (!$check || $check->num_rows === 0) {
        $repo_tables_ready = false;
        break;
    }
}

$stats = [
    'repositories' => 0,
    'draft_repositories' => 0,
    'approved_repositories' => 0,
    'focal_assignments' => 0,
    'accreditor_assignments' => 0,
    'sections' => 0,
    'documents' => 0,
    'for_review_documents' => 0,
    'comments' => 0,
];
$recent_repositories = [];
$recent_documents = [];
$recent_comments = [];

if ($repo_tables_ready) {
    $stats['repositories'] = (int) ($conn->query("SELECT COUNT(*) FROM repositories")->fetch_row()[0] ?? 0);
    $stats['draft_repositories'] = (int) ($conn->query("SELECT COUNT(*) FROM repositories WHERE repository_status IN ('draft', 'in_review')")->fetch_row()[0] ?? 0);
    $stats['approved_repositories'] = (int) ($conn->query("SELECT COUNT(*) FROM repositories WHERE repository_status = 'approved'")->fetch_row()[0] ?? 0);
    $stats['focal_assignments'] = (int) ($conn->query("SELECT COUNT(*) FROM repository_members WHERE member_role = 'focal' AND is_active = 1")->fetch_row()[0] ?? 0);
    $stats['accreditor_assignments'] = (int) ($conn->query("SELECT COUNT(*) FROM repository_members WHERE member_role = 'accreditor' AND is_active = 1")->fetch_row()[0] ?? 0);
    $stats['sections'] = (int) ($conn->query("SELECT COUNT(*) FROM repository_sections WHERE is_active = 1")->fetch_row()[0] ?? 0);
    $stats['documents'] = (int) ($conn->query("SELECT COUNT(*) FROM repository_documents")->fetch_row()[0] ?? 0);
    $stats['for_review_documents'] = (int) ($conn->query("SELECT COUNT(*) FROM repository_documents WHERE document_status IN ('draft', 'for_review')")->fetch_row()[0] ?? 0);
    $stats['comments'] = (int) ($conn->query("SELECT COUNT(*) FROM repository_comments")->fetch_row()[0] ?? 0);

    $recent_repo_sql = "SELECT r.repository_id, r.repository_name, r.school_year, r.accreditation_year, r.course_type, r.repository_status,
                        p.program_code,
                        (SELECT COUNT(*) FROM repository_members rm WHERE rm.repository_id = r.repository_id AND rm.member_role = 'focal' AND rm.is_active = 1) AS focal_count,
                        (SELECT COUNT(*) FROM repository_members rm WHERE rm.repository_id = r.repository_id AND rm.member_role = 'accreditor' AND rm.is_active = 1) AS accreditor_count,
                        (SELECT COUNT(*) FROM repository_documents rd WHERE rd.repository_id = r.repository_id) AS document_count
                        FROM repositories r
                        LEFT JOIN programs p ON r.program_id = p.program_id
                        ORDER BY r.created_at DESC
                        LIMIT 6";
    $recent_repo_res = $conn->query($recent_repo_sql);
    if ($recent_repo_res) {
        while ($row = $recent_repo_res->fetch_assoc()) {
            $recent_repositories[] = $row;
        }
    }

    $recent_doc_sql = "SELECT rd.repository_document_id, rd.file_name, rd.document_status, rd.created_at,
                       r.repository_id, r.repository_name, rs.section_name,
                       CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) AS uploader_name
                       FROM repository_documents rd
                       JOIN repositories r ON rd.repository_id = r.repository_id
                       LEFT JOIN repository_sections rs ON rd.section_id = rs.section_id
                       JOIN users u ON rd.uploaded_by = u.user_id
                       ORDER BY rd.created_at DESC
                       LIMIT 8";
    $recent_doc_res = $conn->query($recent_doc_sql);
    if ($recent_doc_res) {
        while ($row = $recent_doc_res->fetch_assoc()) {
            $recent_documents[] = $row;
        }
    }

    $recent_comment_sql = "SELECT rc.comment_text, rc.created_at,
                           rd.repository_document_id, rd.file_name,
                           r.repository_id, r.repository_name,
                           CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) AS commenter_name
                           FROM repository_comments rc
                           JOIN repository_documents rd ON rc.repository_document_id = rd.repository_document_id
                           JOIN repositories r ON rd.repository_id = r.repository_id
                           JOIN users u ON rc.user_id = u.user_id
                           ORDER BY rc.created_at DESC
                           LIMIT 8";
    $recent_comment_res = $conn->query($recent_comment_sql);
    if ($recent_comment_res) {
        while ($row = $recent_comment_res->fetch_assoc()) {
            $recent_comments[] = $row;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard - BISU Accreditation</title>
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
      <a href="admin_dashboard.php" class="nav-item active"><i class="fas fa-th-large"></i> Dashboard</a>
      <a href="repositories.php" class="nav-item"><i class="fas fa-folder-tree"></i> Repositories</a>
      <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Manage Users</a>
      <a href="documents.php" class="nav-item"><i class="fas fa-file-alt"></i> Documents</a>
      <a href="reports.php" class="nav-item"><i class="fas fa-chart-bar"></i> Reports / Logs</a>
    </nav>
    <div class="mt-auto p-4">
      <a href="profile.php" class="nav-item"><i class="fas fa-user-cog"></i> Manage Profile</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900">Repository Dashboard</h1>
        <p class="text-sm text-slate-500">Monitor repository creation, focal work, accreditor review, and document activity in one place.</p>
      </div>
      <div class="flex flex-wrap gap-3">
        <a href="repositories.php" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 text-sm font-medium">
          <i class="fas fa-plus mr-2"></i>Create Repository
        </a>
        <a href="documents.php" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-white text-sm font-medium">
          <i class="fas fa-folder-open mr-2"></i>Review Files
        </a>
      </div>
    </div>

    <?php if (!$repo_tables_ready): ?>
      <div class="bg-amber-100 text-amber-800 p-4 rounded-lg border border-amber-200">
        Repository workflow tables are not ready yet. Run <a href="update_repository_schema.php" class="underline font-bold">update_repository_schema.php</a> first.
      </div>
    <?php else: ?>
      <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <div class="text-sm text-slate-500">Repositories</div>
          <div class="mt-2 text-3xl font-bold text-slate-900"><?= number_format($stats['repositories']) ?></div>
          <div class="mt-2 text-xs text-slate-400"><?= number_format($stats['draft_repositories']) ?> active draft/in review</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <div class="text-sm text-slate-500">Approved</div>
          <div class="mt-2 text-3xl font-bold text-slate-900"><?= number_format($stats['approved_repositories']) ?></div>
          <div class="mt-2 text-xs text-slate-400">Repositories finalized for accreditation</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <div class="text-sm text-slate-500">Repository Files</div>
          <div class="mt-2 text-3xl font-bold text-slate-900"><?= number_format($stats['documents']) ?></div>
          <div class="mt-2 text-xs text-slate-400"><?= number_format($stats['for_review_documents']) ?> still awaiting completion or review</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <div class="text-sm text-slate-500">Review Comments</div>
          <div class="mt-2 text-3xl font-bold text-slate-900"><?= number_format($stats['comments']) ?></div>
          <div class="mt-2 text-xs text-slate-400">Admin and accreditor feedback across repositories</div>
        </div>
      </section>

      <section class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <h2 class="font-bold text-slate-800 mb-4">Assignments</h2>
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-500">Active focal assignments</span>
              <span class="text-lg font-semibold text-slate-900"><?= number_format($stats['focal_assignments']) ?></span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-500">Active accreditor assignments</span>
              <span class="text-lg font-semibold text-slate-900"><?= number_format($stats['accreditor_assignments']) ?></span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-500">Folders / areas created</span>
              <span class="text-lg font-semibold text-slate-900"><?= number_format($stats['sections']) ?></span>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 lg:col-span-2">
          <h2 class="font-bold text-slate-800 mb-4">Quick Workflow</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="repositories.php" class="block rounded-xl border border-slate-200 p-4 hover:bg-slate-50">
              <div class="text-indigo-600 text-lg mb-2"><i class="fas fa-folder-plus"></i></div>
              <div class="font-semibold text-slate-800">Create and assign</div>
              <div class="text-sm text-slate-500 mt-1">Set repository details, focal persons, accreditors, and area folders.</div>
            </a>
            <a href="documents.php" class="block rounded-xl border border-slate-200 p-4 hover:bg-slate-50">
              <div class="text-indigo-600 text-lg mb-2"><i class="fas fa-file-signature"></i></div>
              <div class="font-semibold text-slate-800">Review repository files</div>
              <div class="text-sm text-slate-500 mt-1">Track uploads, update statuses, and jump into repository workspaces.</div>
            </a>
            <a href="reports.php" class="block rounded-xl border border-slate-200 p-4 hover:bg-slate-50">
              <div class="text-indigo-600 text-lg mb-2"><i class="fas fa-chart-line"></i></div>
              <div class="font-semibold text-slate-800">View activity summary</div>
              <div class="text-sm text-slate-500 mt-1">See repository counts, document volume, and review activity logs.</div>
            </a>
          </div>
        </div>
      </section>

      <section class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Recent Repositories</h2>
            <a href="repositories.php" class="text-sm text-indigo-600 hover:underline">View all</a>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead class="bg-white text-slate-500 border-b border-slate-100">
                <tr>
                  <th class="p-4 font-semibold">Repository</th>
                  <th class="p-4 font-semibold">Program</th>
                  <th class="p-4 font-semibold">Members</th>
                  <th class="p-4 font-semibold">Files</th>
                  <th class="p-4 font-semibold">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if (empty($recent_repositories)): ?>
                  <tr><td colspan="5" class="p-6 text-center text-slate-500">No repositories created yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($recent_repositories as $repo): ?>
                    <tr class="hover:bg-slate-50">
                      <td class="p-4">
                        <div class="font-medium text-slate-800"><?= htmlspecialchars($repo['repository_name']) ?></div>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($repo['school_year']) ?> / <?= (int) $repo['accreditation_year'] ?></div>
                      </td>
                      <td class="p-4 text-slate-600"><?= htmlspecialchars($repo['program_code'] ?: $repo['course_type'] ?: '-') ?></td>
                      <td class="p-4 text-slate-600"><?= (int) $repo['focal_count'] ?> focal / <?= (int) $repo['accreditor_count'] ?> accreditor</td>
                      <td class="p-4 text-slate-600"><?= (int) $repo['document_count'] ?></td>
                      <td class="p-4">
                        <a href="repository_workspace.php?repository_id=<?= (int) $repo['repository_id'] ?>" class="inline-flex items-center gap-2">
                          <span class="bg-slate-100 text-slate-700 px-2 py-1 rounded text-xs font-bold uppercase"><?= htmlspecialchars($repo['repository_status']) ?></span>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Latest Uploads</h2>
            <a href="documents.php" class="text-sm text-indigo-600 hover:underline">Open file manager</a>
          </div>
          <div class="divide-y divide-slate-100">
            <?php if (empty($recent_documents)): ?>
              <div class="p-6 text-center text-slate-500">No repository files uploaded yet.</div>
            <?php else: ?>
              <?php foreach ($recent_documents as $document): ?>
                <div class="p-4">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <div class="font-medium text-slate-800"><?= htmlspecialchars($document['file_name']) ?></div>
                      <div class="text-sm text-slate-500 mt-1"><?= htmlspecialchars($document['repository_name']) ?></div>
                      <div class="text-xs text-slate-400 mt-1">
                        <?= htmlspecialchars($document['section_name'] ?: 'Repository Root') ?> • <?= htmlspecialchars($document['uploader_name']) ?> • <?= date('M d, Y h:i A', strtotime($document['created_at'])) ?>
                      </div>
                    </div>
                    <span class="bg-slate-100 text-slate-700 px-2 py-1 rounded text-xs font-bold uppercase"><?= htmlspecialchars($document['document_status']) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200">
          <h2 class="font-bold text-slate-800">Recent Review Activity</h2>
        </div>
        <div class="divide-y divide-slate-100">
          <?php if (empty($recent_comments)): ?>
            <div class="p-6 text-center text-slate-500">No review comments yet.</div>
          <?php else: ?>
            <?php foreach ($recent_comments as $comment): ?>
              <div class="p-4 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
                <div>
                  <div class="font-medium text-slate-800"><?= htmlspecialchars($comment['commenter_name']) ?></div>
                  <div class="text-sm text-slate-500 mt-1">
                    On <a href="repository_workspace.php?repository_id=<?= (int) $comment['repository_id'] ?>" class="text-indigo-600 hover:underline"><?= htmlspecialchars($comment['repository_name']) ?></a>
                    for <?= htmlspecialchars($comment['file_name']) ?>
                  </div>
                  <div class="text-sm text-slate-600 mt-2"><?= htmlspecialchars(mb_strimwidth($comment['comment_text'], 0, 160, '...')) ?></div>
                </div>
                <div class="text-xs text-slate-400 whitespace-nowrap"><?= date('M d, Y h:i A', strtotime($comment['created_at'])) ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
