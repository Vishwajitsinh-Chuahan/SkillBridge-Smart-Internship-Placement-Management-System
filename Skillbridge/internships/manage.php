<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Company') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Company User';

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $internship_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM internships WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $internship_id, $user_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Internship deleted successfully!';
    }
    header('Location: manage.php');
    exit();
}

// Fetch all internships posted by this company
$stmt = $conn->prepare("
    SELECT i.*, COUNT(a.id) as application_count
    FROM internships i
    LEFT JOIN applications a ON i.id = a.internship_id
    WHERE i.company_id = ?
    GROUP BY i.id
    ORDER BY i.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$internships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$total_internships = count($internships);
$pending_count = count(array_filter($internships, fn($i) => $i['status'] === 'pending'));
$approved_count = count(array_filter($internships, fn($i) => $i['status'] === 'approved'));
$active_count = count(array_filter($internships, fn($i) => $i['status'] === 'active'));

$user_initials = strtoupper(substr($full_name, 0, 2));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Internships - SkillBridge</title>
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
        }

        /* Sidebar - Matching company.php */
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-title p {
            opacity: 0.9;
        }

        .btn-primary {
            background: white;
            color: #667eea;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255,255,255,0.3);
        }

        .content {
            padding: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon.orange { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .stat-icon.green { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .stat-icon.purple { background: linear-gradient(135deg, #a8edea, #fed6e3); }

        .stat-info h3 {
            font-size: 2rem;
            margin-bottom: 0.25rem;
            color: #2c3e50;
        }

        .stat-info p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .internships-table {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .table-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            color: #2c3e50;
            font-size: 1.25rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #ecf0f1;
            color: #2c3e50;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.approved { background: #d4edda; color: #155724; }
        .status-badge.active { background: #d1ecf1; color: #0c5460; }
        .status-badge.rejected { background: #f8d7da; color: #721c24; }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-sm.btn-view { background: #667eea; }
        .btn-sm.btn-edit { background: #3498db; }
        .btn-sm.btn-danger { background: #e74c3c; }

        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
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
            <a href="../dashboard/company.php" class="nav-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard Overview</span>
            </a>
        </div>

        <div class="nav-section">
            <p class="nav-section-title">INTERNSHIPS</p>
            <a href="create.php" class="nav-item">
                <i class="fas fa-plus-circle"></i>
                <span>Post New Internship</span>
            </a>
            <a href="manage.php" class="nav-item active">
                <i class="fas fa-list"></i>
                <span>Manage Internships</span>
            </a>
        </div>

        <div class="nav-section">
            <p class="nav-section-title">SETTINGS</p>
            <a href="../dashboard/profile.php" class="nav-item">
                <i class="fas fa-building"></i>
                <span>Company Profile</span>
            </a>
            <a href="../auth/logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>

        <div class="user-profile">
            <div class="avatar"><?php echo $user_initials; ?></div>
            <div class="user-info">
                <p class="user-name"><?php echo htmlspecialchars($full_name); ?></p>
                <p class="user-role">Company</p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Manage Internships</h1>
                <p>View and manage all your posted internships</p>
            </div>
            <a href="create.php" class="btn-primary">
                <i class="fas fa-plus"></i> Post New Internship
            </a>
        </div>

        <div class="content">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success']) && $_GET['success'] === 'posted'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Internship posted successfully! It will be visible after admin approval.
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_internships; ?></h3>
                        <p>Total Posted</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pending_count; ?></h3>
                        <p>Pending Approval</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $active_count; ?></h3>
                        <p>Active</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo array_sum(array_column($internships, 'application_count')); ?></h3>
                        <p>Total Applications</p>
                    </div>
                </div>
            </div>

            <!-- Internships Table -->
            <div class="internships-table">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> All Internships</h2>
                </div>

                <?php if (empty($internships)): ?>
                    <div class="empty-state">
                        <i class="fas fa-briefcase"></i>
                        <h3>No Internships Posted Yet</h3>
                        <p>Start posting internships to attract talented students!</p>
                        <a href="create.php" class="btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Post Your First Internship
                        </a>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Deadline</th>
                                <th>Applications</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($internships as $internship): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($internship['title']); ?></strong>
                                        <br>
                                        <small style="color: #7f8c8d;">Posted on <?php echo date('M d, Y', strtotime($internship['created_at'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($internship['internship_type']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($internship['location']); ?>
                                        <br>
                                        <small style="color: #7f8c8d;"><?php echo htmlspecialchars($internship['location_type']); ?></small>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($internship['application_deadline'])); ?></td>
                                    <td>
                                        <span style="background: #e3f2fd; color: #1976d2; padding: 0.4rem 0.8rem; border-radius: 12px; font-weight: 600;">
                                            <?php echo $internship['application_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $internship['status']; ?>">
                                            <?php echo ucfirst($internship['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view.php?id=<?php echo $internship['id']; ?>" class="btn-sm btn-view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="edit.php?id=<?php echo $internship['id']; ?>" class="btn-sm btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="?delete=<?php echo $internship['id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this internship?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
