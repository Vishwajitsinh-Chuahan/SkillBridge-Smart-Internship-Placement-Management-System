<?php
require_once 'config/config.php';
$page_title = 'Home';

// Check for logout message
$show_logout_message = false;
if (isset($_SESSION['logout_success'])) {
    $show_logout_message = true;
    unset($_SESSION['logout_success']);
}
?>
<?php include 'includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
     <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for icons -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
 
    <?php if(isset($additional_css)): ?>
        <link rel="stylesheet" href="<?php echo $additional_css; ?>">
    <?php endif; ?>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Bridge Your Skills to Career Success</h1>
                <p>Your ultimate platform for internship and placement management. Connect with top companies, find meaningful internships, and launch your career with SkillBridge.</p>
                <div class="hero-buttons">
                    <a href="<?php echo BASE_URL; ?>/auth/register.php" class="btn btn-primary btn-lg">Join SkillBridge</a>
                    <a href="<?php echo BASE_URL; ?>/internships/browse.php" class="btn btn-secondary btn-lg">Find Internships</a>
                </div>
            </div>
        </div>
        <!-- Hero wave decoration -->
        <div class="hero-wave">
            <svg viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" fill="rgba(255,255,255,0.1)"></path>
            </svg>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Why Choose SkillBridge?</h2>
                <p>Powerful features to connect students with career opportunities</p>
            </div>
            
            <div class="row">
                <!-- Feature 1 -->
                <div class="col-4 mb-4">
                    <div class="feature-card card">
                        <div class="card-body text-center">
                            <div class="feature-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h4>Smart Internship Search</h4>
                            <p>Find internships that match your skills and career goals with our intelligent search and recommendation system.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 2 -->
                <div class="col-4 mb-4">
                    <div class="feature-card card">
                        <div class="card-body text-center">
                            <div class="feature-icon">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <h4>Company Connections</h4>
                            <p>Connect directly with leading companies and startups offering meaningful internship opportunities.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 3 -->
                <div class="col-4 mb-4">
                    <div class="feature-card card">
                        <div class="card-body text-center">
                            <div class="feature-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h4>Easy Application</h4>
                            <p>Apply to multiple internships with a single click using your comprehensive student profile and resume.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 4 -->
                <div class="col-4 mb-4">
                    <div class="feature-card card">
                        <div class="card-body text-center">
                            <div class="feature-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h4>Skill Assessment</h4>
                            <p>Evaluate and showcase your skills with comprehensive assessments and certification tracking.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 5 -->
                <div class="col-4 mb-4">
                    <div class="feature-card card">
                        <div class="card-body text-center">
                            <div class="feature-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h4>Interview Scheduling</h4>
                            <p>Seamlessly schedule and manage interviews with integrated calendar and video conferencing support.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 6 -->
                <div class="col-4 mb-4">
                    <div class="feature-card card">
                        <div class="card-body text-center">
                            <div class="feature-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <h4>Placement Tracking</h4>
                            <p>Track your placement journey with detailed analytics and success metrics to boost your career growth.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row text-center">
                <div class="col-3">
                    <div class="stat-item">
                        <h3>2,500+</h3>
                        <p>Active Students</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-item">
                        <h3>800+</h3>
                        <p>Internship Opportunities</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-item">
                        <h3>150+</h3>
                        <p>Partner Companies</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-item">
                        <h3>92%</h3>
                        <p>Placement Success Rate</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
<section class="benefits-section py-5">
    <div class="container">
        <div class="row">
            <!-- For Students -->
            <div class="col-6">
                <div class="benefit-card">
                    <div class="benefit-header">
                        <i class="fas fa-graduation-cap"></i>
                        <h3>For Students</h3>
                    </div>
                    <ul class="benefit-list">
                        <li><i class="fas fa-check"></i> Access to premium internship opportunities</li>
                        <li><i class="fas fa-check"></i> Personalized career guidance and mentorship</li>
                        <li><i class="fas fa-check"></i> Skill development and certification programs</li>
                        <li><i class="fas fa-check"></i> Direct communication with hiring managers</li>
                        <li><i class="fas fa-check"></i> Portfolio building and resume optimization</li>
                    </ul>
                    <a href="<?php echo BASE_URL; ?>/auth/register.php?role=student" class="btn btn-primary">Join as Student</a>
                </div>
            </div>
            
            <!-- For Companies -->
            <div class="col-6">
                <div class="benefit-card">
                    <div class="benefit-header">
                        <i class="fas fa-building"></i>
                        <h3>For Companies</h3>
                    </div>
                    <ul class="benefit-list">
                        <li><i class="fas fa-check"></i> Access to top-tier student talent pool</li>
                        <li><i class="fas fa-check"></i> Streamlined recruitment and selection process</li>
                        <li><i class="fas fa-check"></i> Customizable internship program management</li>
                        <li><i class="fas fa-check"></i> Performance tracking and analytics</li>
                        <li><i class="fas fa-check"></i> Brand exposure to university communities</li>
                    </ul>
                    <a href="<?php echo BASE_URL; ?>/companies/register.php" class="btn btn-secondary">Partner with Us</a>
                </div>
            </div>
        </div>
    </div>
</section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container text-center">
            <h2>Ready to Launch Your Career?</h2>
            <p>Join thousands of students and companies who trust SkillBridge for internships and placements</p>
            <div class="cta-buttons">
                <a href="<?php echo BASE_URL; ?>/auth/register.php" class="btn btn-primary btn-lg">Get Started Today</a>
                <a href="<?php echo BASE_URL; ?>/internships/browse.php" class="btn btn-outline-primary btn-lg">Explore Opportunities</a>
            </div>
        </div>
    </section>

    <?php if ($show_logout_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
            <i class="fas fa-check-circle"></i> You have been successfully logged out.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <script>
            setTimeout(function() {
                const alert = document.querySelector('.alert');
                if (alert) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 150);
                }
            }, 3000);
        </script>
    <?php endif; ?>

</body>
</html>

<?php include 'includes/footer.php'; ?>
