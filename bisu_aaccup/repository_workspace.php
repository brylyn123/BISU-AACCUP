<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = strtolower(trim($_SESSION['role'] ?? ''));
$is_admin = strpos($role, 'admin') !== false;
$is_focal = strpos($role, 'focal') !== false || strpos($role, 'faculty') !== false;
$is_accreditor = strpos($role, 'accreditor') !== false;

if (!$is_admin && !$is_focal && !$is_accreditor) {
    header('Location: role_home.php');
    exit;
}

$required_tables = ['repositories', 'repository_members', 'repository_sections', 'repository_documents', 'repository_comments'];
foreach ($required_tables as $table_name) {
    $check = $conn->query("SHOW TABLES LIKE '{$table_name}'");
    if (!$check || $check->num_rows === 0) {
        die("Repository workflow is not ready yet. Please run update_repository_schema.php first.");
    }
}

$repository_id = intval($_GET['repository_id'] ?? $_POST['repository_id'] ?? 0);
if ($repository_id <= 0) {
    die('Invalid repository.');
}

$user_id = intval($_SESSION['user_id']);
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'User');
$message = '';

$repo_stmt = $conn->prepare("SELECT r.*, p.program_code, p.program_name,
                             CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) AS creator_name
                             FROM repositories r
                             LEFT JOIN programs p ON r.program_id = p.program_id
                             JOIN users u ON r.created_by = u.user_id
                             WHERE r.repository_id = ?");
$repo_stmt->bind_param("i", $repository_id);
$repo_stmt->execute();
$repository = $repo_stmt->get_result()->fetch_assoc();

if (!$repository) {
    die('Repository not found.');
}

$member = null;
if (!$is_admin) {
    $member_stmt = $conn->prepare("SELECT * FROM repository_members WHERE repository_id = ? AND user_id = ? AND is_active = 1 LIMIT 1");
    $member_stmt->bind_param("ii", $repository_id, $user_id);
    $member_stmt->execute();
    $member = $member_stmt->get_result()->fetch_assoc();

    if (!$member) {
        http_response_code(403);
        die('Access denied.');
    }
}

$can_upload = $is_admin || ($member && $member['member_role'] === 'focal' && intval($member['can_upload']) === 1);
$can_review = $is_admin || ($member && $member['member_role'] === 'accreditor' && intval($member['can_review']) === 1);
$document_upload_types = [
    'pdf' => ['application/pdf'],
    'png' => ['image/png'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'doc' => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'xls' => ['application/vnd.ms-excel'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_section']) && $is_admin) {
    requireValidCsrfToken();
    $section_name = trim($_POST['section_name'] ?? '');
    $parent_section_id = !empty($_POST['parent_section_id']) ? intval($_POST['parent_section_id']) : null;
    $section_kind = ($_POST['section_kind'] ?? 'folder') === 'area' ? 'area' : 'folder';

    if ($section_name !== '') {
        $sort_query = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM repository_sections WHERE repository_id = ?");
        $sort_query->bind_param("i", $repository_id);
        $sort_query->execute();
        $next_sort = intval($sort_query->get_result()->fetch_assoc()['next_sort'] ?? 1);

        $stmt = $conn->prepare("INSERT INTO repository_sections (repository_id, parent_section_id, section_name, section_kind, sort_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iissi", $repository_id, $parent_section_id, $section_name, $section_kind, $next_sort);
        $stmt->execute();
        $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>Section added successfully.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_repository_document']) && $can_upload) {
    requireValidCsrfToken();
    $section_id = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
    $upload_error = null;
    $file_name = isset($_FILES['repository_file']['name']) ? basename($_FILES['repository_file']['name']) : '';
    $target_file = moveValidatedUpload($_FILES['repository_file'] ?? [], __DIR__ . '/uploads/repositories', $document_upload_types, $upload_error);

    if ($target_file !== null && $file_name !== '') {
        $mime_type = detectMimeType($target_file);
        $stmt = $conn->prepare("INSERT INTO repository_documents (repository_id, section_id, file_name, file_path, mime_type, uploaded_by, document_status) VALUES (?, ?, ?, ?, ?, ?, 'for_review')");
        $stmt->bind_param("iisssi", $repository_id, $section_id, $file_name, $target_file, $mime_type, $user_id);
        if ($stmt->execute()) {
            $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>Document uploaded to repository.</div>";
        } else {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Upload failed: " . htmlspecialchars($stmt->error) . "</div>";
        }
    } else {
        $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Error: " . htmlspecialchars($upload_error ?? 'Failed to upload file.') . "</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_repository_comment']) && $can_review) {
    requireValidCsrfToken();
    $repository_document_id = intval($_POST['repository_document_id'] ?? 0);
    $comment_text = trim($_POST['comment_text'] ?? '');

    if ($repository_document_id > 0 && $comment_text !== '') {
        $doc_check = $conn->prepare("SELECT repository_document_id FROM repository_documents WHERE repository_document_id = ? AND repository_id = ?");
        $doc_check->bind_param("ii", $repository_document_id, $repository_id);
        $doc_check->execute();
        if ($doc_check->get_result()->fetch_assoc()) {
            $comment_stmt = $conn->prepare("INSERT INTO repository_comments (repository_document_id, user_id, comment_text) VALUES (?, ?, ?)");
            $comment_stmt->bind_param("iis", $repository_document_id, $user_id, $comment_text);
            $comment_stmt->execute();

            $status_stmt = $conn->prepare("UPDATE repository_documents SET document_status = 'for_review' WHERE repository_document_id = ?");
            $status_stmt->bind_param("i", $repository_document_id);
            $status_stmt->execute();

            $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>Comment added successfully.</div>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_repository']) && $is_admin) {
    requireValidCsrfToken();
    $stmt = $conn->prepare("UPDATE repositories SET repository_status = 'approved', approved_at = NOW() WHERE repository_id = ?");
    $stmt->bind_param("i", $repository_id);
    $stmt->execute();
    $repository['repository_status'] = 'approved';
    $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>Repository approved successfully.</div>";
}

$sections = [];
$sections_stmt = $conn->prepare("SELECT * FROM repository_sections WHERE repository_id = ? AND is_active = 1 ORDER BY parent_section_id IS NULL DESC, sort_order ASC, section_name ASC");
$sections_stmt->bind_param("i", $repository_id);
$sections_stmt->execute();
$sections_res = $sections_stmt->get_result();
while ($row = $sections_res->fetch_assoc()) {
    $sections[] = $row;
}

$documents = [];
$docs_stmt = $conn->prepare("SELECT rd.*, rs.section_name, rs.section_kind,
                             CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) AS uploader_name
                             FROM repository_documents rd
                             LEFT JOIN repository_sections rs ON rd.section_id = rs.section_id
                             JOIN users u ON rd.uploaded_by = u.user_id
                             WHERE rd.repository_id = ?
                             ORDER BY rd.created_at DESC");
$docs_stmt->bind_param("i", $repository_id);
$docs_stmt->execute();
$docs_res = $docs_stmt->get_result();
while ($row = $docs_res->fetch_assoc()) {
    $documents[] = $row;
}

$comments_by_doc = [];
$comments_stmt = $conn->prepare("SELECT rc.*, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) AS commenter_name, r.role_name
                                 FROM repository_comments rc
                                 JOIN users u ON rc.user_id = u.user_id
                                 LEFT JOIN roles r ON u.role_id = r.role_id
                                 JOIN repository_documents rd ON rc.repository_document_id = rd.repository_document_id
                                 WHERE rd.repository_id = ?
                                 ORDER BY rc.created_at DESC");
$comments_stmt->bind_param("i", $repository_id);
$comments_stmt->execute();
$comments_res = $comments_stmt->get_result();
while ($row = $comments_res->fetch_assoc()) {
    $comments_by_doc[$row['repository_document_id']][] = $row;
}

$members = [];
$members_stmt = $conn->prepare("SELECT rm.*, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) AS full_name, u.email
                                FROM repository_members rm
                                JOIN users u ON rm.user_id = u.user_id
                                WHERE rm.repository_id = ? AND rm.is_active = 1
                                ORDER BY rm.member_role, full_name");
$members_stmt->bind_param("i", $repository_id);
$members_stmt->execute();
$members_res = $members_stmt->get_result();
while ($row = $members_res->fetch_assoc()) {
    $members[] = $row;
}

$back_link = 'repositories.php';
if ($is_focal) {
    $back_link = 'focal_dashboard.php';
} elseif ($is_accreditor) {
    $back_link = 'accreditor_dashboard.php';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Repository Workspace</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/admin-dashboard.css">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
  <header class="topbar">
    <div class="left">
      <a href="<?= htmlspecialchars($back_link) ?>" class="brand">
        <i class="fas fa-folder-tree"></i> <span>Repository Workspace</span>
      </a>
    </div>
    <div class="right">
      <div class="user-profile">
        <div class="user-info hidden sm:block">
          <span class="user-name"><?= $full_name ?></span>
          <span class="user-role"><?= htmlspecialchars($_SESSION['role'] ?? 'User') ?></span>
        </div>
        <div class="user-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
      </div>
      <div class="divider-vertical"></div>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 py-24">
    <div class="mb-6 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
      <div>
        <a href="<?= htmlspecialchars($back_link) ?>" class="text-sm text-indigo-600 hover:underline"><i class="fas fa-arrow-left mr-1"></i> Back</a>
        <h1 class="text-3xl font-bold text-slate-900 mt-2"><?= htmlspecialchars($repository['repository_name']) ?></h1>
        <p class="text-slate-500 mt-2">
          <?= htmlspecialchars($repository['school_year']) ?> • Accreditation Year <?= (int) $repository['accreditation_year'] ?>
          <?php if (!empty($repository['program_code'])): ?> • <?= htmlspecialchars($repository['program_code']) ?><?php endif; ?>
          <?php if (!empty($repository['course_type'])): ?> • <?= htmlspecialchars($repository['course_type']) ?><?php endif; ?>
        </p>
      </div>
      <div class="flex flex-wrap gap-2 items-center">
        <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-slate-100 text-slate-700"><?= htmlspecialchars($repository['repository_status']) ?></span>
        <?php if ($is_admin && $repository['repository_status'] !== 'approved'): ?>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="repository_id" value="<?= $repository_id ?>">
            <input type="hidden" name="approve_repository" value="1">
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">Approve Repository</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?= $message ?>

    <section class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="font-bold text-slate-800 mb-3">Repository Info</h2>
        <div class="space-y-2 text-sm text-slate-600">
          <div><strong>Created by:</strong> <?= htmlspecialchars($repository['creator_name']) ?></div>
          <div><strong>Status:</strong> <?= htmlspecialchars($repository['repository_status']) ?></div>
          <div><strong>Program:</strong> <?= htmlspecialchars($repository['program_name'] ?: 'Not linked') ?></div>
          <div><strong>Approved at:</strong> <?= $repository['approved_at'] ? htmlspecialchars($repository['approved_at']) : '-' ?></div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="font-bold text-slate-800 mb-3">Assigned Focal Persons</h2>
        <div class="space-y-2 text-sm text-slate-600">
          <?php
          $has_focal = false;
          foreach ($members as $member_row):
            if ($member_row['member_role'] !== 'focal') continue;
            $has_focal = true;
          ?>
            <div><?= htmlspecialchars($member_row['full_name']) ?> <span class="text-slate-400">• <?= htmlspecialchars($member_row['email']) ?></span></div>
          <?php endforeach; ?>
          <?php if (!$has_focal): ?><div class="text-slate-400">No focal persons assigned.</div><?php endif; ?>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="font-bold text-slate-800 mb-3">Assigned Accreditors</h2>
        <div class="space-y-2 text-sm text-slate-600">
          <?php
          $has_accreditor = false;
          foreach ($members as $member_row):
            if ($member_row['member_role'] !== 'accreditor') continue;
            $has_accreditor = true;
          ?>
            <div><?= htmlspecialchars($member_row['full_name']) ?> <span class="text-slate-400">• <?= htmlspecialchars($member_row['email']) ?></span></div>
          <?php endforeach; ?>
          <?php if (!$has_accreditor): ?><div class="text-slate-400">No accreditors assigned.</div><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="font-bold text-slate-800 mb-3">Sections</h2>
        <div class="space-y-2 text-sm text-slate-600">
          <?php if (empty($sections)): ?>
            <div class="text-slate-400">No sections created yet.</div>
          <?php else: ?>
            <?php foreach ($sections as $section): ?>
              <div class="flex items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2">
                <span><i class="fas <?= $section['section_kind'] === 'area' ? 'fa-layer-group' : 'fa-folder' ?> mr-2 text-slate-400"></i><?= htmlspecialchars($section['section_name']) ?></span>
                <span class="text-xs uppercase text-slate-400"><?= htmlspecialchars($section['section_kind']) ?></span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($can_upload): ?>
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="font-bold text-slate-800 mb-3">Upload Document</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
          <input type="hidden" name="repository_id" value="<?= $repository_id ?>">
          <input type="hidden" name="upload_repository_document" value="1">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Section</label>
            <select name="section_id" class="w-full p-2 border border-slate-200 rounded-lg">
              <option value="">General Repository Root</option>
              <?php foreach ($sections as $section): ?>
                <option value="<?= (int) $section['section_id'] ?>"><?= htmlspecialchars($section['section_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">File</label>
            <input type="file" name="repository_file" required accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx" class="w-full p-2 border border-slate-200 rounded-lg">
          </div>
          <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Upload</button>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($is_admin): ?>
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="font-bold text-slate-800 mb-3">Add Folder / Area</h2>
        <form method="POST" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
          <input type="hidden" name="repository_id" value="<?= $repository_id ?>">
          <input type="hidden" name="add_section" value="1">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Section Name</label>
            <input type="text" name="section_name" required class="w-full p-2 border border-slate-200 rounded-lg" placeholder="Example: Area I or Working Papers">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Parent Folder</label>
            <select name="parent_section_id" class="w-full p-2 border border-slate-200 rounded-lg">
              <option value="">Root</option>
              <?php foreach ($sections as $section): ?>
                <option value="<?= (int) $section['section_id'] ?>"><?= htmlspecialchars($section['section_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
            <select name="section_kind" class="w-full p-2 border border-slate-200 rounded-lg">
              <option value="folder">Folder</option>
              <option value="area">Area</option>
            </select>
          </div>
          <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900">Add Section</button>
        </form>
      </div>
      <?php endif; ?>
    </section>

    <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="p-4 border-b border-slate-200 bg-slate-50">
        <h2 class="font-bold text-slate-800">Repository Documents</h2>
      </div>
      <div class="p-4 space-y-6">
        <?php if (empty($documents)): ?>
          <div class="text-center text-slate-500 py-10">No repository documents uploaded yet.</div>
        <?php else: ?>
          <?php foreach ($documents as $document): ?>
            <div class="border border-slate-200 rounded-xl p-4">
              <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div>
                  <div class="flex items-center gap-2 text-slate-800 font-semibold">
                    <i class="fas fa-file-alt text-indigo-500"></i>
                    <?= htmlspecialchars($document['file_name']) ?>
                  </div>
                  <div class="text-sm text-slate-500 mt-1">
                    <?= htmlspecialchars($document['section_name'] ?: 'Repository Root') ?> • Uploaded by <?= htmlspecialchars($document['uploader_name']) ?> • <?= date('M d, Y h:i A', strtotime($document['created_at'])) ?>
                  </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="px-2 py-1 rounded text-xs font-bold uppercase bg-slate-100 text-slate-700"><?= htmlspecialchars($document['document_status']) ?></span>
                  <a href="<?= htmlspecialchars($document['file_path']) ?>" target="_blank" class="px-3 py-2 text-sm border border-slate-200 rounded-lg hover:bg-slate-50">Open</a>
                  <a href="<?= htmlspecialchars($document['file_path']) ?>" download class="px-3 py-2 text-sm border border-slate-200 rounded-lg hover:bg-slate-50">Download</a>
                </div>
              </div>

              <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-slate-50 rounded-lg p-4">
                  <h3 class="font-medium text-slate-800 mb-3">Comments</h3>
                  <div class="space-y-3">
                    <?php $doc_comments = $comments_by_doc[$document['repository_document_id']] ?? []; ?>
                    <?php if (empty($doc_comments)): ?>
                      <div class="text-sm text-slate-400">No comments yet.</div>
                    <?php else: ?>
                      <?php foreach ($doc_comments as $comment): ?>
                        <div class="bg-white border border-slate-200 rounded-lg p-3">
                          <div class="text-sm font-semibold text-slate-700">
                            <?= htmlspecialchars($comment['commenter_name']) ?>
                            <span class="text-xs text-slate-400 font-normal">• <?= htmlspecialchars($comment['role_name'] ?: 'User') ?></span>
                          </div>
                          <div class="text-sm text-slate-600 mt-1 whitespace-pre-wrap"><?= htmlspecialchars($comment['comment_text']) ?></div>
                          <div class="text-xs text-slate-400 mt-2"><?= date('M d, Y h:i A', strtotime($comment['created_at'])) ?></div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>

                <?php if ($can_review): ?>
                <div class="bg-white border border-slate-200 rounded-lg p-4">
                  <h3 class="font-medium text-slate-800 mb-3"><?= $is_admin ? 'Admin Review' : 'Accreditor Review' ?></h3>
                  <form method="POST" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <input type="hidden" name="repository_id" value="<?= $repository_id ?>">
                    <input type="hidden" name="repository_document_id" value="<?= (int) $document['repository_document_id'] ?>">
                    <input type="hidden" name="add_repository_comment" value="1">
                    <textarea name="comment_text" required class="w-full p-3 border border-slate-300 rounded-lg min-h-[120px]" placeholder="Write missing items, corrections, or review notes..."></textarea>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save Comment</button>
                  </form>
                </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html>
