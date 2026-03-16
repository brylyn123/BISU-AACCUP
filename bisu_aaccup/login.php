<?php 
session_start();
include 'config/db.php';
include 'nav.php'; 
?>
<main class="auth-page auth-login">
    <section class="auth-card login-container">
        <header class="login-header auth-card__header">
            <h1><i class="fas fa-lock"></i> Welcome Back</h1>
            <p>Login to AACCUP System</p>
        </header>
        <div class="login-content auth-card__body">
            <form action="login_process.php" method="POST">
                <div class="form-group" style="position: relative;">
                    <label>Email</label>
                    <input type="email" name="email" id="emailInput" placeholder="Enter Email" required autocomplete="off">
                    
                    <!-- Custom Dropdown for Emails -->
                    <div id="emailDropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #dfe6e9; border-radius: 0 0 10px 10px; max-height: 200px; overflow-y: auto; z-index: 1000; box-shadow: 0 10px 20px rgba(0,0,0,0.1);">
                        <div style="padding: 10px; background: #fff1f2; border-bottom: 1px solid #fecdd3; text-align: center;">
                            <a href="reset_demo.php" style="color: #e11d48; font-size: 0.8rem; font-weight: 600; text-decoration: none; display: block;">
                                <i class="fas fa-wrench"></i> Click here to Reset Passwords
                            </a>
                        </div>
                        <?php
                        $u_res = $conn->query("SELECT u.email, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id LIMIT 20");
                        if($u_res && $u_res->num_rows > 0) {
                            while($r = $u_res->fetch_assoc()) {
                                $e = htmlspecialchars($r['email']);
                                $role = htmlspecialchars($r['role_name']);
                                echo "<div onclick=\"selectEmail('$e')\" style='padding: 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;' onmouseover=\"this.style.background='#f8fafc'\" onmouseout=\"this.style.background='white'\">";
                                echo "<span style='font-weight: 500; color: #2d3436;'>$e</span>";
                                echo "<span style='font-size: 0.75rem; background: #e0e7ff; color: #4f46e5; padding: 2px 8px; border-radius: 12px;'>$role</span>";
                                echo "</div>";
                            }
                        }
                        ?>
                    </div>
                </div>
                <div class="form-group password-field">
                    <label>Password</label>
                    <div class="password-input-wrap">
                        <input type="password" name="password" id="passwordInput" placeholder="Enter Password" required>
                        <button type="button" class="password-toggle" data-target="passwordInput" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="login-btn">Login</button>
                <div class="signup-link">
                    Don't have an account? <a href="signup.php">Sign Up</a>
                </div>
            </form>
        </div>
    </section>
</main>

<script>
    const emailInput = document.getElementById('emailInput');
    const passwordInput = document.getElementById('passwordInput');
    const dropdown = document.getElementById('emailDropdown');

    // Show dropdown when input is clicked or focused
    emailInput.addEventListener('focus', () => dropdown.style.display = 'block');
    emailInput.addEventListener('click', () => dropdown.style.display = 'block');

    // Select email function
    function selectEmail(email) {
        emailInput.value = email;
        // Auto-fill password for development convenience
        if(passwordInput) passwordInput.value = '12345678';
        dropdown.style.display = 'none';
    }

    // Hide dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (!emailInput.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    });
</script>
<?php include 'footer.php'; ?>
