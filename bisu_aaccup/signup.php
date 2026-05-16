<?php 
session_start();
require_once 'config/db.php';

include 'nav.php'; 
?>
<main class="auth-page auth-signup">
    <section class="auth-card signup-container">
        <header class="signup-header auth-card__header">
            <h1><i class="fas fa-graduation-cap"></i> Create Account</h1>
            <p>BISU AACCUP Document Repository</p>
        </header>
        <div class="signup-content auth-card__body">
            <?php if (isset($_SESSION['error'])): ?>
                <div style="background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <form action="register_process.php" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" placeholder="Enter First Name" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" placeholder="Enter Middle Name">
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" placeholder="Enter Last Name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="Enter Email" required>
                </div>
                
                <div class="form-group">
                    <label>Role</label>
                    <select name="role_id" id="roleSelect" required>
                        <option value="">Select Role</option>
                        <?php
                        $admin_exists = false;
                        $check_admin = $conn->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'Admin'");
                        if ($check_admin && $check_admin->fetch_row()[0] > 0) {
                            $admin_exists = true;
                        }

                        $roles = $conn->query("SELECT * FROM roles WHERE role_name IN ('Admin', 'Faculty / Focal Person', 'Accreditor', 'Accreditor (Internal)', 'Accreditor (External)') ORDER BY role_name");
                        while($r = $roles->fetch_assoc()) {
                            if ($admin_exists && stripos($r['role_name'], 'Admin') !== false) continue;
                            echo "<option value='{$r['role_id']}'>{$r['role_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group password-field">
                    <label>Password</label>
                    <div class="password-input-wrap">
                        <input type="password" name="password" placeholder="Enter Password" id="password" required>
                        <button type="button" class="password-toggle" data-target="password" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group password-field">
                    <label>Confirm Password</label>
                    <div class="password-input-wrap">
                        <input type="password" name="confirm_password" placeholder="Confirm Password" id="confirm_password" required>
                        <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Toggle confirmation visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="password-feedback" class="password-feedback" aria-live="polite" style="margin-top:6px;font-size:0.95em;color:#e00"></div>
                </div>
                <button type="submit" class="signup-btn">Register</button>
                <div class="login-link">
                    Already have an account? <a href="login.php">Login</a>
                </div>
            </form>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
