<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/email_functions.php';
requireAdmin();

$page_title = 'Company Details';
$admin_name = $_SESSION['full_name'];

// Get company ID from URL
$company_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$company_id) {
    header("Location: company-approvals.php");
    exit();
}

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $reason = trim($_POST['reason'] ?? '');
        
        if ($action === 'approve') {
            // Update companies table
            $stmt = $conn->prepare("UPDATE companies SET status = 'approved', updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $company_id);
            
            if ($stmt->execute()) {
                // Also update user status to active
                $user_stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $user_stmt->bind_param("i", $company_id);
                $user_stmt->execute();
                
                // ✅ SEND APPROVAL EMAIL
                $emailResult = sendCompanyApprovalEmail($company_id, 'approved');
                
                if ($emailResult) {
                    $success_message = "Company has been approved successfully and notification email sent!";
                } else {
                    $success_message = "Company has been approved successfully, but email notification failed.";
                }
                
                // Log admin action
                error_log("Admin {$_SESSION['full_name']} approved company ID: $company_id");
            }
            
        } elseif ($action === 'reject') {
            // Update companies table with rejection
            $stmt = $conn->prepare("UPDATE companies SET status = 'rejected', updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $company_id);
            
            if ($stmt->execute()) {
                // Also update user status to rejected
                $user_stmt = $conn->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
                $user_stmt->bind_param("i", $company_id);
                $user_stmt->execute();
                
                // ✅ SEND REJECTION EMAIL WITH REASON
                $emailResult = sendCompanyApprovalEmail($company_id, 'rejected', $reason);
                
                if ($emailResult) {
                    $success_message = "Company has been rejected and notification email sent.";
                } else {
                    $success_message = "Company has been rejected, but email notification failed.";
                }
                
                // Log admin action
                error_log("Admin {$_SESSION['full_name']} rejected company ID: $company_id. Reason: $reason");
            }
        }
    }
}

// Fetch company details
$company_query = "
    SELECT 
        u.id, u.full_name, u.email, u.phone, u.created_at, u.status as user_status,
        c.name as company_name, c.industry, c.company_size, c.website, c.address, 
        c.description, c.logo_path, c.status as company_status, c.updated_at,
        c.trust_level, c.approved_posts_count, c.flagged_posts_count, c.last_reviewed_at
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN companies c ON u.id = c.user_id
    WHERE u.id = ? AND r.role_name = 'Company'
";

$stmt = $conn->prepare($company_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: company-approvals.php");
    exit();
}

$company = $result->fetch_assoc();

// ✅ UPDATED EMAIL FUNCTION
function sendCompanyApprovalEmail($company_id, $status, $reason = '') {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT u.full_name, u.email, c.name as company_name 
        FROM users u 
        LEFT JOIN companies c ON u.id = c.user_id 
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
    
    if ($company) {
        // Call the email template function
        return sendCompanyStatusEmail($company['full_name'], $company['email'], $company['company_name'], $status, $reason);
    }
    return false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - SkillBridge</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            color: #334155;
        }

        /* Sidebar - Same as previous pages */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 240px;
            height: 100vh;
            background-color: #334155;
            color: #cbd5e1;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(203, 213, 225, 0.1);
        }

        .sidebar-header .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #ffffff;
            text-decoration: none;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .sidebar-header .logo i {
            color: #3b82f6;
            font-size: 1.5rem;
        }

        /* Navigation */
        .nav-section {
            padding: 0.5rem 0;
        }

        .nav-section-title {
            padding: 0.75rem 1rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #cbd5e1;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background-color: rgba(59, 130, 246, 0.1);
            color: #ffffff;
            border-left-color: #3b82f6;
        }

        .nav-link.active {
            background-color: rgba(59, 130, 246, 0.15);
            color: #ffffff;
            border-left-color: #3b82f6;
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 0.9rem;
            color: #94a3b8;
        }

        .nav-link:hover i,
        .nav-link.active i {
            color: #3b82f6;
        }

        /* User Profile in Sidebar */
        .user-profile {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem;
            background-color: rgba(0, 0, 0, 0.1);
            border-top: 1px solid rgba(203, 213, 225, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-details h4 {
            color: #ffffff;
            font-size: 0.9rem;
            margin-bottom: 0.125rem;
        }

        .user-details p {
            color: #94a3b8;
            font-size: 0.8rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 240px;
            min-height: 100vh;
            background-color: #f8fafc;
        }

        /* Header */
        .header {
            background: #ffffff;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title h1 {
            color: #1e293b;
            font-size: 1.875rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .header-title p {
            color: #64748b;
            font-size: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .back-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #64748b;
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: #475569;
            color: white;
        }

        /* Success Message */
        .success-message {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 1px solid #34d399;
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Company Details Section */
        .company-details-section {
            padding: 2rem;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .detail-card {
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        .card-icon.company {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .card-icon.contact {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .card-icon.business {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .card-icon.status {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .card-title {
            color: #1e293b;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .detail-item {
            margin-bottom: 1.5rem;
        }

        .detail-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            color: #1e293b;
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.5;
        }

        .detail-value.empty {
            color: #94a3b8;
            font-style: italic;
        }

        /* Company Logo */
        .company-logo {
            width: 80px;
            height: 80px;
            border-radius: 0.5rem;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }

        .logo-placeholder {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 2rem;
            border: 2px solid #e2e8f0;
        }

        /* Status Badge */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Full Width Cards */
        .full-width-card {
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }

        /* Action Buttons */
        .action-section {
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn {
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-approve {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-approve:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-1px);
        }

        .btn-reject {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-reject:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                padding: 1rem;
            }
            
            .company-details-section {
                padding: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }

        .info-row {
            display: grid;
            grid-template-columns: 180px 1fr;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 0.85rem;
            color: #7f8c8d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 0.95rem;
            color: #2c3e50;
            font-weight: 500;
        }

        .trust-level-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .trust-level-badge.new {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }

        .trust-level-badge.verified {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .trust-level-badge.trusted {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .trust-level-badge.premium {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }

    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="#" class="logo">
                <i class="fas fa-graduation-cap"></i>
                SkillBridge
            </a>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">DASHBOARD</div>
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard Overview
                </a>
                <a href="company-approvals.php" class="nav-link active">
                    <i class="fas fa-building"></i>
                    Company Approvals
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">MANAGEMENT</div>
                <a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    User Management
                </a>
                <a href="internships.php" class="nav-link">
                    <i class="fas fa-briefcase"></i>
                    Internship Posts
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">SETTINGS</div>
                <a href="analytics.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    Analytics
                </a>
                <a href="../auth/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </nav>
        
        <!-- User Profile -->
        <div class="user-profile">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($admin_name, 0, 2)); ?>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($admin_name); ?></h4>
                    <p>Administrator</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-title">
                <h1>Company Details</h1>
                <p>Review company information and make approval decisions</p>
            </div>
            <div class="header-actions">
                <a href="company-approvals.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Approvals
                </a>
            </div>
        </header>

        <!-- Success Message -->
        <?php if (isset($success_message)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <!-- Company Details -->
        <div class="company-details-section">
            <div class="details-grid">
                <!-- Company Information -->
                <div class="detail-card">
                    <div class="card-header">
                        <div class="card-icon company">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 class="card-title">Company Information</h3>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Company Logo</div>
                        <div class="detail-value">
                            <?php if ($company['logo_path'] && file_exists("../uploads/logos/" . $company['logo_path'])): ?>
                                <img src="../uploads/logos/<?php echo htmlspecialchars($company['logo_path']); ?>" alt="Company Logo" class="company-logo">
                            <?php else: ?>
                                <div class="logo-placeholder">
                                    <i class="fas fa-building"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Company Name</div>
                        <div class="detail-value <?php echo empty($company['company_name']) ? 'empty' : ''; ?>">
                            <?php echo htmlspecialchars($company['company_name'] ?: 'Not provided'); ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Industry</div>
                        <div class="detail-value <?php echo empty($company['industry']) ? 'empty' : ''; ?>">
                            <?php echo htmlspecialchars($company['industry'] ?: 'Not specified'); ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Company Size</div>
                        <div class="detail-value <?php echo empty($company['company_size']) ? 'empty' : ''; ?>">
                            <?php echo htmlspecialchars($company['company_size'] ?: 'Not specified'); ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Website</div>
                        <div class="detail-value <?php echo empty($company['website']) ? 'empty' : ''; ?>">
                            <?php if ($company['website']): ?>
                                <a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank" style="color: #3b82f6; text-decoration: none;">
                                    <?php echo htmlspecialchars($company['website']); ?>
                                    <i class="fas fa-external-link-alt" style="margin-left: 0.5rem; font-size: 0.8rem;"></i>
                                </a>
                            <?php else: ?>
                                Not provided
                            <?php endif; ?>
                        </div>
                    </div>
                                                <!-- ✅ ADDED: Trust Level -->
                            <div class="detail-item">
                                <span class="detail-label">TRUST LEVEL</span>
                                <div class="detail-value">
                                    <?php 
                                    $trustLevel = $company['trust_level'] ?? 'new';
                                    $trustIcons = [
                                        'new' => '🆕',
                                        'verified' => '✅',
                                        'trusted' => '⭐',
                                        'premium' => '👑'
                                    ];
                                    ?>
                                    <span class="trust-level-badge <?php echo $trustLevel; ?>">
                                        <span><?php echo $trustIcons[$trustLevel]; ?></span>
                                        <span><?php echo ucfirst($trustLevel); ?></span>
                                    </span>
                                </div>
                            </div>

                            <!-- ✅ ADDED: Approved Posts Count -->
                            <div class="detail-item">
                                <span class="detail-label">APPROVED POSTS</span>
                                <div class="detail-value">
                                    <strong style="color: #10b981; font-size: 1.1rem;">
                                        <?php echo $company['approved_posts_count'] ?? 0; ?>
                                    </strong>
                                    <span style="color: #7f8c8d; font-size: 0.85rem; margin-left: 0.5rem;">
                                        / 5 required for Verified
                                    </span>
                                </div>
                            </div>

                </div>
                

                <!-- Contact Information -->
                <div class="detail-card">
                    <div class="card-header">
                        <div class="card-icon contact">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3 class="card-title">Contact Information</h3>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Contact Person</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($company['full_name']); ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Email Address</div>
                        <div class="detail-value">
                            <a href="mailto:<?php echo htmlspecialchars($company['email']); ?>" style="color: #3b82f6; text-decoration: none;">
                                <?php echo htmlspecialchars($company['email']); ?>
                            </a>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Phone Number</div>
                        <div class="detail-value <?php echo empty($company['phone']) ? 'empty' : ''; ?>">
                            <?php if ($company['phone']): ?>
                                <a href="tel:<?php echo htmlspecialchars($company['phone']); ?>" style="color: #3b82f6; text-decoration: none;">
                                    <?php echo htmlspecialchars($company['phone']); ?>
                                </a>
                            <?php else: ?>
                                Not provided
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Registration Date</div>
                        <div class="detail-value">
                            <?php echo date('F j, Y \a\t g:i A', strtotime($company['created_at'])); ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Current Status</div>
                        <div class="detail-value">
                            <span class="status-badge status-<?php echo $company['user_status'] === 'active' ? 'approved' : ($company['user_status'] === 'rejected' ? 'rejected' : 'pending'); ?>">
                                <i class="fas fa-<?php echo $company['user_status'] === 'active' ? 'check' : ($company['user_status'] === 'rejected' ? 'times' : 'clock'); ?>"></i>
                                <?php echo ucfirst($company['user_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Company Address -->
            <?php if ($company['address']): ?>
            <div class="full-width-card">
                <div class="card-header">
                    <div class="card-icon business">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3 class="card-title">Company Address</h3>
                </div>
                <div class="detail-value">
                    <?php echo nl2br(htmlspecialchars($company['address'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Company Description -->
            <?php if ($company['description']): ?>
            <div class="full-width-card">
                <div class="card-header">
                    <div class="card-icon business">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <h3 class="card-title">Company Description</h3>
                </div>
                <div class="detail-value">
                    <?php echo nl2br(htmlspecialchars($company['description'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <?php if ($company['user_status'] === 'pending'): ?>
            <div class="action-section">
                <div class="card-header">
                    <div class="card-icon status">
                        <i class="fas fa-gavel"></i>
                    </div>
                    <h3 class="card-title">Approval Decision</h3>
                </div>
                
                <div class="action-buttons">
                    <button onclick="approveCompany()" class="btn btn-approve">
                        <i class="fas fa-check"></i>
                        Approve Company
                    </button>
                    <button onclick="rejectCompany()" class="btn btn-reject">
                        <i class="fas fa-times"></i>
                        Reject Company
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hidden Forms for Actions -->
    <form id="approveForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="approve">
    </form>

    <form id="rejectForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="reason" id="reject_reason">
    </form>

    <script>
        function approveCompany() {
            if (confirm('Are you sure you want to approve this company?\n\nThis will:\n• Set company status to "Approved"\n• Allow them to post internships\n• Send approval confirmation email')) {
                document.getElementById('approveForm').submit();
            }
        }

        function rejectCompany() {
            const reason = prompt('Please provide a detailed reason for rejection:\n(This will be included in the rejection email)');
            if (reason && reason.trim()) {
                if (confirm('Are you sure you want to reject this company?\n\nReason: ' + reason.trim() + '\n\nThis will send a rejection email with the reason provided.')) {
                    document.getElementById('reject_reason').value = reason.trim();
                    document.getElementById('rejectForm').submit();
                }
            } else if (reason === '') {
                alert('Please provide a reason for rejection. This is required for the rejection email.');
                rejectCompany(); // Call function again to re-prompt
            }
        }
    </script>

</body>
</html>
