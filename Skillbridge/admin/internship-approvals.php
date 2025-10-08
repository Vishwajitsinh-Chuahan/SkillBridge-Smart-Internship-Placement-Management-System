<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/email_functions.php'; // If you have email system

requireAdmin();

$page_title = "Internship Approvals";
$admin_name = $_SESSION['full_name'];

$success_message = '';
$error_message = '';

// ✅ UPDATED: Handle Approve Action with start_date logic
//trust level logic
if (isset($_POST['approve_internship'])) {
    $internship_id = (int)$_POST['internship_id'];
    
    // Get internship and company details including start_date
    $stmt = $conn->prepare("
        SELECT i.*, c.trust_level, c.approved_posts_count, u.email, u.full_name, c.name as company_name
        FROM internships i
        JOIN companies c ON i.company_id = c.user_id
        JOIN users u ON i.company_id = u.id
        WHERE i.id = ?
    ");
    $stmt->bind_param("i", $internship_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $internship = $result->fetch_assoc();
        $company_id = $internship['company_id'];
        $current_trust_level = $internship['trust_level'] ?? 'new';
        $current_count = isset($internship['approved_posts_count']) && $internship['approved_posts_count'] !== null 
            ? (int)$internship['approved_posts_count'] 
            : 0;
        
        $start_date = $internship['start_date'] ?? date('Y-m-d');
        $today = date('Y-m-d');
        
        // ✅ SMART APPROVAL LOGIC based on start_date
        if ($start_date <= $today) {
            // Start date already passed or is today → Make ACTIVE immediately
            $new_status = 'active';
            $status_message = "The internship is now ACTIVE and visible to students!";
        } else {
            // Start date is future → Set as APPROVED, will auto-activate later
            $new_status = 'approved';
            $status_message = "The internship is approved and will automatically go live on " . date('M d, Y', strtotime($start_date)) . ".";
        }
        
        // 1. Update internship status
        $update_internship = $conn->prepare("UPDATE internships SET status = ?, updated_at = NOW() WHERE id = ?");
        $update_internship->bind_param("si", $new_status, $internship_id);
        $update_internship->execute();
        
        // 2. Increment approved_posts_count
        $increment_count = $conn->prepare("UPDATE companies SET approved_posts_count = approved_posts_count + 1 WHERE user_id = ?");
        $increment_count->bind_param("i", $company_id);
        $increment_count->execute();
        
        $new_count = $current_count + 1;
        
        // 3. ✅ Check if upgrade to "verified" is needed (at count = 5)
        if ($new_count == 5 && $current_trust_level === 'new') {
            $upgrade_stmt = $conn->prepare("UPDATE companies SET trust_level = 'verified' WHERE user_id = ?");
            $upgrade_stmt->bind_param("i", $company_id);
            $upgrade_stmt->execute();
            
            // Send email notification about upgrade
            // sendTrustLevelUpgradeEmail($company_id, 'verified');
            
            $success_message = "✅ Internship approved! Company '{$internship['company_name']}' has been upgraded to VERIFIED status (5 approved posts). Future posts will be auto-approved! {$status_message}";
            
            // Log the upgrade
            error_log("Admin {$admin_name} approved internship ID: {$internship_id}. Company ID: {$company_id} upgraded to VERIFIED.");
        }
        // 4. ✅ Check if upgrade to "trusted" is needed (at count = 10)
        elseif ($new_count == 10 && $current_trust_level === 'verified') {
            $upgrade_stmt = $conn->prepare("UPDATE companies SET trust_level = 'trusted' WHERE user_id = ?");
            $upgrade_stmt->bind_param("i", $company_id);
            $upgrade_stmt->execute();
            
            // Send email notification about upgrade
            // sendTrustLevelUpgradeEmail($company_id, 'trusted');
            
            $success_message = "✅ Internship approved! Company '{$internship['company_name']}' has been upgraded to TRUSTED status (10 approved posts)! {$status_message}";
            
            // Log the upgrade
            error_log("Admin {$admin_name} approved internship ID: {$internship_id}. Company ID: {$company_id} upgraded to TRUSTED.");
        }
        else {
            $success_message = "✅ Internship approved successfully! ({$new_count}/5 posts for VERIFIED status). {$status_message}";
        }
        
        // Log admin action
        error_log("Admin {$admin_name} approved internship ID: {$internship_id} for company ID: {$company_id}. Status set to: {$new_status}");
    } else {
        $error_message = "Internship not found";
    }
}

// ✅ Handle Reject Action with rejection_reason saved to database
if (isset($_POST['reject_internship'])) {
    $internship_id = (int)$_POST['internship_id'];
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    // Get internship details
    $stmt = $conn->prepare("
        SELECT i.*, u.email, u.full_name, c.name as company_name
        FROM internships i
        JOIN companies c ON i.company_id = c.user_id
        JOIN users u ON i.company_id = u.id
        WHERE i.id = ?
    ");
    $stmt->bind_param("i", $internship_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $internship = $result->fetch_assoc();
        
        // Update internship status AND save rejection_reason
        $update_stmt = $conn->prepare("UPDATE internships SET status = 'rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->bind_param("si", $rejection_reason, $internship_id);
        $update_stmt->execute();
        
        // Optionally send rejection email with reason
        // sendInternshipRejectionEmail($internship['email'], $internship['title'], $rejection_reason);
        
        $success_message = "❌ Internship rejected successfully.";
        
        // Log admin action
        error_log("Admin {$admin_name} rejected internship ID: {$internship_id}. Reason: {$rejection_reason}");
    } else {
        $error_message = "Internship not found.";
    }
}

// Fetch all pending internships with start_date and end_date
$query = "
    SELECT 
        i.id,
        i.title,
        i.internship_type,
        i.location,
        i.location_type,
        i.positions_available,
        i.created_at,
        i.application_deadline,
        i.start_date,
        i.end_date,
        c.name as company_name,
        c.trust_level,
        c.approved_posts_count,
        u.full_name as contact_person,
        u.email
    FROM internships i
    JOIN companies c ON i.company_id = c.user_id
    JOIN users u ON i.company_id = u.id
    WHERE i.status = 'pending'
    ORDER BY i.created_at DESC
";

$pending_internships = $conn->query($query);
$total_pending = $pending_internships->num_rows;
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

        .content {
            padding: 2rem;
            max-width: 1400px;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        /* Stats Card */
        .stats-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stats-card h2 {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .stats-card p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Internship Cards */
        .internships-grid {
            display: grid;
            gap: 1.5rem;
        }

        .internship-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1.5rem;
        }

        .internship-title {
            font-size: 1.3rem;
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .company-name {
            color: #667eea;
            font-weight: 600;
            font-size: 1rem;
        }

        .trust-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .trust-badge.new { background: #e3f2fd; color: #1976d2; }
        .trust-badge.verified { background: #e8f5e9; color: #388e3c; }
        .trust-badge.trusted { background: #fff3e0; color: #f57c00; }

        .card-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-label {
            font-size: 0.8rem;
            color: #7f8c8d;
            text-transform: uppercase;
            font-weight: 600;
        }

        .info-value {
            font-size: 0.95rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .card-actions {
            display: flex;
            gap: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .btn-approve {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-reject {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-view {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 2rem;
            border-radius: 16px;
            max-width: 500px;
        }

        .modal-header {
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            font-size: 1.3rem;
            color: #2c3e50;
        }

        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
        }

        .empty-state i {
            font-size: 4rem;
            color: #95a5a6;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #7f8c8d;
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
        <a href="company-approvals.php" class="nav-item">
            <i class="fas fa-building"></i>
            <span>Company Approvals</span>
        </a>

        <div class="nav-section-title">INTERNSHIPS</div>
        <a href="internship-approvals.php" class="nav-item active">
            <i class="fas fa-briefcase"></i>
            <span>Internship Approvals</span>
        </a>
        <a href="internships.php" class="nav-item">
            <i class="fas fa-list"></i>
            <span>All Internships</span>
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
                <h1>Internship Approvals</h1>
                <p>Review and approve pending internship postings</p>
            </div>
        </div>

     <div class="content">
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-card">
            <div>
                <h2><?php echo $total_pending; ?></h2>
                <p>Pending Internship Approvals</p>
            </div>
            <i class="fas fa-clock" style="font-size: 4rem; opacity: 0.3;"></i>
        </div>

        <!-- Pending Internships -->
        <?php if ($total_pending > 0): ?>
            <div class="internships-grid">
                <?php while ($internship = $pending_internships->fetch_assoc()): ?>
                    <div class="internship-card">
                        <div class="card-header">
                            <div>
                                <h3 class="internship-title"><?php echo htmlspecialchars($internship['title']); ?></h3>
                                <p class="company-name"><?php echo htmlspecialchars($internship['company_name']); ?></p>
                            </div>
                            <span class="trust-badge <?php echo $internship['trust_level']; ?>">
                                <?php echo strtoupper($internship['trust_level']); ?>
                            </span>
                        </div>

                        <div class="card-info">
                            <div class="info-item">
                                <span class="info-label">Type</span>
                                <span class="info-value"><?php echo $internship['internship_type']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Location</span>
                                <span class="info-value"><?php echo $internship['location']; ?> (<?php echo $internship['location_type']; ?>)</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Positions</span>
                                <span class="info-value"><?php echo $internship['positions_available']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Posted On</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($internship['created_at'])); ?></span>
                            </div>
                            
                            <!-- ✅ NEW: Display Start Date -->
                            <?php if (!empty($internship['start_date'])): ?>
                            <div class="info-item">
                                <span class="info-label">Start Date</span>
                                <span class="info-value" style="<?php echo ($internship['start_date'] <= date('Y-m-d')) ? 'color: #10b981; font-weight: 600;' : ''; ?>">
                                    <?php echo date('M d, Y', strtotime($internship['start_date'])); ?>
                                    <?php if ($internship['start_date'] <= date('Y-m-d')): ?>
                                        <small style="display: block; font-size: 0.75rem;">(Will go ACTIVE immediately)</small>
                                    <?php else: ?>
                                        <small style="display: block; font-size: 0.75rem;">(Scheduled)</small>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- ✅ NEW: Display End Date -->
                            <?php if (!empty($internship['end_date'])): ?>
                            <div class="info-item">
                                <span class="info-label">End Date</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($internship['end_date'])); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="info-item">
                                <span class="info-label">Deadline</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($internship['application_deadline'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Approved Posts</span>
                                <span class="info-value"><?php echo $internship['approved_posts_count'] ?? 0; ?> / 5</span>
                            </div>
                        </div>

                        <div class="card-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="internship_id" value="<?php echo $internship['id']; ?>">
                                <button type="submit" name="approve_internship" class="btn btn-approve" onclick="return confirm('Approve this internship?');">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </form>

                            <button class="btn btn-reject" onclick="openRejectModal(<?php echo $internship['id']; ?>)">
                                <i class="fas fa-times"></i> Reject
                            </button>

                            <a href="view-internship-details.php?id=<?php echo $internship['id']; ?>" class="btn btn-view">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>All Caught Up!</h3>
                <p>No pending internship approvals at the moment.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Reject Modal (unchanged) -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reject Internship</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="internship_id" id="reject_internship_id">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Rejection Reason (optional)</label>
                <textarea name="rejection_reason" placeholder="Enter reason for rejection..."></textarea>
                <div class="modal-actions">
                    <button type="submit" name="reject_internship" class="btn btn-reject">
                        <i class="fas fa-times"></i> Confirm Rejection
                    </button>
                    <button type="button" class="btn btn-view" onclick="closeRejectModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRejectModal(internshipId) {
            document.getElementById('reject_internship_id').value = internshipId;
            document.getElementById('rejectModal').style.display = 'block';
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('rejectModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>