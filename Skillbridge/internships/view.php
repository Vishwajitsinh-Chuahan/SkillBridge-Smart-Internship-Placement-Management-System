<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';
$full_name = $_SESSION['full_name'] ?? 'User';

// Get internship ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage.php');
    exit();
}

$internship_id = (int)$_GET['id'];

// Fetch internship details with company information
$query = "
    SELECT i.*, 
           c.name as company_name,
           c.industry,
           c.company_size,
           c.address as company_address,
           c.website,
           c.logo_path,
           u.full_name as contact_person,
           u.email as contact_email,
           u.phone as contact_phone,
           COUNT(a.id) as application_count
    FROM internships i
    JOIN users u ON i.company_id = u.id
    LEFT JOIN companies c ON u.id = c.user_id
    LEFT JOIN applications a ON i.id = a.internship_id
    WHERE i.id = ?
";

// Add permission check based on role
if ($user_role === 'Company') {
    $query .= " AND i.company_id = ?";
}

$query .= " GROUP BY i.id";

$stmt = $conn->prepare($query);
if ($user_role === 'Company') {
    $stmt->bind_param("ii", $internship_id, $user_id);
} else {
    $stmt->bind_param("i", $internship_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Internship not found.';
    header('Location: manage.php');
    exit();
}

$internship = $result->fetch_assoc();

// Get user initials
$user_initials = strtoupper(substr($full_name, 0, 2));

// Get status badge color
function getStatusColor($status) {
    switch($status) {
        case 'pending': return 'warning';
        case 'approved': return 'success';
        case 'active': return 'info';
        case 'rejected': return 'danger';
        case 'closed': return 'secondary';
        default: return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Internship - SkillBridge</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            color: #2c3e50;
        }

        /* Sidebar - Matching design */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 240px;
            height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: #ecf0f1;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .logo {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo i {
            font-size: 1.5rem;
            color: #3498db;
        }

        .logo h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #ffffff;
        }

        .nav-section-title {
            padding: 1rem 1rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #95a5a6;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
            border-left-color: #3498db;
        }

        .nav-item.active {
            background: rgba(52, 152, 219, 0.15);
            color: #3498db;
            border-left-color: #3498db;
            font-weight: 600;
        }

        .nav-item i {
            width: 20px;
            margin-right: 0.75rem;
        }

        .user-profile {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem;
            background: rgba(0,0,0,0.2);
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
        }

        .user-name {
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .user-role {
            color: #95a5a6;
            font-size: 0.75rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 240px;
            min-height: 100vh;
        }

        .top-bar {
            background: white;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .page-title h1 {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .page-title p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .top-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* Content Area */
        .content {
            padding: 2rem;
            max-width: 1400px;
        }

        /* Card Layouts - Matching reference design */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .card-icon.blue { background: linear-gradient(135deg, #667eea, #764ba2); }
        .card-icon.green { background: linear-gradient(135deg, #11998e, #38ef7d); }
        .card-icon.orange { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .card-icon.purple { background: linear-gradient(135deg, #4facfe, #00f2fe); }

        .card-header h3 {
            font-size: 1.1rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .info-row {
            display: grid;
            grid-template-columns: 150px 1fr;
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

        .status-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.success { background: #d4edda; color: #155724; }
        .status-badge.warning { background: #fff3cd; color: #856404; }
        .status-badge.danger { background: #f8d7da; color: #721c24; }
        .status-badge.info { background: #d1ecf1; color: #0c5460; }
        .status-badge.secondary { background: #e2e3e5; color: #383d41; }

        /* Full Width Sections */
        .full-width-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }

        .section-content {
            color: #2c3e50;
            line-height: 1.8;
            font-size: 0.95rem;
        }

        .section-content p {
            margin-bottom: 1rem;
        }

        .section-content ul {
            list-style-position: inside;
            margin-left: 1rem;
        }

        .section-content li {
            margin-bottom: 0.5rem;
            line-height: 1.6;
        }

        /* Skills Display */
        .skills-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        .skill-badge {
            background: linear-gradient(135deg, #667eea15, #764ba215);
            color: #667eea;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 2px solid #667eea30;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert-danger { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }

        @media (max-width: 992px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <h2>SkillBridge</h2>
        </div>

        <div class="nav-section">
            <p class="nav-section-title">DASHBOARD</p>
            <a href="<?php echo $user_role === 'Company' ? '../dashboard/company.php' : '../dashboard/admin.php'; ?>" class="nav-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard Overview</span>
            </a>
        </div>

        <div class="nav-section">
            <p class="nav-section-title">INTERNSHIPS</p>
            <?php if ($user_role === 'Company'): ?>
                <a href="create.php" class="nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <span>Post New Internship</span>
                </a>
            <?php endif; ?>
            <a href="manage.php" class="nav-item active">
                <i class="fas fa-list"></i>
                <span>Manage Internships</span>
            </a>
        </div>

        <div class="nav-section">
            <p class="nav-section-title">SETTINGS</p>
            <a href="../auth/logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>

        <div class="user-profile">
            <div class="avatar"><?php echo $user_initials; ?></div>
            <div class="user-info">
                <p class="user-name"><?php echo htmlspecialchars($full_name); ?></p>
                <p class="user-role"><?php echo ucfirst($user_role); ?></p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>View Internship Details</h1>
                <p>Complete information about the internship posting</p>
            </div>
            <div class="top-actions">
                <a href="manage.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <?php if ($user_role === 'Company'): ?>
                    <a href="edit.php?id=<?php echo $internship_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Internship
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <!-- Basic Information & Status -->
            <div class="info-grid">
                <!-- Basic Information -->
                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon blue">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <h3>Basic Information</h3>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Title</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['title']); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Type</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['internship_type']); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Positions</div>
                        <div class="info-value"><?php echo $internship['positions_available']; ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Applications</div>
                        <div class="info-value">
                            <span style="background: #e3f2fd; color: #1976d2; padding: 0.4rem 0.8rem; border-radius: 12px; font-weight: 600;">
                                <?php echo $internship['application_count']; ?>
                            </span>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge <?php echo getStatusColor($internship['status']); ?>">
                                <?php echo ucfirst($internship['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Company Information -->
                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon green">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3>Company Information</h3>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Company Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['company_name']); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Industry</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['industry']); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Company Size</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['company_size']); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Website</div>
                        <div class="info-value">
                            <?php if ($internship['website']): ?>
                                <a href="<?php echo htmlspecialchars($internship['website']); ?>" target="_blank" style="color: #667eea;">
                                    <?php echo htmlspecialchars($internship['website']); ?>
                                </a>
                            <?php else: ?>
                                <span style="color: #95a5a6;">Not provided</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Contact Person</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['contact_person']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Location & Compensation -->
            <div class="info-grid">
                <!-- Location Details -->
                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon orange">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h3>Location Details</h3>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Location</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['location']); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Work Mode</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['location_type']); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['company_address'] ?: 'Not provided'); ?></div>
                    </div>
                </div>

                <!-- Compensation & Timeline -->
                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon purple">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3>Compensation & Timeline</h3>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Duration</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['duration'] ?: 'Not specified'); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Stipend/Salary</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['stipend'] ?: 'Not specified'); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Deadline</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($internship['application_deadline'])); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Posted On</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($internship['created_at'])); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Start Date</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($internship['start_date'])); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">End Date</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($internship['end_date'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Required Skills -->
            <?php if ($internship['skills_required']): ?>
            <div class="full-width-card">
                <div class="card-header">
                    <div class="card-icon blue">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h3>Required Skills</h3>
                </div>
                <div class="skills-container">
                    <?php 
                    $skills = explode(', ', $internship['skills_required']);
                    foreach ($skills as $skill):
                    ?>
                        <span class="skill-badge"><?php echo htmlspecialchars(trim($skill)); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Description -->
            <div class="full-width-card">
                <div class="card-header">
                    <div class="card-icon green">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Internship Description</h3>
                </div>
                <div class="section-content">
                    <?php echo nl2br(htmlspecialchars($internship['description'])); ?>
                </div>
            </div>

            <!-- Requirements -->
            <div class="full-width-card">
                <div class="card-header">
                    <div class="card-icon orange">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h3>Requirements & Qualifications</h3>
                </div>
                <div class="section-content">
                    <?php echo nl2br(htmlspecialchars($internship['requirements'])); ?>
                </div>
            </div>

            <?php if ($internship['status'] === 'rejected' && !empty($internship['rejection_reason'])): ?>
            <div class="full-width-card" style="border: 2px solid #dc3545;">
                <div class="card-header" style="background: linear-gradient(135deg, #f8d7da, #f5c6cb); border-radius: 8px; padding: 1.5rem;">
                    <div class="card-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 style="color: #721c24;">Rejection Reason</h3>
                </div>
                <div style="background: #f8d7da; padding: 1.5rem; border-radius: 8px; margin-top: 1rem; border-left: 4px solid #dc3545;">
                    <p style="color: #721c24; font-weight: 500; margin-bottom: 0.5rem;">
                        <i class="fas fa-info-circle"></i> Your internship post was rejected by the admin for the following reason:
                    </p>
                    <div class="section-content" style="color: #721c24; background: white; padding: 1rem; border-radius: 6px; margin-top: 1rem;">
                        <?php echo nl2br(htmlspecialchars($internship['rejection_reason'])); ?>
                    </div>
                    <p style="color: #721c24; font-size: 0.9rem; margin-top: 1rem; font-style: italic;">
                        <i class="fas fa-lightbulb"></i> <strong>Next Steps:</strong> Please review the feedback and make necessary changes. You can create a new internship post after addressing these concerns.
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>