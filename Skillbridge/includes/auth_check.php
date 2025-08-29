

<?php
/**
 * SkillBridge Authentication Helper Functions
 * Enhanced authentication and authorization system
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Get logged in user data
function get_logged_in_user() {
    global $conn;
    
    if (!is_logged_in()) {
        return null;
    }
    
    $stmt = $conn->prepare("
        SELECT u.*, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Check user role
function has_role($role) {
    return is_logged_in() && $_SESSION['role'] === $role;
}

// Check if user has any of the specified roles
function has_any_role($roles) {
    if (!is_logged_in()) {
        return false;
    }
    
    return in_array($_SESSION['role'], $roles);
}

// Redirect if not logged in
function require_login($redirect_url = null) {
    if (!is_logged_in()) {
        $redirect = $redirect_url ?: BASE_URL . "/auth/login.php";
        header("Location: " . $redirect);
        exit();
    }
}

// Redirect if not specific role
function require_role($role, $redirect_url = null) {
    require_login();
    
    if (!has_role($role)) {
        $redirect = $redirect_url ?: BASE_URL . "/auth/login.php?error=access_denied";
        header("Location: " . $redirect);
        exit();
    }
}

// Require specific roles (multiple roles allowed)
function require_any_role($roles, $redirect_url = null) {
    require_login();
    
    if (!has_any_role($roles)) {
        $redirect = $redirect_url ?: BASE_URL . "/auth/login.php?error=access_denied";
        header("Location: " . $redirect);
        exit();
    }
}

// ✅ ADMIN-SPECIFIC FUNCTIONS (This was missing!)
function requireAdmin() {
    require_role('Admin');
}

// Role-specific helper functions
function require_student() {
    require_role('Student');
}

function require_company() {
    require_role('Company');
}

// Check if user can access admin features
function can_access_admin() {
    return has_role('Admin');
}

// Check if user can manage companies
function can_manage_companies() {
    return has_role('Admin');
}

// Check if user can post jobs
function can_post_jobs() {
    return has_any_role(['Company', 'Admin']);
}

// Set flash message
function set_message($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

// Get and display flash message
function show_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        
        $icon = '';
        switch ($type) {
            case 'success':
                $icon = 'fas fa-check-circle';
                break;
            case 'error':
                $icon = 'fas fa-exclamation-circle';
                break;
            case 'warning':
                $icon = 'fas fa-exclamation-triangle';
                break;
            default:
                $icon = 'fas fa-info-circle';
        }
        
        echo "<div class='alert alert-{$type}' style='margin: 1rem 0; padding: 1rem; border-radius: 8px;'>";
        echo "<i class='{$icon}'></i> " . htmlspecialchars($message);
        echo "</div>";
    }
}

// Get user's full name
function get_user_name() {
    return is_logged_in() ? $_SESSION['full_name'] : 'Guest';
}

// Get user role
function get_user_role() {
    return is_logged_in() ? $_SESSION['role'] : null;
}

// Check if account status is active
function is_account_active() {
    $user = get_logged_in_user();
    return $user && $user['status'] === 'active';
}

// Generate secure logout URL
function get_logout_url() {
    return BASE_URL . "/auth/logout.php";
}

// Log user activity
function log_user_activity($action, $details = '') {
    global $conn;
    
    if (!is_logged_in()) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $conn->prepare("
        INSERT INTO user_activity_log (user_id, action, details, ip_address, user_agent, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    if ($stmt) {
        $stmt->bind_param("issss", $user_id, $action, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    
    return false;
}

// Check session timeout (30 minutes for regular users, 20 minutes for admins)
function check_session_timeout() {
    if (!is_logged_in()) {
        return;
    }
    
    $timeout = has_role('Admin') ? 1200 : 1800; // 20 min for admin, 30 min for others
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_destroy();
        header("Location: " . BASE_URL . "/auth/login.php?timeout=true");
        exit();
    }
    
    $_SESSION['last_activity'] = time();
}

// Auto-call session timeout check
check_session_timeout();
?>
