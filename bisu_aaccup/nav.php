<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>AACCUP Repository - BISU</title>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-wrapper">
                <a href="index.php" class="nav-logo">
                    <i class="fas fa-book-open"></i>
                    <span>BISU Candijay AACCUP System</span>
                </a>
                <button class="nav-toggle" id="navToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
            <ul class="nav-menu" id="navMenu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">Home</a>
                </li>
                <li class="nav-item">
                    <a href="structure.php" class="nav-link">Structure</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a href="role_home.php" class="nav-link">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link nav-logout">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a href="login.php" class="nav-link">Login</a>
                    </li>
                    <li class="nav-item">
                        <a href="signup.php" class="nav-link">Signup</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
