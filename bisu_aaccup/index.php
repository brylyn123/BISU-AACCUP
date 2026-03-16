<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
include 'config/db.php';
include 'nav.php'; 
?>

<div class="landing-wrapper">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-graduation-cap"></i>
                    <span>BISU Candijay Accreditation System</span>
                </div>
                <h1 class="hero-title">Streamline Your Accreditation Journey</h1>
                <p class="hero-subtitle">A centralized, secure, and efficient repository for managing all accreditation documents at BISU Candijay Campus.</p>
                
                <div class="hero-buttons">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Get Started
                    </a>
                    <a href="#features" class="btn btn-secondary">
                        <i class="fas fa-arrow-down"></i>
                        Learn More
                    </a>
                </div>
            </div>
            <div class="hero-image">
                <div class="floating-card card-1">
                    <i class="fas fa-file-alt"></i>
                    <p>Smart Filing</p>
                </div>
                <div class="floating-card card-2">
                    <i class="fas fa-shield-alt"></i>
                    <p>Secure Data</p>
                </div>
                <div class="floating-card card-3">
                    <i class="fas fa-check-circle"></i>
                    <p>Audit Ready</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="section-header">
                <h2>Why Use This System?</h2>
                <p>Designed to make the accreditation process smoother for faculty and accreditors.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                    <h3>Centralized Repository</h3>
                    <p>Upload, organize, and access all accreditation documents in one secure location.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-search"></i></div>
                    <h3>Easy Retrieval</h3>
                    <p>Quickly find documents by area, program, or year with powerful search tools.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-comments"></i></div>
                    <h3>Instant Feedback</h3>
                    <p>Receive and respond to accreditor comments and suggestions in real-time.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-chart-pie"></i></div>
                    <h3>Progress Tracking</h3>
                    <p>Monitor compliance status across different areas and programs visually.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Get Started?</h2>
                <p>Join the faculty and staff in modernizing our accreditation workflow.</p>
                <a href="signup.php" class="btn btn-primary btn-large">Create Your Account</a>
            </div>
        </div>
    </section>
</div>

<?php include 'footer.php'; ?>