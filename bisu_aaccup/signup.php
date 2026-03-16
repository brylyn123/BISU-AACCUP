<?php 
session_start();
require_once 'config/db.php';

// --- AJAX Handler for Dynamic Dropdowns ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'get_available_colleges') {
        // Get colleges that do NOT have a Dean
        $role_res = $conn->query("SELECT role_id FROM roles WHERE role_name = 'Dean' LIMIT 1");
        $dean_role_id = $role_res->fetch_row()[0] ?? 0;

        $stmt = $conn->prepare("SELECT college_id FROM users WHERE role_id = ? AND college_id IS NOT NULL");
        $stmt->bind_param("i", $dean_role_id);
        $stmt->execute();
        $taken = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $taken_ids = array_column($taken, 'college_id');

        $sql = "SELECT * FROM colleges";
        if (!empty($taken_ids)) {
            $ids = implode(',', array_map('intval', $taken_ids));
            $sql .= " WHERE college_id NOT IN ($ids)";
        }
        $sql .= " ORDER BY college_name";
        echo json_encode($conn->query($sql)->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    if ($action === 'get_available_programs') {
        // Get programs that do NOT have a Chairperson
        $role_res = $conn->query("SELECT role_id FROM roles WHERE role_name = 'Chairperson' LIMIT 1");
        $chair_role_id = $role_res->fetch_row()[0] ?? 0;

        $stmt = $conn->prepare("SELECT program_id FROM users WHERE role_id = ? AND program_id IS NOT NULL");
        $stmt->bind_param("i", $chair_role_id);
        $stmt->execute();
        $taken = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $taken_ids = array_column($taken, 'program_id');

        $sql = "SELECT * FROM programs";
        if (!empty($taken_ids)) {
            $ids = implode(',', array_map('intval', $taken_ids));
            $sql .= " WHERE program_id NOT IN ($ids)";
        }
        $sql .= " ORDER BY program_code";
        echo json_encode($conn->query($sql)->fetch_all(MYSQLI_ASSOC));
        exit;
    }
}

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
                
                <!-- Role Selection First -->
                <div class="form-group">
                    <label>Role</label>
                    <select name="role_id" id="roleSelect" required onchange="toggleFields()">
                        <option value="">Select Role</option>
                        <?php
                        // Check if Admin exists
                        $admin_exists = false;
                        $check_admin = $conn->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'Admin'");
                        if ($check_admin && $check_admin->fetch_row()[0] > 0) {
                            $admin_exists = true;
                        }

                        $roles = $conn->query("SELECT * FROM roles");
                        while($r = $roles->fetch_assoc()) {
                            if ($admin_exists && stripos($r['role_name'], 'Admin') !== false) continue;
                            echo "<option value='{$r['role_id']}' data-name='" . strtolower($r['role_name']) . "'>{$r['role_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Program Selection (For Faculty/Focal) -->
                <div class="form-group" id="programGroup" style="display:none;">
                    <label>Department / Program</label>
                    <select name="program_id" id="programSelect">
                        <option value="">Select Program</option>
                        <?php
                        $progs = $conn->query("SELECT * FROM programs ORDER BY program_code");
                        while($p = $progs->fetch_assoc()) {
                            echo "<option value='{$p['program_id']}'>{$p['program_code']} - {$p['program_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- College Selection (For Dean/Chairperson) -->
                <div class="form-group" id="collegeGroup" style="display:none;">
                    <label>College / Department</label>
                    <select name="college_id" id="collegeSelect">
                        <option value="">Select College</option>
                        <?php
                        $cols = $conn->query("SELECT * FROM colleges ORDER BY college_name");
                        while($c = $cols->fetch_assoc()) {
                            echo "<option value='{$c['college_id']}'>{$c['college_code']} - {$c['college_name']}</option>";
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

<script>
// Store original options to restore them for Faculty/Focal roles
let originalProgramOptions = "";
let originalCollegeOptions = "";

document.addEventListener('DOMContentLoaded', () => {
    originalProgramOptions = document.getElementById('programSelect').innerHTML;
    originalCollegeOptions = document.getElementById('collegeSelect').innerHTML;
});

function toggleFields() {
    const roleSelect = document.getElementById('roleSelect');
    const programGroup = document.getElementById('programGroup');
    const collegeGroup = document.getElementById('collegeGroup');
    const programSelect = document.getElementById('programSelect');
    const collegeSelect = document.getElementById('collegeSelect');

    // Get selected role name (lowercase)
    const selectedOption = roleSelect.options[roleSelect.selectedIndex];
    const roleName = selectedOption.getAttribute('data-name') || '';

    // Reset visibility and requirements
    programGroup.style.display = 'none';
    collegeGroup.style.display = 'none';
    programSelect.required = false;
    collegeSelect.required = false;

    if (roleName.includes('dean')) {
        collegeGroup.style.display = 'block';
        collegeSelect.required = true;
        programSelect.value = "";
        
        // Fetch only available colleges
        fetch('signup.php?action=get_available_colleges')
            .then(res => res.json())
            .then(data => {
                collegeSelect.innerHTML = '<option value="">Select College</option>';
                data.forEach(c => {
                    collegeSelect.innerHTML += `<option value="${c.college_id}">${c.college_code} - ${c.college_name}</option>`;
                });
            });

    } else if (roleName.includes('chairperson')) {
        programGroup.style.display = 'block';
        programSelect.required = true;
        collegeSelect.value = "";
        
        // Fetch only available programs
        fetch('signup.php?action=get_available_programs')
            .then(res => res.json())
            .then(data => {
                programSelect.innerHTML = '<option value="">Select Program</option>';
                data.forEach(p => {
                    programSelect.innerHTML += `<option value="${p.program_id}">${p.program_code} - ${p.program_name}</option>`;
                });
            });

    } else if (roleName.includes('faculty') || roleName.includes('focal')) {
        programGroup.style.display = 'block';
        programSelect.required = true;
        collegeSelect.value = "";
        // Restore full list for faculty
        programSelect.innerHTML = originalProgramOptions;
    }
}
</script>
<?php include 'footer.php'; ?>
