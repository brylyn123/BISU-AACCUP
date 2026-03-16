<?php
require_once 'config/db.php';

$msg = "";
if (isset($_POST['reset'])) {
    // Hash for '12345678'
    $new_pass = password_hash('12345678', PASSWORD_BCRYPT);
    
    if ($conn->query("UPDATE users SET password = '$new_pass'")) {
        $msg = "✅ Success! All passwords have been reset to: <strong>12345678</strong>";
    } else {
        $msg = "❌ Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Demo Passwords</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f1f5f9; margin: 0; }
        .card { background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; max-width: 400px; width: 90%; }
        h2 { margin-top: 0; color: #1e293b; }
        p { color: #64748b; line-height: 1.5; }
        .btn { background: #ef4444; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 1rem; font-weight: 600; margin-top: 1rem; width: 100%; transition: background 0.2s; }
        .btn:hover { background: #dc2626; }
        .back { display: block; margin-top: 1.5rem; color: #4f46e5; text-decoration: none; font-weight: 500; }
        .alert { background: #dcfce7; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 1rem; text-align: left; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Reset All Passwords</h2>
        <?php if($msg): ?><div class="alert"><?= $msg ?></div><?php endif; ?>
        <p>This tool is for development only. It will reset the password for <strong>ALL users</strong> to:</p>
        <code style="background:#f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 1.2rem; font-weight: bold; display: block; margin: 10px 0;">12345678</code>
        <form method="post"><button type="submit" name="reset" class="btn">Confirm Reset</button></form>
        <a href="login.php" class="back">← Back to Login</a>
    </div>
</body>
</html>