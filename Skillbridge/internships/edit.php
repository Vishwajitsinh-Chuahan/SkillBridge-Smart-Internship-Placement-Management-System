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

// Get internship ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage.php');
    exit();
}

$internship_id = (int)$_GET['id'];

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_internship'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $requirements = trim($_POST['requirements']);
    
    $selected_skills = isset($_POST['skills_required']) ? $_POST['skills_required'] : [];
    $custom_skills_input = trim($_POST['custom_skills'] ?? '');
    
    if (!empty($custom_skills_input)) {
        $custom_skills_array = array_map('trim', explode(',', $custom_skills_input));
        $selected_skills = array_merge($selected_skills, $custom_skills_array);
    }
    
    $skills_required = implode(', ', $selected_skills);
    $internship_type = $_POST['internship_type'];
    $location = trim($_POST['location']);
    $location_type = $_POST['location_type'];
    $duration = trim($_POST['duration']);
    $stipend = trim($_POST['stipend']);
    $positions_available = (int)$_POST['positions_available'];
    $application_deadline = $_POST['application_deadline'];

    // Validation
    if (empty($title) || empty($description) || empty($requirements) || empty($location) || empty($application_deadline)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        // Update internship
        $stmt = $conn->prepare("UPDATE internships SET title = ?, description = ?, requirements = ?, skills_required = ?, internship_type = ?, location = ?, location_type = ?, duration = ?, stipend = ?, positions_available = ?, application_deadline = ?, updated_at = NOW() WHERE id = ? AND company_id = ?");
        
        $stmt->bind_param("sssssssssssii", 
            $title, 
            $description, 
            $requirements, 
            $skills_required, 
            $internship_type, 
            $location, 
            $location_type, 
            $duration, 
            $stipend, 
            $positions_available, 
            $application_deadline,
            $internship_id,
            $user_id
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = 'Internship updated successfully!';
            header('Location: view.php?id=' . $internship_id);
            exit();
        } else {
            $error_message = 'Failed to update internship. Please try again.';
        }
    }
}

// Fetch internship details
$stmt = $conn->prepare("SELECT * FROM internships WHERE id = ? AND company_id = ?");
$stmt->bind_param("ii", $internship_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Internship not found or you do not have permission to edit it.';
    header('Location: manage.php');
    exit();
}

$internship = $result->fetch_assoc();

// Get all skills
$skills_query = "SELECT * FROM skills ORDER BY category, skill_name";
$skills_result = $conn->query($skills_query);
$all_skills = [];
if ($skills_result) {
    while ($row = $skills_result->fetch_assoc()) {
        $all_skills[$row['category']][] = $row;
    }
}

// Get selected skills
$selected_skills_array = !empty($internship['skills_required']) ? explode(', ', $internship['skills_required']) : [];

$user_initials = strtoupper(substr($full_name, 0, 2));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Internship - SkillBridge</title>
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

        /* Sidebar Styling */
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .page-title h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-title p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .content {
            padding: 2rem;
            max-width: 1200px;
        }

        /* Form Styling - Same as create.php */
        .form-container {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-header i {
            font-size: 1.5rem;
            color: #667eea;
        }

        .section-header h3 {
            font-size: 1.25rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        .form-group label .required {
            color: #e74c3c;
            margin-left: 0.25rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control {
            min-height: 140px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .skills-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .skills-checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.75rem;
            max-height: 250px;
            overflow-y: auto;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .skill-category-header {
            grid-column: 1 / -1;
            font-weight: 700;
            color: #667eea;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid #e0e0e0;
        }

        .skill-category-header:first-child {
            margin-top: 0;
            padding-top: 0;
            border-top: none;
        }

        .skill-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .skill-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #667eea;
        }

        .skill-item label {
            margin: 0;
            font-weight: 400;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .custom-skills-box {
            background: linear-gradient(135deg, #667eea15, #764ba215);
            padding: 1.5rem;
            border-radius: 12px;
            border: 2px dashed #667eea;
            margin-top: 1rem;
        }

        .custom-skills-box h4 {
            color: #667eea;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #f0f0f0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

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

        .character-count {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }

        .help-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.5rem;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .form-row {
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
                <h1>Edit Internship</h1>
                <p>Update internship details and information</p>
            </div>
        </div>

        <div class="content">
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" action="">
                    <!-- Basic Information -->
                    <div class="section-header">
                        <i class="fas fa-info-circle"></i>
                        <h3>Basic Information</h3>
                    </div>

                    <div class="form-group">
                        <label>Internship/Job Title <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($internship['title']); ?>" required maxlength="200">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Type <span class="required">*</span></label>
                            <select name="internship_type" class="form-control" required>
                                <option value="Internship" <?php echo $internship['internship_type'] === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                                <option value="Job" <?php echo $internship['internship_type'] === 'Job' ? 'selected' : ''; ?>>Full-time Job</option>
                                <option value="Both" <?php echo $internship['internship_type'] === 'Both' ? 'selected' : ''; ?>>Both</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Positions Available <span class="required">*</span></label>
                            <input type="number" name="positions_available" class="form-control" min="1" value="<?php echo $internship['positions_available']; ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description <span class="required">*</span></label>
                        <textarea name="description" class="form-control" required maxlength="5000"><?php echo htmlspecialchars($internship['description']); ?></textarea>
                        <div class="character-count">Maximum 5000 characters</div>
                    </div>

                    <div class="form-group">
                        <label>Requirements & Qualifications <span class="required">*</span></label>
                        <textarea name="requirements" class="form-control" required maxlength="3000"><?php echo htmlspecialchars($internship['requirements']); ?></textarea>
                        <div class="character-count">Maximum 3000 characters</div>
                    </div>

                    <!-- Skills Section -->
                    <div class="section-header">
                        <i class="fas fa-cogs"></i>
                        <h3>Required Skills</h3>
                    </div>

                    <div class="skills-section">
                        <label style="font-weight: 600; margin-bottom: 1rem; display: block;">Select Skills</label>
                        <div class="skills-checkbox-group">
                            <?php if (!empty($all_skills)): ?>
                                <?php foreach ($all_skills as $category => $skills): ?>
                                    <div class="skill-category-header"><?php echo htmlspecialchars($category); ?></div>
                                    <?php foreach ($skills as $skill): ?>
                                        <div class="skill-item">
                                            <input type="checkbox" name="skills_required[]" value="<?php echo htmlspecialchars($skill['skill_name']); ?>" id="skill_<?php echo $skill['id']; ?>" <?php echo in_array($skill['skill_name'], $selected_skills_array) ? 'checked' : ''; ?>>
                                            <label for="skill_<?php echo $skill['id']; ?>">
                                                <?php echo htmlspecialchars($skill['skill_name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="custom-skills-box">
                            <h4><i class="fas fa-plus-circle"></i> Add Custom Skills</h4>
                            <p class="help-text" style="margin-bottom: 1rem;">Add custom skills separated by commas</p>
                            <div class="form-group" style="margin-bottom: 0;">
                                <input type="text" name="custom_skills" class="form-control" placeholder="Enter custom skills separated by commas">
                            </div>
                        </div>
                    </div>

                    <!-- Location Details -->
                    <div class="section-header">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3>Location Details</h3>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Location <span class="required">*</span></label>
                            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($internship['location']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Work Mode <span class="required">*</span></label>
                            <select name="location_type" class="form-control" required>
                                <option value="On-site" <?php echo $internship['location_type'] === 'On-site' ? 'selected' : ''; ?>>On-site</option>
                                <option value="Remote" <?php echo $internship['location_type'] === 'Remote' ? 'selected' : ''; ?>>Remote</option>
                                <option value="Hybrid" <?php echo $internship['location_type'] === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                            </select>
                        </div>
                    </div>

                    <!-- Compensation & Timeline -->
                    <div class="section-header">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>Compensation & Timeline</h3>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Duration</label>
                            <input type="text" name="duration" class="form-control" value="<?php echo htmlspecialchars($internship['duration']); ?>">
                        </div>

                        <div class="form-group">
                            <label>Stipend/Salary</label>
                            <input type="text" name="stipend" class="form-control" value="<?php echo htmlspecialchars($internship['stipend']); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Application Deadline <span class="required">*</span></label>
                        <input type="date" name="application_deadline" class="form-control" value="<?php echo $internship['application_deadline']; ?>" required>
                    </div>

                    <!-- Form Buttons -->
                    <div class="form-buttons">
                        <button type="submit" name="update_internship" class="btn-primary">
                            <i class="fas fa-save"></i>
                            Update Internship
                        </button>
                        <a href="view.php?id=<?php echo $internship_id; ?>" class="btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
