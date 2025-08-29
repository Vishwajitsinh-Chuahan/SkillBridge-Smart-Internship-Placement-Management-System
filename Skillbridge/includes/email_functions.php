<?php
// SkillBridge - Email Functions
require_once '../config/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once '../vendor/phpmailer/src/Exception.php';
require_once '../vendor/phpmailer/src/PHPMailer.php';
require_once '../vendor/phpmailer/src/SMTP.php';

/**
 * Send Email Function
 */
function sendEmail($to, $subject, $body, $isHTML = true) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Set charset to UTF-8 for proper emoji support
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"];
    }
}

/**
 * Generate Welcome Email HTML for Students
 */
function generateStudentWelcomeEmail($username, $email, $full_name) {
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Welcome to SkillBridge</title>
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f4f4f4;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
                color: white;
                padding: 40px 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
            }
            .header p {
                margin: 10px 0 0 0;
                font-size: 16px;
                opacity: 0.9;
            }
            .content {
                padding: 40px 30px;
            }
            .welcome-message {
                background: linear-gradient(135deg, #dbeafe 0%, #dcfce7 100%);
                padding: 25px;
                border-radius: 8px;
                margin-bottom: 30px;
                border-left: 4px solid #2563eb;
            }
            .welcome-message h2 {
                margin: 0 0 15px 0;
                color: #2563eb;
                font-size: 24px;
            }
            .account-details {
                background: #fff;
                border: 2px solid #e5e7eb;
                border-radius: 8px;
                padding: 20px;
                margin: 25px 0;
            }
            .account-details h3 {
                margin: 0 0 15px 0;
                color: #374151;
                font-size: 18px;
            }
            .detail-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid #f3f4f6;
            }
            .detail-row:last-child {
                border-bottom: none;
            }
            .detail-label {
                font-weight: 600;
                color: #6b7280;
            }
            .detail-value {
                color: #374151;
            }
            .next-steps {
                margin: 30px 0;
            }
            .next-steps h3 {
                color: #374151;
                margin-bottom: 15px;
            }
            .next-steps ul {
                padding-left: 20px;
            }
            .next-steps li {
                margin: 8px 0;
                color: #6b7280;
            }
            .cta-button {
                text-align: center;
                margin: 35px 0;
            }
            .cta-button a {
                display: inline-block;
                background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
                color: white;
                text-decoration: none;
                padding: 15px 35px;
                border-radius: 8px;
                font-weight: 600;
                font-size: 16px;
                transition: transform 0.2s;
            }
            .cta-button a:hover {
                transform: translateY(-2px);
            }
            .footer {
                background: #f9fafb;
                padding: 30px;
                text-align: center;
                color: #6b7280;
                font-size: 14px;
                border-top: 1px solid #e5e7eb;
            }
            .footer p {
                margin: 5px 0;
            }
            .social-links {
                margin: 20px 0;
            }
            .social-links a {
                display: inline-block;
                margin: 0 10px;
                color: #2563eb;
                text-decoration: none;
            }
            .emoji {
                font-family: "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", sans-serif;
                font-size: 1.2em;
            }
            @media (max-width: 600px) {
                .container {
                    margin: 10px;
                    border-radius: 5px;
                }
                .header, .content, .footer {
                    padding: 20px;
                }
                .detail-row {
                    flex-direction: column;
                }
                .detail-value {
                    margin-top: 5px;
                    font-weight: 500;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <!-- Header -->
            <div class="header">
                <h1><span class="emoji">🎓</span> Welcome to SkillBridge!</h1>
                <p>Your journey to amazing career opportunities starts here</p>
            </div>
            
            <!-- Main Content -->
            <div class="content">
                <!-- Welcome Message -->
                <div class="welcome-message">
                    <h2>Hello ' . htmlspecialchars($full_name) . '!</h2>
                    <p>Welcome to SkillBridge! We\'re thrilled to have you join our community of ambitious students ready to explore incredible internship opportunities and kickstart their careers.</p>
                </div>
                
                <!-- Account Details -->
                <div class="account-details">
                    <h3><span class="emoji">📋</span> Your Account Details</h3>
                    <div class="detail-row">
                        <span class="detail-label">Full Name:</span>
                        <span class="detail-value">' . htmlspecialchars($full_name) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Username:</span>
                        <span class="detail-value">' . htmlspecialchars($username) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value">' . htmlspecialchars($email) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Account Type:</span>
                        <span class="detail-value">Student</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Registration Date:</span>
                        <span class="detail-value">' . date('F d, Y') . '</span>
                    </div>
                </div>
                
                <!-- Next Steps -->
                <div class="next-steps">
                    <h3><span class="emoji">🚀</span> What\'s Next?</h3>
                    <p>Here are some things you can do to get started on your career journey:</p>
                    <ul>
                        <li>Complete your student profile with your skills and interests</li>
                        <li>Browse thousands of internship opportunities</li>
                        <li>Connect with top companies in your field</li>
                        <li>Apply for internships that match your career goals</li>
                        <li>Build your professional network</li>
                    </ul>
                </div>
                
                <!-- CTA Button -->
                <div class="cta-button">
                    <a href="' . BASE_URL . '/auth/login.php">Start Exploring Internships</a>
                </div>
                
                <!-- Additional Info -->
                <div style="background: #fef3c7; padding: 20px; border-radius: 8px; border-left: 4px solid #f59e0b; margin: 25px 0;">
                    <h4 style="margin: 0 0 10px 0; color: #92400e;"><span class="emoji">💡</span> Pro Tips for Success:</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #92400e;">
                        <li>Upload a professional profile picture and resume</li>
                        <li>Write a compelling bio highlighting your achievements</li>
                        <li>Set up job alerts for your preferred roles</li>
                        <li>Apply early to increase your chances of selection</li>
                    </ul>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p><strong>' . COMPANY_NAME . '</strong> - Bridging Skills with Career Opportunities</p>
                <p>Need help? Contact us at <a href="mailto:' . SUPPORT_EMAIL . '" style="color: #2563eb;">' . SUPPORT_EMAIL . '</a></p>
                
                <div class="social-links">
                    <a href="#">LinkedIn</a> •
                    <a href="#">Twitter</a> •
                    <a href="#">Facebook</a> •
                    <a href="#">Instagram</a>
                </div>
                
                <p style="font-size: 12px; color: #9ca3af; margin-top: 20px;">
                    This email was sent to ' . htmlspecialchars($email) . ' because you created a student account on SkillBridge.<br>
                    If you didn\'t create this account, please contact our support team immediately.
                </p>
                
                <p style="font-size: 12px; color: #9ca3af;">
                    &copy; ' . date('Y') . ' SkillBridge. All rights reserved.
                </p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Generate Welcome Email HTML for Companies
 */
function generateCompanyWelcomeEmail($username, $email, $full_name, $company_name) {
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Welcome to SkillBridge</title>
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f4f4f4;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
                color: white;
                padding: 40px 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
            }
            .content {
                padding: 40px 30px;
            }
            .welcome-message {
                background: linear-gradient(135deg, #dbeafe 0%, #dcfce7 100%);
                padding: 25px;
                border-radius: 8px;
                margin-bottom: 30px;
                border-left: 4px solid #2563eb;
            }
            .welcome-message h2 {
                margin: 0 0 15px 0;
                color: #2563eb;
                font-size: 24px;
            }
            .pending-notice {
                background: #fef3c7;
                padding: 20px;
                border-radius: 8px;
                border-left: 4px solid #f59e0b;
                margin: 25px 0;
            }
            .pending-notice h3 {
                margin: 0 0 10px 0;
                color: #92400e;
                font-size: 18px;
            }
            .cta-button {
                text-align: center;
                margin: 35px 0;
            }
            .cta-button a {
                display: inline-block;
                background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
                color: white;
                text-decoration: none;
                padding: 15px 35px;
                border-radius: 8px;
                font-weight: 600;
                font-size: 16px;
            }
            .footer {
                background: #f9fafb;
                padding: 30px;
                text-align: center;
                color: #6b7280;
                font-size: 14px;
                border-top: 1px solid #e5e7eb;
            }
            .emoji {
                font-family: "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", sans-serif;
                font-size: 1.2em;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><span class="emoji">🏢</span> Welcome to SkillBridge!</h1>
                <p>Partner with the brightest minds</p>
            </div>
            
            <div class="content">
                <div class="welcome-message">
                    <h2>Hello ' . htmlspecialchars($full_name) . '!</h2>
                    <p>Thank you for registering <strong>' . htmlspecialchars($company_name) . '</strong> with SkillBridge! We\'re excited to help you connect with talented students and build the next generation of professionals.</p>
                </div>
                
                <div class="pending-notice">
                    <h3><span class="emoji">⏳</span> Account Under Review</h3>
                    <p><strong>Your company account is currently pending approval.</strong></p>
                    <p>Our admin team will review your registration within <strong>1-2 business days</strong>. Once approved, you\'ll receive a confirmation email and can start:</p>
                    <ul style="margin: 10px 0; padding-left: 20px; color: #92400e;">
                        <li>Posting internship opportunities</li>
                        <li>Browsing student profiles</li>
                        <li>Managing applications</li>
                        <li>Building your talent pipeline</li>
                    </ul>
                </div>
                
                <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6; margin: 25px 0;">
                    <h4 style="margin: 0 0 10px 0; color: #1e40af;"><span class="emoji">📋</span> What Happens Next?</h4>
                    <ol style="margin: 0; padding-left: 20px; color: #1e40af;">
                        <li><strong>Verification Process:</strong> We verify your company details</li>
                        <li><strong>Approval Email:</strong> You\'ll receive confirmation once approved</li>
                        <li><strong>Complete Profile:</strong> Add company details, logo, and description</li>
                        <li><strong>Start Recruiting:</strong> Post your first internship opportunity</li>
                    </ol>
                </div>
                
                <div style="text-align: center; margin: 30px 0; padding: 20px; background: #f9fafb; border-radius: 8px;">
                    <h4 style="color: #374151; margin-bottom: 10px;">Need Assistance?</h4>
                    <p style="color: #6b7280; margin: 0;">Contact our support team at <a href="mailto:' . SUPPORT_EMAIL . '" style="color: #2563eb;">' . SUPPORT_EMAIL . '</a></p>
                    <p style="color: #6b7280; margin: 5px 0 0 0;">We\'re here to help you get started!</p>
                </div>
            </div>
            
            <div class="footer">
                <p><strong>' . COMPANY_NAME . '</strong> - Bridging Skills with Career Opportunities</p>
                <p style="font-size: 12px; color: #9ca3af; margin-top: 20px;">
                    This email was sent to ' . htmlspecialchars($email) . ' because you registered a company account on SkillBridge.
                </p>
                <p style="font-size: 12px; color: #9ca3af;">
                    &copy; ' . date('Y') . ' SkillBridge. All rights reserved.
                </p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Send Welcome Email to New User
 */
function sendWelcomeEmail($name, $email, $role, $username = '', $company_name = '') {
    if ($role === 'Student') {
        $subject = "Welcome to SkillBridge - Your Career Journey Starts Here!";
        $body = generateStudentWelcomeEmail($username, $email, $name);
    } elseif ($role === 'Company') {
        $subject = "Welcome to SkillBridge - Account Pending Approval";
        $body = generateCompanyWelcomeEmail($username, $email, $name, $company_name);
    } else {
        $subject = "Welcome to SkillBridge - Your Account is Ready!";
        $body = generateStudentWelcomeEmail($username, $email, $name); // Default template
    }
    
    return sendEmail($email, $subject, $body, true);
}

/**
 * Generate Password Reset Email HTML (Common for all roles)
 */
function generatePasswordResetEmail($name, $email, $resetToken, $role = '') {
    // Reset URL for your SkillBridge setup
    $resetUrl = BASE_URL . "/auth/reset_password.php?token=" . $resetToken;
    
    $roleText = $role ? " ({$role})" : "";
    
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Reset Your Password - SkillBridge</title>
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f4f4f4;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                color: white;
                padding: 40px 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
            }
            .content {
                padding: 40px 30px;
            }
            .warning-box {
                background: #fef2f2;
                border: 2px solid #fecaca;
                border-radius: 8px;
                padding: 20px;
                margin: 25px 0;
                border-left: 4px solid #ef4444;
            }
            .warning-box h3 {
                margin: 0 0 10px 0;
                color: #dc2626;
                font-size: 18px;
            }
            .reset-button {
                text-align: center;
                margin: 35px 0;
            }
            .reset-button a {
                display: inline-block;
                background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
                color: white !important;
                text-decoration: none;
                padding: 15px 35px;
                border-radius: 8px;
                font-weight: 600;
                font-size: 16px;
            }
            .info-box {
                background: #f0f9ff;
                padding: 20px;
                border-radius: 8px;
                border-left: 4px solid #3b82f6;
                margin: 25px 0;
            }
            .footer {
                background: #f9fafb;
                padding: 30px;
                text-align: center;
                color: #6b7280;
                font-size: 14px;
                border-top: 1px solid #e5e7eb;
            }
            @media (max-width: 600px) {
                .container { margin: 10px; }
                .header, .content, .footer { padding: 20px; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><span style="font-family: Apple Color Emoji, Segoe UI Emoji;">🔒</span> Password Reset Request</h1>
                <p>Reset your SkillBridge account password</p>
            </div>
            
            <div class="content">
                <h2>Hello ' . htmlspecialchars($name) . ',</h2>
                <p>We received a request to reset the password for your SkillBridge account' . $roleText . ' associated with <strong>' . htmlspecialchars($email) . '</strong>.</p>
                
                <div class="warning-box">
                    <h3><span style="font-family: Apple Color Emoji, Segoe UI Emoji;">⚠️</span> Security Notice</h3>
                    <p>If you did not request this password reset, please ignore this email and your password will remain unchanged. Someone may have entered your email address by mistake.</p>
                </div>
                
                <p>To reset your password, click the button below:</p>
                
                <div class="reset-button">
                    <a href="' . $resetUrl . '">Reset My Password</a>
                </div>
                
                <div class="info-box">
                    <h4><span style="font-family: Apple Color Emoji, Segoe UI Emoji;">⏰</span> Important Information:</h4>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>This reset link will expire in <strong>1 hour</strong></li>
                        <li>The link can only be used <strong>once</strong></li>
                        <li>If the button doesn\'t work, copy and paste this link into your browser:</li>
                    </ul>
                    <p style="background: #e5e7eb; padding: 10px; border-radius: 4px; word-break: break-all; font-size: 12px; font-family: monospace;">
                        ' . $resetUrl . '
                    </p>
                </div>
                
                <p style="margin-top: 30px; color: #6b7280;">
                    <strong>Need help?</strong><br>
                    If you\'re having trouble with the password reset, contact our support team at 
                    <a href="mailto:' . SUPPORT_EMAIL . '" style="color: #2563eb;">' . SUPPORT_EMAIL . '</a>
                </p>
            </div>
            
            <div class="footer">
                <p><strong>' . COMPANY_NAME . '</strong> - Bridging Skills with Career Opportunities</p>
                <p style="font-size: 12px; color: #9ca3af; margin-top: 20px;">
                    This email was sent to ' . htmlspecialchars($email) . ' because a password reset was requested for your SkillBridge account.
                </p>
                <p style="font-size: 12px; color: #9ca3af;">
                    &copy; ' . date('Y') . ' SkillBridge. All rights reserved.
                </p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Send Password Reset Email
 */
function sendPasswordResetEmail($name, $email, $resetToken, $role = '') {
    $subject = "Reset Your SkillBridge Password - Action Required";
    $body = generatePasswordResetEmail($name, $email, $resetToken, $role);
    
    return sendEmail($email, $subject, $body, true);
}

?>


<?php
/**
 * Send password reset email specifically for Admin users
 * Uses the existing email configuration and PHPMailer setup
 */
function sendAdminPasswordResetEmail($name, $email, $resetToken) {
    $resetLink = BASE_URL . "/admin/reset_password.php?token=" . $resetToken;
    
    $subject = "🔐 Admin Password Reset Request - SkillBridge";
    
    $message = "
    <html>
    <head>
        <title>Admin Password Reset - SkillBridge</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f4f4f4; 
            }
            .email-container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white; 
                border-radius: 15px; 
                overflow: hidden; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
            }
            .header { 
                background: linear-gradient(135deg, #667eea, #764ba2); 
                color: white; 
                padding: 40px 30px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 2rem; 
                font-weight: 700; 
            }
            .header p { 
                margin: 10px 0 0 0; 
                opacity: 0.9; 
                font-size: 1.1rem; 
            }
            .content { 
                padding: 40px 30px; 
            }
            .content h2 { 
                color: #1e293b; 
                margin-bottom: 20px; 
                font-size: 1.5rem; 
            }
            .admin-info {
                background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
                border: 1px solid #c084fc;
                border-radius: 10px;
                padding: 20px;
                margin: 20px 0;
                color: #581c87;
            }
            .reset-button { 
                display: block;
                width: fit-content;
                margin: 30px auto;
                background: linear-gradient(135deg, #667eea, #764ba2); 
                color: white !important; 
                padding: 15px 40px; 
                text-decoration: none; 
                border-radius: 10px; 
                font-weight: 600;
                font-size: 1.1rem;
                text-align: center;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            }
            .reset-link { 
                word-break: break-all; 
                background: #f8fafc; 
                padding: 15px; 
                border-radius: 8px; 
                border: 1px solid #e2e8f0;
                font-family: monospace;
                margin: 20px 0;
            }
            .security-warning { 
                background: linear-gradient(135deg, #fef3c7, #fde68a);
                border: 1px solid #f59e0b;
                border-radius: 10px; 
                padding: 20px; 
                margin: 25px 0; 
                color: #92400e;
            }
            .security-warning h3 {
                margin-top: 0;
                color: #78350f;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .security-warning ul {
                margin: 15px 0;
                padding-left: 20px;
            }
            .security-warning li {
                margin-bottom: 8px;
            }
            .footer { 
                background: #1e293b;
                color: #cbd5e1;
                padding: 30px; 
                text-align: center; 
                font-size: 0.9rem;
            }
            .footer strong {
                color: white;
            }
            .footer small {
                opacity: 0.7;
                display: block;
                margin-top: 15px;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>🛡️ Admin Password Reset</h1>
                <p>SkillBridge System Administration</p>
            </div>
            
            <div class='content'>
                <h2>Hello " . htmlspecialchars($name) . ",</h2>
                
                <p>We received a request to reset the password for your SkillBridge administrator account.</p>
                
                <div class='admin-info'>
                    <strong>🔐 Admin Account Details:</strong><br>
                    <strong>Email:</strong> " . htmlspecialchars($email) . "<br>
                    <strong>Role:</strong> System Administrator<br>
                    <strong>Request Time:</strong> " . date('F j, Y \a\t g:i A T') . "<br>
                    <strong>IP Address:</strong> " . $_SERVER['REMOTE_ADDR'] . "
                </div>
                
                <p><strong>Click the button below to reset your admin password:</strong></p>
                
                <a href='" . $resetLink . "' class='reset-button'>
                    🔓 Reset Admin Password
                </a>
                
                <p>Or copy and paste this secure link into your browser:</p>
                <div class='reset-link'>" . $resetLink . "</div>
                
                <div class='security-warning'>
                    <h3>⚠️ Important Security Information</h3>
                    <ul>
                        <li><strong>This link expires in 1 hour</strong> for security reasons</li>
                        <li>This reset request has been <strong>logged and monitored</strong></li>
                        <li>Only authorized system administrators should receive this email</li>
                        <li>If you didn't request this reset, please <strong>contact IT security immediately</strong></li>
                        <li><strong>Never share this link</strong> with anyone else</li>
                        <li>Use a strong, unique password for your admin account</li>
                    </ul>
                </div>
                
                <p>If you have any questions or concerns about this password reset request, please contact the system administrator immediately.</p>
            </div>
            
            <div class='footer'>
                <p><strong>SkillBridge Security Team</strong></p>
                <p>System Administration Portal</p>
                <small>This is an automated security message. Please do not reply to this email.<br>
                All admin activities are logged and monitored for security purposes.</small>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Use your existing email sending function (same as company/student)
    return sendEmail($email, $subject, $message, $name);
}
?>

<?php
/**
 * Send company approval or rejection notification email
 * Uses existing email configuration and infrastructure
 * @param string $contactName - Contact person name
 * @param string $email - Company email address
 * @param string $companyName - Company name
 * @param string $status - 'approved' or 'rejected'
 * @param string $reason - Rejection reason (optional)
 * @return array - Success status and message
 */
function sendCompanyStatusEmail($contactName, $email, $companyName, $status, $reason = '') {
    
    if ($status === 'approved') {
         // ✅ FIXED: Create absolute URL
        $loginUrl = 'http://localhost/Skillbridge/auth/login.php';
        $subject = "🎉 Company Registration Approved - Welcome to SkillBridge!";
       
        
        $message = "
        <html>
        <head>
            <title>Company Registration Approved - SkillBridge</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f4f4f4; 
                }
                .email-container { 
                    max-width: 600px; 
                    margin: 20px auto; 
                    background: white; 
                    border-radius: 15px; 
                    overflow: hidden; 
                    box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
                }
                .header { 
                    background: linear-gradient(135deg, #10b981, #059669); 
                    color: white; 
                    padding: 40px 30px; 
                    text-align: center; 
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 2rem; 
                    font-weight: 700; 
                }
                .header p { 
                    margin: 10px 0 0 0; 
                    opacity: 0.9; 
                    font-size: 1.1rem; 
                }
                .content { 
                    padding: 40px 30px; 
                }
                .content h2 { 
                    color: #1e293b; 
                    margin-bottom: 20px; 
                    font-size: 1.5rem; 
                }
                .company-info {
                    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
                    border: 1px solid #34d399;
                    border-radius: 10px;
                    padding: 20px;
                    margin: 20px 0;
                    color: #065f46;
                }
                .action-button { 
                    display: block;
                    width: fit-content;
                    margin: 30px auto;
                    background: linear-gradient(135deg, #3b82f6, #1d4ed8); 
                    color: white !important; 
                    padding: 15px 40px; 
                    text-decoration: none; 
                    border-radius: 10px; 
                    font-weight: 600;
                    font-size: 1.1rem;
                    text-align: center;
                    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
                }
                .next-steps { 
                    background: linear-gradient(135deg, #eff6ff, #dbeafe);
                    border: 1px solid #93c5fd;
                    border-radius: 10px; 
                    padding: 20px; 
                    margin: 25px 0; 
                    color: #1e40af;
                }
                .next-steps h3 {
                    margin-top: 0;
                    color: #1e3a8a;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .next-steps ul {
                    margin: 15px 0;
                    padding-left: 20px;
                }
                .next-steps li {
                    margin-bottom: 8px;
                }
                .footer { 
                    background: #1e293b;
                    color: #cbd5e1;
                    padding: 30px; 
                    text-align: center; 
                    font-size: 0.9rem;
                }
                .footer strong {
                    color: white;
                }
                .footer small {
                    opacity: 0.7;
                    display: block;
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>🎉 Congratulations!</h1>
                    <p>Your Company Registration has been Approved</p>
                </div>
                 
                <div class='content'>
                    <h2>Welcome to SkillBridge, " . htmlspecialchars($contactName) . "!</h2>
                    
                    <p>We are delighted to inform you that <strong>" . htmlspecialchars($companyName) . "</strong> has been successfully approved to join the SkillBridge platform.</p>
                    
                    <div class='company-info'>
                        <strong>🏢 Your Company Status:</strong><br>
                        <strong>Company:</strong> " . htmlspecialchars($companyName) . "<br>
                        <strong>Status:</strong> ✅ Approved<br>
                        <strong>Approved Date:</strong> " . date('F j, Y \a\t g:i A') . "<br>
                        <strong>Account Access:</strong> Full Access Granted
                    </div>
                    
                    <div class='next-steps'>
                        <h3>🚀 What's Next?</h3>
                        <p>You can now access your company dashboard and start posting internship opportunities:</p>
                        <ul>
                            <li>Log in to your company dashboard</li>
                            <li>Complete your company profile</li>
                            <li>Post internship opportunities</li>
                            <li>Review student applications</li>
                            <li>Connect with talented students</li>
                        </ul>
                    </div>
                    
                <a href='" . $loginUrl . "' class='action-button'>
                    Access Company Dashboard
                </a>
                    
                    <p>Thank you for choosing SkillBridge as your internship partner. We look forward to helping you connect with talented students and grow your team.</p>
                </div>
                
                <div class='footer'>
                    <p><strong>Best regards,</strong><br>The SkillBridge Team</p>
                    <small>This is an automated message. For support, contact us at support@skillbridge.com</small>
                </div>
            </div>
        </body>
        </html>
        ";
        
    } elseif ($status === 'rejected') {
        $subject = "❌ Company Registration Update - SkillBridge";
        
        $message = "
        <html>
        <head>
            <title>Company Registration Status - SkillBridge</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f4f4f4; 
                }
                .email-container { 
                    max-width: 600px; 
                    margin: 20px auto; 
                    background: white; 
                    border-radius: 15px; 
                    overflow: hidden; 
                    box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
                }
                .header { 
                    background: linear-gradient(135deg, #ef4444, #dc2626); 
                    color: white; 
                    padding: 40px 30px; 
                    text-align: center; 
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 2rem; 
                    font-weight: 700; 
                }
                .header p { 
                    margin: 10px 0 0 0; 
                    opacity: 0.9; 
                    font-size: 1.1rem; 
                }
                .content { 
                    padding: 40px 30px; 
                }
                .content h2 { 
                    color: #1e293b; 
                    margin-bottom: 20px; 
                    font-size: 1.5rem; 
                }
                .rejection-info {
                    background: linear-gradient(135deg, #fee2e2, #fecaca);
                    border: 1px solid #f87171;
                    border-radius: 10px;
                    padding: 20px;
                    margin: 20px 0;
                    color: #991b1b;
                }
                .reason-box {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 20px 0;
                    color: #475569;
                    font-style: italic;
                }
                .action-button { 
                    display: block;
                    width: fit-content;
                    margin: 30px auto;
                    background: linear-gradient(135deg, #3b82f6, #1d4ed8); 
                    color: white !important; 
                    padding: 15px 40px; 
                    text-decoration: none; 
                    border-radius: 10px; 
                    font-weight: 600;
                    font-size: 1.1rem;
                    text-align: center;
                    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
                }
                .next-steps { 
                    background: linear-gradient(135deg, #fef3c7, #fde68a);
                    border: 1px solid #f59e0b;
                    border-radius: 10px; 
                    padding: 20px; 
                    margin: 25px 0; 
                    color: #92400e;
                }
                .next-steps h3 {
                    margin-top: 0;
                    color: #78350f;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .next-steps ul {
                    margin: 15px 0;
                    padding-left: 20px;
                }
                .next-steps li {
                    margin-bottom: 8px;
                }
                .footer { 
                    background: #1e293b;
                    color: #cbd5e1;
                    padding: 30px; 
                    text-align: center; 
                    font-size: 0.9rem;
                }
                .footer strong {
                    color: white;
                }
                .footer small {
                    opacity: 0.7;
                    display: block;
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>Registration Update</h1>
                    <p>Company Registration Status</p>
                </div>
                
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($contactName) . ",</h2>
                    
                    <p>Thank you for your interest in joining SkillBridge. After reviewing your company registration for <strong>" . htmlspecialchars($companyName) . "</strong>, we regret to inform you that your application has not been approved at this time.</p>
                    
                    <div class='rejection-info'>
                        <strong>📋 Application Status:</strong><br>
                        <strong>Company:</strong> " . htmlspecialchars($companyName) . "<br>
                        <strong>Status:</strong> ❌ Not Approved<br>
                        <strong>Review Date:</strong> " . date('F j, Y \a\t g:i A') . "
                    </div>";
        
        if (!empty($reason)) {
            $message .= "
                    <h3>📝 Reason for Decision:</h3>
                    <div class='reason-box'>
                        " . nl2br(htmlspecialchars($reason)) . "
                    </div>";
        }
        
        $message .= "
                    <div class='next-steps'>
                        <h3>💡 Next Steps:</h3>
                        <ul>
                            <li>Review the feedback provided above</li>
                            <li>Address any issues mentioned</li>
                            <li>Feel free to reapply after making necessary improvements</li>
                            <li>Contact our support team if you need clarification</li>
                        </ul>
                    </div>
                    
                    <a href='mailto:support@skillbridge.com' class='action-button'>
                        Contact Support Team
                    </a>
                    
                    <p>We appreciate your interest in SkillBridge and encourage you to address the feedback and consider reapplying in the future. Our goal is to maintain a high-quality platform for both companies and students.</p>
                </div>
                
                <div class='footer'>
                    <p><strong>Best regards,</strong><br>The SkillBridge Team</p>
                    <small>This is an automated message. For questions, contact us at support@skillbridge.com</small>
                </div>
            </div>
        </body>
        </html>
        ";
    } else {
        return ['success' => false, 'message' => 'Invalid status provided'];
    }
    
    // ✅ USE YOUR EXISTING EMAIL SYSTEM (like forgot password)
    return sendEmail($email, $subject, $message, $contactName);
}
?>
