<?php
session_start();
require_once __DIR__ . '/config/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');

$view = isset($_GET['view']) ? $_GET['view'] : 'logs';

// Logic for General Report
if ($view === 'general') {
    $graduate_programs = [];
    $undergraduate_programs = [];

    // Helper function for descriptive rating
    function getDescriptiveRating($mean) {
        if ($mean === null) return 'N/A';
        if ($mean >= 4.5) return 'Excellent';
        if ($mean >= 3.5) return 'Very Good';
        if ($mean >= 2.5) return 'Good';
        if ($mean >= 1.5) return 'Fair';
        return 'Needs Improvement';
    }

    // 1. Fetch all programs with their latest cycle info and average ratings
    $sql = "SELECT 
                p.program_id,
                p.program_name,
                p.program_code,
                c.level,
                c.valid_from,
                (SELECT AVG(sr.rating) FROM survey_ratings sr WHERE sr.program_id = p.program_id) as grand_mean
            FROM programs p
            LEFT JOIN (
                -- Subquery to get the latest valid cycle for each program
                SELECT 
                    program_id, 
                    level, 
                    valid_from,
                    ROW_NUMBER() OVER(PARTITION BY program_id ORDER BY valid_from DESC) as rn
                FROM cycles 
                WHERE status_id IN (1, 4) -- Active or Completed
            ) c ON p.program_id = c.program_id AND c.rn = 1
            ORDER BY p.program_name";
    
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Calculate end date (assuming 5 year validity)
            if ($row['valid_from']) {
                $startDate = new DateTime($row['valid_from']);
                $startDate->add(new DateInterval('P5Y')); // Add 5 years
                $row['valid_to'] = $startDate->format('Y-m-d');
            } else {
                $row['valid_to'] = null;
            }

            $row['descriptive_rating'] = getDescriptiveRating($row['grand_mean']);

            if (stripos($row['program_name'], 'Master') !== false || stripos($row['program_name'], 'Doctor') !== false) {
                $graduate_programs[] = $row;
            } else {
                $undergraduate_programs[] = $row;
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
  <title>Reports & Logs — Admin</title>
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
        <a href="documents.php" class="nav-item"><i class="fas fa-file-alt"></i> Documents</a>
        <a href="reports.php" class="nav-item active"><i class="fas fa-chart-bar"></i> Reports / Logs</a>
        <a href="admin_dashboard.php?view=self_survey" class="nav-item"><i class="fas fa-tasks"></i> Self Survey</a>
    </nav>
    <div class="mt-auto p-4">
        <a href="profile.php" class="nav-item"><i class="fas fa-user-cog"></i> Manage Profile</a>
    </div>
  </aside>

  <main class="main-content">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-bisu">Reports & Activity Logs</h1>
          <div class="text-sm text-slate-500">System activities and audit trails</div>
        </div>
        <div class="flex bg-white rounded-lg p-1 border border-slate-200">
            <a href="?view=logs" class="px-4 py-2 rounded-md text-sm font-medium transition-colors <?= $view === 'logs' ? 'bg-indigo-50 text-indigo-600' : 'text-slate-600 hover:bg-slate-50' ?>">
                <i class="fas fa-list-ul mr-2"></i> Activity Logs
            </a>
            <a href="?view=general" class="px-4 py-2 rounded-md text-sm font-medium transition-colors <?= $view === 'general' ? 'bg-indigo-50 text-indigo-600' : 'text-slate-600 hover:bg-slate-50' ?>">
                <i class="fas fa-table mr-2"></i> Accomplishment Report
            </a>
        </div>
      </div>

      <?php if ($view === 'logs'): ?>
      <div class="card">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-slate-700">System Activity Log</h3>
            <button class="btn btn-outline small"><i class="fas fa-download"></i> Export CSV</button>
        </div>
        <div class="flex items-center gap-3 mb-4">
          <input class="px-3 py-2 border rounded w-1/3" placeholder="Search logs...">
          <select class="px-3 py-2 border rounded"><option>All Activities</option><option>Login</option><option>Upload</option><option>Delete</option></select>
          <input type="date" class="px-3 py-2 border rounded">
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="text-slate-600">
                <th class="py-2">Timestamp</th>
                <th class="py-2">User</th>
                <th class="py-2">Action</th>
                <th class="py-2">Details</th>
                <th class="py-2">IP Address</th>
              </tr>
            </thead>
            <tbody class="text-slate-700">
              <tr class="border-t hover:bg-gray-50">
                <td class="py-3">2026-02-10 08:45:12</td>
                <td class="py-3 font-medium">Gina Galbo</td>
                <td class="py-3"><span class="px-2 py-1 bg-blue-50 text-blue-600 rounded text-xs font-semibold">Upload</span></td>
                <td class="py-3">Uploaded "BSCS_Level3_Area1.pdf"</td>
                <td class="py-3 text-slate-500">192.168.1.45</td>
              </tr>
              <tr class="border-t hover:bg-gray-50">
                <td class="py-3">2026-02-10 08:30:05</td>
                <td class="py-3 font-medium">Admin</td>
                <td class="py-3"><span class="px-2 py-1 bg-green-50 text-green-600 rounded text-xs font-semibold">Login</span></td>
                <td class="py-3">Successful login</td>
                <td class="py-3 text-slate-500">192.168.1.10</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <?php elseif ($view === 'general'): ?>
        <style>
            .report-container { background: white; padding: 2rem; border-radius: 8px; border: 1px solid #e2e8f0; }
            .report-header, .report-section { margin-bottom: 2rem; }
            .report-header h3 { font-size: 1.25rem; font-weight: bold; color: #1e293b; }
            .report-header p, .report-section p { font-size: 0.95rem; color: #475569; }
            .report-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
            .report-table th, .report-table td { border: 1px solid #cbd5e1; padding: 0.75rem; text-align: left; }
            .report-table th { background-color: #f1f5f9; font-weight: bold; }
            .report-table td { vertical-align: top; }
            .indented { margin-left: 2rem; }
            @media print {
                body * { visibility: hidden; }
                .printable-area, .printable-area * { visibility: visible; }
                .printable-area { position: absolute; left: 0; top: 0; width: 100%; }
                .no-print { display: none; }
                .main-content { margin: 0; padding: 0; }
                .sidebar, .topbar { display: none; }
            }
        </style>

        <div class="flex justify-end mb-4 no-print">
            <button onclick="window.print()" class="btn btn-primary small"><i class="fas fa-print"></i> Print Report</button>
        </div>

        <div class="report-container printable-area">
            <div class="report-header">
                <p><strong>Campus:</strong> Candijay Campus</p>
                <p><strong>Reporting Period:</strong> ___________________</p>
                <p><strong>Data Contributors:</strong> Gina M. Galbo, EdD (Campus QA Director)</p>
            </div>

            <div class="report-section">
                <h3 class="font-bold mb-2">General Instruction:</h3>
                <p class="indented">Please use this template in the preparation of your Campus Accomplishment Report. For uniformity of the report, kindly use the Arial font style, font size #11 and A4 size of paper.</p>
                <p class="indented">Please provide updated data and information on the achievements and accomplishments collected. Disaggregating data into male and female will also be very helpful. You may also add narratives to the data and figures which will be very essential in the consolidation of the University annual accomplishments.</p>
                <p class="indented">Thank you very much.</p>
            </div>

            <div class="report-section">
                <p><strong>DATA NEEDED:</strong> Accredited Programs</p>
                <p><strong>SUPPORTING DOCUMENTS:</strong> Annex I.C.1 Accreditation Certificates</p>
                <p><strong>TABULAR PRESENTATION OF DATA:</strong></p>
            </div>

            <!-- Table 13: Graduate Programs -->
            <div class="report-section">
                <h4 class="font-bold mb-2">Table 13. Accredited Graduate Programs</h4>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Graduate Programs<br>(please include majors)</th>
                            <th rowspan="2">Accreditation Status</th>
                            <th colspan="2">Validity of Accreditation</th>
                            <th rowspan="2">Rating (Grand Mean and Descriptive Rating)</th>
                        </tr>
                        <tr>
                            <th>Start Date</th>
                            <th>End Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($graduate_programs)): ?>
                            <tr><td colspan="5" class="text-center text-slate-500 py-4">No accredited graduate programs found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($graduate_programs as $prog): ?>
                                <tr>
                                    <td><?= htmlspecialchars($prog['program_name']) ?></td>
                                    <td><?= $prog['level'] ? 'Level ' . $prog['level'] : 'N/A' ?></td>
                                    <td><?= $prog['valid_from'] ? date('M d, Y', strtotime($prog['valid_from'])) : 'N/A' ?></td>
                                    <td><?= $prog['valid_to'] ? date('M d, Y', strtotime($prog['valid_to'])) : 'N/A' ?></td>
                                    <td>
                                        <?php if ($prog['grand_mean']): ?>
                                            <?= number_format($prog['grand_mean'], 2) ?> (<?= htmlspecialchars($prog['descriptive_rating']) ?>)
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table 14: Undergraduate Programs -->
            <div class="report-section">
                <h4 class="font-bold mb-2">Table 14. Accredited Undergraduate Programs</h4>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Undergraduate Programs<br>(please include majors)</th>
                            <th rowspan="2">Accreditation Status</th>
                            <th colspan="2">Validity of Accreditation</th>
                            <th rowspan="2">Rating (Grand Mean and Descriptive Rating)</th>
                        </tr>
                        <tr>
                            <th>Start Date</th>
                            <th>End Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($undergraduate_programs)): ?>
                            <tr><td colspan="5" class="text-center text-slate-500 py-4">No accredited undergraduate programs found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($undergraduate_programs as $prog): ?>
                                <tr>
                                    <td><?= htmlspecialchars($prog['program_name']) ?></td>
                                    <td><?= $prog['level'] ? 'Level ' . $prog['level'] : 'N/A' ?></td>
                                    <td><?= $prog['valid_from'] ? date('M d, Y', strtotime($prog['valid_from'])) : 'N/A' ?></td>
                                    <td><?= $prog['valid_to'] ? date('M d, Y', strtotime($prog['valid_to'])) : 'N/A' ?></td>
                                    <td>
                                        <?php if ($prog['grand_mean']): ?>
                                            <?= number_format($prog['grand_mean'], 2) ?> (<?= htmlspecialchars($prog['descriptive_rating']) ?>)
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="report-section">
                <h4 class="font-bold mb-2">Annex I.C.1 Accreditation Certificates</h4>
                <p class="indented">Note: As of this date no certificates from AACCUP are issued except the AACCUP Technical Review and Recommended Board Action (Please see attached below)</p>
                <ol class="list-decimal list-inside indented mt-2">
                    <li>BEED</li>
                    <li>BSHM</li>
                    <li>BSMB</li>
                    <li>BSED</li>
                    <li>BSFi</li>
                    <li>BSCS</li>
                    <li>BSES</li>
                    <li>BSOA</li>
                </ol>
            </div>
        </div>
      </div>
      <?php endif; ?>
  </main>
</body>
</html>