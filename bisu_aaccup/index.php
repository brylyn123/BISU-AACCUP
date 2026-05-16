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
                    <span>BISU Candijay Repository Workflow</span>
                </div>
                <h1 class="hero-title">Build And Review Accreditation Repositories In One Place</h1>
                <p class="hero-subtitle">Create repository workspaces, assign focal persons and accreditors, organize folders, and review accreditation files in a secure shared workflow.</p>
                
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
                    <h3>Repository Workspaces</h3>
                    <p>Create flexible repositories with folders, areas, and organized document sections for each accreditation effort.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-search"></i></div>
                    <h3>Easy Retrieval</h3>
                    <p>Open assigned repositories quickly and navigate files the same way teams work inside shared drives.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-comments"></i></div>
                    <h3>Review Comments</h3>
                    <p>Let admins and accreditors leave comments on uploaded files so focal persons can complete missing requirements.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-chart-pie"></i></div>
                    <h3>Access Control</h3>
                    <p>Grant and remove accreditor access per repository to keep each accreditation workspace organized and secure.</p>
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
