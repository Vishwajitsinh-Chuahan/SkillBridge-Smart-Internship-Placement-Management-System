<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
 
    <?php if(isset($additional_css)): ?>
        <link rel="stylesheet" href="<?php echo $additional_css; ?>">
    <?php endif; ?>
</head>
<body>

    <!-- Navigation Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <!-- Brand Logo -->
                <a href="<?php echo BASE_URL; ?>/index.php" class="navbar-brand">
                    <i class="fas fa-graduation-cap"></i>
                    SkillBridge
                </a>
                
                <!-- Navigation Menu -->
                <ul class="navbar-nav">
                    <li><a href="<?php echo BASE_URL; ?>/index.php" class="nav-link">Home</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/internships/browse.php" class="nav-link">Internships</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/companies/list.php" class="nav-link">Companies</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/about.php" class="nav-link">About</a></li>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <!-- Logged in user menu -->
                        <?php if($_SESSION['role'] === 'Student'): ?>
                            <li><a href="<?php echo BASE_URL; ?>/dashboard/student.php" class="nav-link">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a></li>
                            <li><a href="<?php echo BASE_URL; ?>/applications/my-applications.php" class="nav-link">
                                <i class="fas fa-file-alt"></i> My Applications
                            </a></li>
                        <?php elseif($_SESSION['role'] === 'Company'): ?>
                            <li><a href="<?php echo BASE_URL; ?>/dashboard/company.php" class="nav-link">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a></li>
                            <li><a href="<?php echo BASE_URL; ?>/internships/manage.php" class="nav-link">
                                <i class="fas fa-briefcase"></i> Manage Posts
                            </a></li>
                        <?php elseif($_SESSION['role'] === 'Admin'): ?>
                            <li><a href="<?php echo BASE_URL; ?>/admin/index.php" class="nav-link">
                                <i class="fas fa-cog"></i> Admin Panel
                            </a></li>
                        <?php endif; ?>
                        
                        <!-- User Dropdown -->
                        <li class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" id="userDropdown">
                                <i class="fas fa-user-circle"></i> 
                                <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a href="<?php echo BASE_URL; ?>/profile/edit.php" class="dropdown-item">
                                    <i class="fas fa-user-edit"></i> Edit Profile
                                </a></li>
                                <li><a href="<?php echo BASE_URL; ?>/profile/settings.php" class="dropdown-item">
                                    <i class="fas fa-cog"></i> Settings
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a href="<?php echo BASE_URL; ?>/auth/logout.php" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Guest user menu -->
                        <li><a href="<?php echo BASE_URL; ?>/auth/login.php" class="nav-link">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a></li>
                        <li class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle btn btn-primary" id="registerDropdown">
                                <i class="fas fa-user-plus"></i> Join SkillBridge
                            </a>
                            <ul class="dropdown-menu">
                                <li><a href="<?php echo BASE_URL; ?>/auth/register.php" class="dropdown-item">
                                    <i class="fas fa-graduation-cap"></i> Register as Student
                                </a></li>
                                <li><a href="<?php echo BASE_URL; ?>/companies/register.php" class="dropdown-item">
                                    <i class="fas fa-building"></i> Register as Company
                                </a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>

                <!-- Mobile Menu Toggle -->
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars"></i>
                </button>
            </nav>

            <!-- Mobile Menu -->
            <div class="mobile-menu" id="mobileMenu">
                <div class="mobile-menu-content">
                    <a href="<?php echo BASE_URL; ?>/index.php" class="mobile-nav-link">
                        <i class="fas fa-home"></i> Home
                    </a>
                    <a href="<?php echo BASE_URL; ?>/internships/browse.php" class="mobile-nav-link">
                        <i class="fas fa-briefcase"></i> Internships
                    </a>
                    <a href="<?php echo BASE_URL; ?>/companies/list.php" class="mobile-nav-link">
                        <i class="fas fa-building"></i> Companies
                    </a>
                    <a href="<?php echo BASE_URL; ?>/about.php" class="mobile-nav-link">
                        <i class="fas fa-info-circle"></i> About
                    </a>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <hr class="mobile-menu-divider">
                        <?php if($_SESSION['role'] === 'Student'): ?>
                            <a href="<?php echo BASE_URL; ?>/dashboard/student.php" class="mobile-nav-link">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                            <a href="<?php echo BASE_URL; ?>/applications/my-applications.php" class="mobile-nav-link">
                                <i class="fas fa-file-alt"></i> My Applications
                            </a>
                        <?php elseif($_SESSION['role'] === 'Company'): ?>
                            <a href="<?php echo BASE_URL; ?>/dashboard/company.php" class="mobile-nav-link">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                            <a href="<?php echo BASE_URL; ?>/internships/manage.php" class="mobile-nav-link">
                                <i class="fas fa-briefcase"></i> Manage Posts
                            </a>
                        <?php elseif($_SESSION['role'] === 'Admin'): ?>
                            <a href="<?php echo BASE_URL; ?>/admin/index.php" class="mobile-nav-link">
                                <i class="fas fa-cog"></i> Admin Panel
                            </a>
                        <?php endif; ?>
                        <hr class="mobile-menu-divider">
                        <a href="<?php echo BASE_URL; ?>/profile/edit.php" class="mobile-nav-link">
                            <i class="fas fa-user-edit"></i> Edit Profile
                        </a>
                        <a href="<?php echo BASE_URL; ?>/profile/settings.php" class="mobile-nav-link">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="mobile-nav-link">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php else: ?>
                        <hr class="mobile-menu-divider">
                        <a href="<?php echo BASE_URL; ?>/auth/login.php" class="mobile-nav-link">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <a href="<?php echo BASE_URL; ?>/auth/register.php" class="mobile-nav-link">
                            <i class="fas fa-graduation-cap"></i> Register as Student
                        </a>
                        <a href="<?php echo BASE_URL; ?>/companies/register.php" class="mobile-nav-link">
                            <i class="fas fa-building"></i> Register as Company
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <script>
        // Mobile menu toggle
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            const toggleBtn = document.querySelector('.mobile-menu-toggle i');
            
            if (mobileMenu.classList.contains('active')) {
                mobileMenu.classList.remove('active');
                toggleBtn.classList.remove('fa-times');
                toggleBtn.classList.add('fa-bars');
            } else {
                mobileMenu.classList.add('active');
                toggleBtn.classList.remove('fa-bars');
                toggleBtn.classList.add('fa-times');
            }
        }

        // Dropdown toggle for desktop
        document.addEventListener('DOMContentLoaded', function() {
            const dropdowns = document.querySelectorAll('.dropdown-toggle');
            
            dropdowns.forEach(dropdown => {
                dropdown.addEventListener('click', function(e) {
                    e.preventDefault();
                    const dropdownMenu = this.nextElementSibling;
                    
                    // Close other dropdowns
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                        if (menu !== dropdownMenu) {
                            menu.classList.remove('show');
                        }
                    });
                    
                    // Toggle current dropdown
                    dropdownMenu.classList.toggle('show');
                });
            });

            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                        menu.classList.remove('show');
                    });
                }
            });
        });
    </script>

    <style>
        /* Additional CSS for dropdowns and mobile menu */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            cursor: pointer;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            min-width: 200px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 0.5rem 0;
            display: none;
            z-index: 1000;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            display: block;
            padding: 0.75rem 1rem;
            color: #374151;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: #f3f4f6;
            color: #2563eb;
            text-decoration: none;
        }

        .dropdown-divider {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 0.5rem 0;
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: #64748b;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.5rem;
        }

        .mobile-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 999;
        }

        .mobile-menu.active {
            display: block;
        }

        .mobile-menu-content {
            padding: 1rem;
        }

        .mobile-nav-link {
            display: block;
            padding: 0.75rem 1rem;
            color: #374151;
            text-decoration: none;
            transition: all 0.2s ease;
            border-radius: 6px;
            margin-bottom: 0.25rem;
        }

        .mobile-nav-link:hover {
            background: #f3f4f6;
            color: #2563eb;
            text-decoration: none;
        }

        .mobile-menu-divider {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 1rem 0;
        }

        @media (max-width: 768px) {
            .navbar-nav {
                display: none;
            }

            .mobile-menu-toggle {
                display: block;
            }
        }
    </style>
