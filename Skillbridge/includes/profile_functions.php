<?php
function calculateProfileCompletion($user, $profile) {
    if (!$profile) {
        return 0;
    }
    
    $fields = [
        'full_name' => !empty($user['full_name']),
        'email' => !empty($user['email']),
        'phone' => !empty($user['phone']),
        'university' => !empty($profile['university']),
        'course' => !empty($profile['course']),
        'graduation_year' => !empty($profile['graduation_year']),
        'cgpa' => !empty($profile['cgpa']),
        'year_of_study' => !empty($profile['year_of_study']),
        'skills' => !empty($profile['skills']),
        'bio' => !empty($profile['bio']),
        'github' => !empty($profile['github']),
        'linkedin' => !empty($profile['linkedin']),
        'resume_path' => !empty($profile['resume_path'])
    ];
    
    $completed = count(array_filter($fields));
    $total = count($fields);
    
    return round(($completed / $total) * 100);
}

function canApplyForInternships($user_id, $conn) {
    // Get user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    // Get profile data
    $stmt = $conn->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $profile_result = $stmt->get_result();
    $profile = $profile_result->num_rows > 0 ? $profile_result->fetch_assoc() : null;
    
    // Check if profile is 100% complete
    $completion = calculateProfileCompletion($user, $profile);
    
    return $completion >= 100;
}
?>
