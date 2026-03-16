<?php
session_start();
require_once __DIR__ . '/config/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Areas — Admin</title>
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
          <h1 class="text-2xl font-semibold text-bisu">Manage Areas by Level</h1>
          <div class="text-sm text-slate-500">Select an accreditation level to view its areas</div>
        </div>
      </div>

      <!-- Level Selection -->
      <div id="levelSelection" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Level 1 Card -->
        <div onclick="showLevel('level1')" class="bg-white p-8 rounded-xl border border-slate-200 shadow-sm cursor-pointer hover:shadow-md hover:border-blue-500 transition-all group">
            <div class="flex items-center justify-between mb-4">
                <div class="p-4 bg-blue-50 text-blue-600 rounded-full group-hover:bg-blue-600 group-hover:text-white transition-colors">
                    <i class="fas fa-star fa-2x"></i>
                </div>
                <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-semibold">10 Areas</span>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Level 1 Accreditation</h3>
            <p class="text-slate-500 text-sm">Manage areas, parameters, and indicators for Level 1 status.</p>
        </div>

        <!-- Level 2 Card -->
        <div onclick="showLevel('level2')" class="bg-white p-8 rounded-xl border border-slate-200 shadow-sm cursor-pointer hover:shadow-md hover:border-indigo-500 transition-all group">
            <div class="flex items-center justify-between mb-4">
                <div class="p-4 bg-indigo-50 text-indigo-600 rounded-full group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                    <i class="fas fa-layer-group fa-2x"></i>
                </div>
                <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-semibold">10 Areas</span>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Level 2 Accreditation</h3>
            <p class="text-slate-500 text-sm">Manage areas, parameters, and indicators for Level 2 status.</p>
        </div>

        <!-- Level 3 Card -->
        <div onclick="showLevel('level3')" class="bg-white p-8 rounded-xl border border-slate-200 shadow-sm cursor-pointer hover:shadow-md hover:border-emerald-500 transition-all group">
            <div class="flex items-center justify-between mb-4">
                <div class="p-4 bg-emerald-50 text-emerald-600 rounded-full group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                    <i class="fas fa-certificate fa-2x"></i>
                </div>
                <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-semibold">10 Areas</span>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Level 3 Accreditation</h3>
            <p class="text-slate-500 text-sm">Manage areas, parameters, and indicators for Level 3 status.</p>
        </div>

        <!-- Level 4 Card -->
        <div onclick="showLevel('level4')" class="bg-white p-8 rounded-xl border border-slate-200 shadow-sm cursor-pointer hover:shadow-md hover:border-amber-500 transition-all group">
            <div class="flex items-center justify-between mb-4">
                <div class="p-4 bg-amber-50 text-amber-600 rounded-full group-hover:bg-amber-600 group-hover:text-white transition-colors">
                    <i class="fas fa-trophy fa-2x"></i>
                </div>
                <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-semibold">10 Areas</span>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Level 4 Accreditation</h3>
            <p class="text-slate-500 text-sm">Manage areas, parameters, and indicators for Level 4 status.</p>
        </div>
      </div>

      <!-- Level 1 Content -->
      <div id="level1Content" class="hidden">
        <div class="flex items-center justify-between mb-6">
            <button onclick="showSelection()" class="btn bg-white border border-slate-200 text-slate-600 hover:bg-blue-50 hover:text-blue-600 hover:border-blue-200 shadow-sm group">
                <i class="fas fa-arrow-left transition-transform group-hover:-translate-x-1"></i> Back to Levels
            </button>
            <button class="btn btn-primary bg-blue-600 hover:bg-blue-700 border-blue-600">+ Add Area to Level 1</button>
        </div>
        <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 mb-6 flex items-center gap-3">
            <i class="fas fa-info-circle text-blue-600"></i>
            <span class="text-blue-800 font-medium">Viewing Level 1 Areas</span>
        </div>
        <div class="card">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="text-slate-600"><th class="py-2">Area Name</th><th class="py-2">Description</th><th class="py-2">Actions</th></tr>
            </thead>
            <tbody class="text-slate-700">
              <tr class="border-t hover:bg-gray-50"><td class="py-3 font-medium">Area I</td><td class="py-3">Vision, Mission, Goals and Objectives</td><td class="py-3"><a href="#" class="mr-3 text-slate-500 hover:text-blue-600"><i class="fas fa-pen"></i></a><a href="#" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></a></td></tr>
              <tr class="border-t hover:bg-gray-50"><td class="py-3 font-medium">Area II</td><td class="py-3">Faculty</td><td class="py-3"><a href="#" class="mr-3 text-slate-500 hover:text-blue-600"><i class="fas fa-pen"></i></a><a href="#" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></a></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Level 2 Content -->
      <div id="level2Content" class="hidden">
        <div class="flex items-center justify-between mb-6">
            <button onclick="showSelection()" class="btn bg-white border border-slate-200 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 shadow-sm group">
                <i class="fas fa-arrow-left transition-transform group-hover:-translate-x-1"></i> Back to Levels
            </button>
            <button class="btn btn-primary">+ Add Area to Level 2</button>
        </div>
        <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-4 mb-6 flex items-center gap-3">
            <i class="fas fa-info-circle text-indigo-600"></i>
            <span class="text-indigo-800 font-medium">Viewing Level 2 Areas</span>
        </div>
        <div class="card">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="text-slate-600"><th class="py-2">Area Name</th><th class="py-2">Description</th><th class="py-2">Actions</th></tr>
            </thead>
            <tbody class="text-slate-700">
              <tr class="border-t hover:bg-gray-50"><td class="py-3 font-medium">Area I</td><td class="py-3">Vision, Mission, Goals and Objectives</td><td class="py-3"><a href="#" class="mr-3 text-slate-500 hover:text-indigo-600"><i class="fas fa-pen"></i></a><a href="#" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></a></td></tr>
              <tr class="border-t hover:bg-gray-50"><td class="py-3 font-medium">Area II</td><td class="py-3">Faculty</td><td class="py-3"><a href="#" class="mr-3 text-slate-500 hover:text-indigo-600"><i class="fas fa-pen"></i></a><a href="#" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></a></td></tr>
              <tr class="border-t hover:bg-gray-50"><td class="py-3 font-medium">Area III</td><td class="py-3">Curriculum and Instruction</td><td class="py-3"><a href="#" class="mr-3 text-slate-500 hover:text-indigo-600"><i class="fas fa-pen"></i></a><a href="#" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></a></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Level 3 Content -->
      <div id="level3Content" class="hidden">
        <div class="flex items-center justify-between mb-6">
            <button onclick="showSelection()" class="btn bg-white border border-slate-200 text-slate-600 hover:bg-emerald-50 hover:text-emerald-600 hover:border-emerald-200 shadow-sm group">
                <i class="fas fa-arrow-left transition-transform group-hover:-translate-x-1"></i> Back to Levels
            </button>
            <button class="btn btn-primary bg-emerald-600 hover:bg-emerald-700 border-emerald-600">+ Add Area to Level 3</button>
        </div>
        <div class="bg-emerald-50 border border-emerald-100 rounded-lg p-4 mb-6 flex items-center gap-3">
            <i class="fas fa-info-circle text-emerald-600"></i>
            <span class="text-emerald-800 font-medium">Viewing Level 3 Areas</span>
        </div>
        <div class="card">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="text-slate-600"><th class="py-2">Area Name</th><th class="py-2">Description</th><th class="py-2">Actions</th></tr>
            </thead>
            <tbody class="text-slate-700">
              <tr class="border-t hover:bg-gray-50"><td class="py-3 font-medium">Area I</td><td class="py-3">Vision, Mission, Goals and Objectives (Level 3)</td><td class="py-3"><a href="#" class="mr-3 text-slate-500 hover:text-emerald-600"><i class="fas fa-pen"></i></a><a href="#" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></a></td></tr>
              <tr class="border-t hover:bg-gray-50"><td class="py-3 font-medium">Area II</td><td class="py-3">Faculty (Level 3)</td><td class="py-3"><a href="#" class="mr-3 text-slate-500 hover:text-emerald-600"><i class="fas fa-pen"></i></a><a href="#" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></a></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Level 4 Content -->
      <div id="level4Content" class="hidden">
        <div class="flex items-center justify-between mb-6">
            <button onclick="showSelection()" class="btn bg-white border border-slate-200 text-slate-600 hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200 shadow-sm group">
                <i class="fas fa-arrow-left transition-transform group-hover:-translate-x-1"></i> Back to Levels
            </button>
            <button class="btn btn-primary bg-amber-600 hover:bg-amber-700 border-amber-600">+ Add Area to Level 4</button>
        </div>
        <div class="bg-amber-50 border border-amber-100 rounded-lg p-4 mb-6 flex items-center gap-3">
            <i class="fas fa-info-circle text-amber-600"></i>
            <span class="text-amber-800 font-medium">Viewing Level 4 Areas</span>
        </div>
        <div class="card">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="text-slate-600"><th class="py-2">Area Name</th><th class="py-2">Description</th><th class="py-2">Actions</th></tr>
            </thead>
            <tbody class="text-slate-700">
              <tr class="border-t hover:bg-gray-50"><td class="py-3 font-medium">Area I</td><td class="py-3">Vision, Mission, Goals and Objectives (Level 4)</td><td class="py-3"><a href="#" class="mr-3 text-slate-500 hover:text-amber-600"><i class="fas fa-pen"></i></a><a href="#" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></a></td></tr>
              <tr class="border-t hover:bg-gray-50"><td class="py-3 font-medium">Area II</td><td class="py-3">Faculty (Level 4)</td><td class="py-3"><a href="#" class="mr-3 text-slate-500 hover:text-amber-600"><i class="fas fa-pen"></i></a><a href="#" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></a></td></tr>
            </tbody>
          </table>
        </div>
      </div>
  </main>

  <script>
    function showLevel(level) {
        document.getElementById('levelSelection').classList.add('hidden');
        document.getElementById(level + 'Content').classList.remove('hidden');
    }
    function showSelection() {
        document.getElementById('levelSelection').classList.remove('hidden');
        document.getElementById('level1Content').classList.add('hidden');
        document.getElementById('level2Content').classList.add('hidden');
        document.getElementById('level3Content').classList.add('hidden');
        document.getElementById('level4Content').classList.add('hidden');
    }
  </script>
</body>
</html>
