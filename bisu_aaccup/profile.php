<?php
session_start();
require_once __DIR__ . '/config/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];
$msg = '';
$error = '';

// Handle Avatar Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5 MB

        if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
            $upload_dir = 'uploads/avatars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
            $new_filepath = $upload_dir . $new_filename;

            // Fetch old avatar to delete it later
            $old_avatar_stmt = $conn->prepare("SELECT avatar_path FROM users WHERE user_id = ?");
            $old_avatar_stmt->bind_param("i", $user_id);
            $old_avatar_stmt->execute();
            $old_avatar_path = $old_avatar_stmt->get_result()->fetch_assoc()['avatar_path'] ?? null;

            if (move_uploaded_file($file['tmp_name'], $new_filepath)) {
                $stmt = $conn->prepare("UPDATE users SET avatar_path = ? WHERE user_id = ?");
                $stmt->bind_param("si", $new_filepath, $user_id);
                if ($stmt->execute()) {
                    $_SESSION['avatar_path'] = $new_filepath; // Update session
                    // Delete old avatar if it exists and is not the default
                    if ($old_avatar_path && file_exists($old_avatar_path)) {
                        unlink($old_avatar_path);
                    }
                    $msg = "Profile picture updated successfully!";
                } else { $error = "Failed to update database."; }
            } else { $error = "Failed to move uploaded file."; }
        } else { $error = "Invalid file type or size too large (Max 5MB)."; }
    } else { $error = "File upload error."; }
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);

    if ($first_name !== '' && $last_name !== '' && !empty($email)) {
        // Check if email is already taken by another user
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $error = "Email is already in use by another account.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET firstname = ?, middlename = ?, lastname = ?, email = ? WHERE user_id = ?");
            $stmt->bind_param("ssssi", $first_name, $middle_name, $last_name, $email, $user_id);
            if ($stmt->execute()) {
                $full_name = trim(implode(' ', array_filter([$first_name, $middle_name, $last_name], fn($part) => $part !== '')));
                $_SESSION['full_name'] = $full_name; // Update session
                $msg = "Profile updated successfully!";
            } else {
                $error = "Failed to update profile.";
            }
        }
    } else {
        $error = "First name, last name, and email cannot be empty.";
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Fetch current password hash
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($current_password, $user['password'])) {
        if (strlen($new_password) < 8) {
            $error = "New password must be at least 8 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_password_hash, $user_id);
            if ($stmt->execute()) {
                $msg = "Password changed successfully!";
            } else {
                $error = "Failed to change password.";
            }
        }
    } else {
        $error = "Incorrect current password.";
    }
}

$stmt = $conn->prepare("SELECT CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) AS full_name,
                        u.firstname, u.middlename, u.lastname,
                        u.email, u.avatar_path, r.role_name, 
                        c.college_code, cp.college_code as program_college_code
                        FROM users u 
                        JOIN roles r ON u.role_id = r.role_id 
                        LEFT JOIN colleges c ON u.college_id = c.college_id
                        LEFT JOIN programs p ON u.program_id = p.program_id
                        LEFT JOIN colleges cp ON p.college_id = cp.college_id
                        WHERE u.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Determine Theme based on Role and College
$role_name = strtolower($user['role_name'] ?? '');
$college_code = strtoupper($user['college_code'] ?? $user['program_college_code'] ?? '');

// Default Theme (Admin / Accreditor / Unassigned) - Indigo/Purple
$theme = [
    'gradient' => 'from-indigo-600 to-purple-700',
    'text_accent' => 'text-indigo-600',
    'bg_light' => 'bg-indigo-50',
    'btn_primary' => 'bg-indigo-600 hover:bg-indigo-700 shadow-indigo-200',
    'ring' => 'focus:ring-indigo-500',
    'border_focus' => 'focus:border-indigo-500'
];

// Override for College Roles
if (strpos($role_name, 'admin') === false && strpos($role_name, 'accreditor') === false) {
    if ($college_code === 'COS') { // Green
        $theme['gradient'] = 'from-green-600 to-emerald-800';
        $theme['text_accent'] = 'text-green-600';
        $theme['bg_light'] = 'bg-green-50';
        $theme['btn_primary'] = 'bg-green-600 hover:bg-green-700 shadow-green-200';
        $theme['ring'] = 'focus:ring-green-500';
        $theme['border_focus'] = 'focus:border-green-500';
    } elseif ($college_code === 'CTE') { // Red/Rose
        $theme['gradient'] = 'from-rose-600 to-red-800';
        $theme['text_accent'] = 'text-rose-600';
        $theme['bg_light'] = 'bg-rose-50';
        $theme['btn_primary'] = 'bg-rose-600 hover:bg-rose-700 shadow-rose-200';
        $theme['ring'] = 'focus:ring-rose-500';
        $theme['border_focus'] = 'focus:border-rose-500';
    } elseif ($college_code === 'CBM') { // Amber/Orange
        $theme['gradient'] = 'from-amber-500 to-orange-700';
        $theme['text_accent'] = 'text-amber-600';
        $theme['bg_light'] = 'bg-amber-50';
        $theme['btn_primary'] = 'bg-amber-600 hover:bg-amber-700 shadow-amber-200';
        $theme['ring'] = 'focus:ring-amber-500';
        $theme['border_focus'] = 'focus:border-amber-500';
    } elseif ($college_code === 'CFMS') { // Blue
        $theme['gradient'] = 'from-blue-600 to-blue-800';
        $theme['text_accent'] = 'text-blue-600';
        $theme['bg_light'] = 'bg-blue-50';
        $theme['btn_primary'] = 'bg-blue-600 hover:bg-blue-700 shadow-blue-200';
        $theme['ring'] = 'focus:ring-blue-500';
        $theme['border_focus'] = 'focus:border-blue-500';
    }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Profile</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/admin-dashboard.css">
  <style>
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
    }
  </style>
</head>
<body class="bg-slate-50">
  
  <!-- Header Background -->
  <div class="h-64 w-full bg-gradient-to-r <?= $theme['gradient'] ?> absolute top-0 left-0 z-0">
    <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"></div>
  </div>

  <div class="relative z-10 max-w-5xl mx-auto px-4 py-8">
    <!-- Top Navigation -->
    <div class="flex justify-between items-center mb-8">
        <div class="text-white">
            <h1 class="text-3xl font-bold drop-shadow-md">My Profile</h1>
            <p class="text-white/80 text-sm">Manage your account settings and security</p>
        </div>
        <a href="role_home.php" class="inline-flex items-center justify-center px-4 py-2 bg-white/20 backdrop-blur-sm border border-white/30 rounded-lg text-sm font-medium text-white hover:bg-white/30 transition-colors shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Profile Card -->
        <div class="lg:col-span-1">
            <div class="glass-card rounded-2xl shadow-xl border border-white/50 p-8 text-center relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-b <?= $theme['gradient'] ?> opacity-10"></div>
                <form method="POST" enctype="multipart/form-data" class="relative w-28 h-28 mx-auto mb-4 group">
                    <label for="avatarUpload" class="cursor-pointer">
                        <?php if (!empty($user['avatar_path']) && file_exists($user['avatar_path'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar_path']) ?>" alt="Avatar" class="w-28 h-28 rounded-full object-cover bg-white p-1 shadow-lg">
                        <?php else: ?>
                            <div class="relative w-28 h-28 rounded-full bg-white p-1 shadow-lg">
                                <div class="w-full h-full rounded-full <?= $theme['bg_light'] ?> <?= $theme['text_accent'] ?> flex items-center justify-center text-4xl font-bold">
                                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="absolute inset-0 rounded-full bg-black/50 flex items-center justify-center text-white opacity-0 group-hover:opacity-100 transition-opacity">
                            <i class="fas fa-camera text-2xl"></i>
                        </div>
                    </label>
                    <input type="file" id="avatarUpload" name="avatar" accept="image/*" class="hidden" onchange="this.form.submit()">
                </form>
                
                <h2 class="text-xl font-bold text-slate-800 mb-1"><?= htmlspecialchars($user['full_name']) ?></h2>
                <p class="text-slate-500 text-sm mb-4"><?= htmlspecialchars($user['email']) ?></p>
                
                <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?= $theme['bg_light'] ?> <?= $theme['text_accent'] ?>">
                    <?= htmlspecialchars($user['role_name']) ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Forms -->
        <div class="lg:col-span-2 space-y-6">
            
            <?php if ($msg): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-r shadow-sm flex items-center animate-fade-in-down" role="alert">
                    <i class="fas fa-check-circle mr-3 text-xl"></i> <p><?= $msg ?></p>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-r shadow-sm flex items-center animate-fade-in-down" role="alert">
                    <i class="fas fa-exclamation-circle mr-3 text-xl"></i> <p><?= $error ?></p>
                </div>
            <?php endif; ?>

            <!-- Edit Details -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
                    <div class="w-10 h-10 rounded-lg <?= $theme['bg_light'] ?> <?= $theme['text_accent'] ?> flex items-center justify-center"><i class="fas fa-user-edit"></i></div>
                    <h2 class="text-xl font-bold text-slate-800">Personal Information</h2>
                </div>
                <form method="POST" class="space-y-5">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($user['firstname'] ?? '') ?>" required class="w-full p-3 border border-slate-300 rounded-xl outline-none <?= $theme['ring'] ?> <?= $theme['border_focus'] ?> transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Middle Name</label>
                            <input type="text" name="middle_name" value="<?= htmlspecialchars($user['middlename'] ?? '') ?>" class="w-full p-3 border border-slate-300 rounded-xl outline-none <?= $theme['ring'] ?> <?= $theme['border_focus'] ?> transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($user['lastname'] ?? '') ?>" required class="w-full p-3 border border-slate-300 rounded-xl outline-none <?= $theme['ring'] ?> <?= $theme['border_focus'] ?> transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="w-full p-3 border border-slate-300 rounded-xl outline-none <?= $theme['ring'] ?> <?= $theme['border_focus'] ?> transition-all">
                    </div>
                    <div class="pt-2 text-right">
                        <button type="submit" name="update_profile" class="px-6 py-3 <?= $theme['btn_primary'] ?> text-white rounded-xl font-semibold shadow-lg transition-all transform hover:-translate-y-0.5">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
                    <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center"><i class="fas fa-lock"></i></div>
                    <h2 class="text-xl font-bold text-slate-800">Security</h2>
                </div>
                <form method="POST" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Current Password</label>
                        <input type="password" name="current_password" required class="w-full p-3 border border-slate-300 rounded-xl outline-none <?= $theme['ring'] ?> <?= $theme['border_focus'] ?> transition-all">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">New Password</label>
                            <input type="password" name="new_password" required class="w-full p-3 border border-slate-300 rounded-xl outline-none <?= $theme['ring'] ?> <?= $theme['border_focus'] ?> transition-all" placeholder="Min. 8 characters">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Confirm New Password</label>
                            <input type="password" name="confirm_password" required class="w-full p-3 border border-slate-300 rounded-xl outline-none <?= $theme['ring'] ?> <?= $theme['border_focus'] ?> transition-all">
                        </div>
                    </div>
                    <div class="pt-2 text-right">
                        <button type="submit" name="change_password" class="px-6 py-3 bg-slate-800 text-white rounded-xl font-semibold hover:bg-slate-900 shadow-lg shadow-slate-200 transition-all transform hover:-translate-y-0.5">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
  </div>
</body>
</html>
