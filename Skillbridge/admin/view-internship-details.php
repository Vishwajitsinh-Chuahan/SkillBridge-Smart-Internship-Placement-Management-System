<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

requireAdmin();

$page_title = "Internship Details";
$admin_name = $_SESSION['full_name'];

// Get internship ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: internship-approvals.php');
    exit();
}

$internship_id = (int)$_GET['id'];

// Fetch complete internship details
$query = "
    SELECT 
        i.*,
        c.name as company_name,
        c.industry,
        c.company_size,
        c.website,
        c.address,
        c.trust_level,
        c.approved_posts_count,
        u.full_name as contact_person,
        u.email as contact_email,
        u.phone as contact_phone,
        COUNT(a.id) as application_count
    FROM internships i
    JOIN companies c ON i.company_id = c.user_id
    JOIN users u ON i.company_id = u.id
    LEFT JOIN applications a ON i.id = a.internship_id
    WHERE i.id = ?
    GROUP BY i.id
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $internship_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Internship not found.';
    header('Location: internship-approvals.php');
    exit();
}

$internship = $result->fetch_assoc();

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
    <title><?php echo $page_title; ?> - SkillBridge Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            color: #2c3e50;
        }

        /* Sidebar */
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
        }

        .logo {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: white;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .nav-section-title {
            padding: 1rem 1.25rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #95a5a6;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.25rem;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
            border-left-color: #3498db;
        }

        .nav-item i {
            width: 20px;
            margin-right: 0.75rem;
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
        }

        .page-title h1 {
            font-size: 1.75rem;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .page-title p {
            color: #7f8c8d;
            font-size: 0.95rem;
        }

        .btn-back {
            background: #6c757d;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-back:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .content {
            padding: 2rem;
            max-width: 1400px;
        }

        /* Info Grid */
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

        .card-header h3 {
            font-size: 1.15rem;
            color: #2c3e50;
            font-weight: 600;
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

        .trust-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .trust-badge.new { background: #e3f2fd; color: #1976d2; }
        .trust-badge.verified { background: #e8f5e9; color: #388e3c; }
        .trust-badge.trusted { background: #fff3e0; color: #f57c00; }

        /* Full Width Card */
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
            white-space: pre-wrap;
        }

        .rejection-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 1.5rem;
            border-radius: 8px;
            color: #721c24;
            margin-top: 1rem;
        }

        .rejection-box h4 {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <span>SkillBridge</span>
        </div>

        <div class="nav-section-title">DASHBOARD</div>
        <a href="dashboard.php" class="nav-item">
            <i class="fas fa-th-large"></i>
            <span>Dashboard Overview</span>
        </a>

        <div class="nav-section-title">INTERNSHIPS</div>
        <a href="internship-approvals.php" class="nav-item active">
            <i class="fas fa-briefcase"></i>
            <span>Internship Approvals</span>
        </a>

        <div class="nav-section-title">SETTINGS</div>
        <a href="../auth/logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Internship Details</h1>
                <p>Complete information about the internship posting</p>
            </div>
            <a href="internship-approvals.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Approvals
            </a>
        </div>

        <div class="content">
            <!-- Basic Information & Company Info -->
            <div class="info-grid">
                <!-- Internship Information -->
                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon blue">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <h3>Internship Information</h3>
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
                        <div class="info-label">Location</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['location']); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Work Mode</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['location_type']); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Positions</div>
                        <div class="info-value"><?php echo $internship['positions_available']; ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Duration</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['duration'] ?: 'Not specified'); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Stipend</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['stipend'] ?: 'Not specified'); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Deadline</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($internship['application_deadline'])); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge <?php echo getStatusColor($internship['status']); ?>">
                                <?php echo ucfirst($internship['status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Applications</div>
                        <div class="info-value">
                            <span style="background: #e3f2fd; color: #1976d2; padding: 0.4rem 0.8rem; border-radius: 12px; font-weight: 600;">
                                <?php echo $internship['application_count']; ?>
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
                                <a href="<?php echo htmlspecialchars($internship['website']); ?>" target="_blank" style="color: #3498db;">
                                    <?php echo htmlspecialchars($internship['website']); ?>
                                </a>
                            <?php else: ?>
                                Not provided
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Trust Level</div>
                        <div class="info-value">
                            <span class="trust-badge <?php echo $internship['trust_level']; ?>">
                                <?php echo ucfirst($internship['trust_level']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Approved Posts</div>
                        <div class="info-value">
                            <strong style="color: #10b981;"><?php echo $internship['approved_posts_count'] ?? 0; ?></strong>
                            <span style="color: #7f8c8d; font-size: 0.85rem;"> / 5 for Verified</span>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Contact Person</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['contact_person']); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Email</div>
                        <div class="info-value">
                            <a href="mailto:<?php echo htmlspecialchars($internship['contact_email']); ?>" style="color: #3498db;">
                                <?php echo htmlspecialchars($internship['contact_email']); ?>
                            </a>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?php echo htmlspecialchars($internship['contact_phone'] ?: 'Not provided'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="full-width-card">
                <div class="card-header">
                    <div class="card-icon blue">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Description</h3>
                </div>
                <div class="section-content">
                    <?php echo nl2br(htmlspecialchars($internship['description'])); ?>
                </div>
            </div>

            <!-- Requirements -->
            <div class="full-width-card">
                <div class="card-header">
                    <div class="card-icon orange">
                        <i class="fas fa-list-check"></i>
                    </div>
                    <h3>Requirements & Qualifications</h3>
                </div>
                <div class="section-content">
                    <?php echo nl2br(htmlspecialchars($internship['requirements'])); ?>
                </div>
            </div>

            <!-- Required Skills -->
            <?php if (!empty($internship['skills_required'])): ?>
            <div class="full-width-card">
                <div class="card-header">
                    <div class="card-icon blue">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h3>Required Skills</h3>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                    <?php 
                    $skills = explode(',', $internship['skills_required']);
                    foreach ($skills as $skill): 
                        $skill = trim($skill);
                        if (!empty($skill)):
                    ?>
                        <span style="background: linear-gradient(135deg, #667eea15, #764ba215); color: #667eea; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; border: 2px solid #667eea30;">
                            <?php echo htmlspecialchars($skill); ?>
                        </span>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Rejection Reason (if rejected) -->
            <?php if ($internship['status'] === 'rejected' && !empty($internship['rejection_reason'])): ?>
            <div class="full-width-card">
                <div class="rejection-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> Rejection Reason</h4>
                    <p><?php echo nl2br(htmlspecialchars($internship['rejection_reason'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
