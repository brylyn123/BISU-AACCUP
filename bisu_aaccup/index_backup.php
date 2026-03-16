<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
include 'config/db.php';
include 'nav.php'; 
?>

<div class="landing-wrapper">
    <div class="hero-section">
        <div class="hero-content">
            <h1>AACCUP Document Repository</h1>
            <p>Streamlining Accreditation for BISU Candijay Departments</p>
            <div class="hero-buttons">
                <a href="login.php" class="btn-primary">Get Started</a>
                <a href="#about" class="btn-secondary">Learn More</a>
            </div>
        </div>
    </div>
</div>

<style>
    .landing-wrapper {
        height: 80vh;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    .hero-content h1 {
        font-size: 3.5rem;
        color: white;
        margin-bottom: 1rem;
    }
    .hero-content p { color: rgba(255,255,255,0.8); font-size: 1.2rem; margin-bottom: 2rem;}
    .btn-primary {
        padding: 15px 30px;
        background: white;
        color: #667eea;
        text-decoration: none;
        border-radius: 8px;
        font-weight: bold;
        transition: 0.3s;
    }
    .btn-primary:hover { background: #f0f0f0; }
</style>

<?php include 'footer.php'; ?>
