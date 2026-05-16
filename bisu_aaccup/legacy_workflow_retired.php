<?php
require_once __DIR__ . '/config/db.php';
requireAdminOrCliForMaintenance();

$script_name = $deprecated_legacy_script ?? basename(__FILE__);
$message = "The script {$script_name} belonged to the retired cycle/survey accreditation workflow and has been disabled.";
$next_step = "Use update_repository_schema.php for active schema setup, then manage accreditation work through repositories.php.";

if (PHP_SAPI === 'cli') {
    echo $message . PHP_EOL;
    echo $next_step . PHP_EOL;
    return;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Legacy Workflow Retired</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 flex items-center justify-center p-6">
  <div class="max-w-2xl w-full bg-white border border-slate-200 rounded-2xl shadow-sm p-8">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-xl font-bold">!</div>
      <div>
        <h1 class="text-2xl font-semibold text-slate-900">Legacy Workflow Retired</h1>
        <p class="text-sm text-slate-500">Repository-based accreditation is now the active system workflow.</p>
      </div>
    </div>
    <p class="text-slate-700 mb-3"><?= htmlspecialchars($message) ?></p>
    <p class="text-slate-600 mb-6"><?= htmlspecialchars($next_step) ?></p>
    <div class="flex flex-wrap gap-3">
      <a href="update_repository_schema.php" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Run Repository Schema</a>
      <a href="repositories.php" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">Open Repositories</a>
    </div>
  </div>
</body>
</html>
