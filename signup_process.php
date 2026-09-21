<?php
require_once "config.php";
verify_csrf();

function clean($v) {
    return trim(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method');
}

// Personal Information
$first_name = clean($_POST['first_name'] ?? '');
$middle_name = clean($_POST['middle_name'] ?? '');
$last_name = clean($_POST['last_name'] ?? '');
$extension = clean($_POST['extension'] ?? '');
$id_number = next_static_id_number($conn);
$birthdate = clean($_POST['birthdate'] ?? '');

// Address Information
$street = clean($_POST['street'] ?? '');
$barangay = clean($_POST['barangay'] ?? '');
$city = clean($_POST['city'] ?? '');
$province = clean($_POST['province'] ?? '');
$country = clean($_POST['country'] ?? '');
$zip = clean($_POST['zip'] ?? '');

// Account Information
$username = clean($_POST['username'] ?? '');
$email = clean($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Security Questions
$auth_a1 = clean($_POST['auth_a1'] ?? '');
$auth_a2 = clean($_POST['auth_a2'] ?? '');
$auth_a3 = clean($_POST['auth_a3'] ?? '');
$current_step = max(1, min(4, (int)($_POST['current_step'] ?? 1)));

// Get the actual question text selected by user (from the select dropdowns)
$question1 = clean($_POST['question1'] ?? '');
$question2 = clean($_POST['question2'] ?? '');
$question3 = clean($_POST['question3'] ?? '');

$errors = [];

// STEP VALIDATION - Strict enforcement of step-by-step progression
// Users must complete personal details (Steps 1-2) before proceeding to password/security questions (Steps 3-4)

// Helper function to validate step 1 (Personal Information)
function validate_step_1($first_name, $middle_name, $last_name, $extension, $id_number, $birthdate) {
    $errors = [];
    
    // Required fields for step 1
    if (empty($first_name)) $errors[] = 'First Name is required.';
    if (empty($last_name)) $errors[] = 'Last Name is required.';
    if (empty($id_number)) $errors[] = 'ID Number is required.';
    if (empty($birthdate)) $errors[] = 'Birthdate is required.';
    
    // ID Number format
    if ($id_number && !preg_match('/^[0-9]{4}-[0-9]{4}$/', $id_number)) {
        $errors[] = 'ID Number must be in format xxxx-xxxx.';
    }
    
    // Age validation
    if ($birthdate) {
        $dob = new DateTime($birthdate);
        $today = new DateTime();
        $age = $today->diff($dob)->y;
        if ($age < 18) $errors[] = 'Only legal age (18+) allowed.';
    }
    
    // Name validation
    function name_valid($name, $field) {
        if (empty($name)) return true;
        if (preg_match('/[^a-zA-Z\s\-\']/', $name)) {
            return "$field contains invalid characters.";
        }
        if (preg_match('/[A-Z]{2,}/', $name) && strtoupper($name) === $name) {
            return "$field must not be ALL CAPS.";
        }
        if (strpos($name, '  ') !== false) {
            return "$field must not contain double spaces.";
        }
        if (preg_match('/(.)\1{2,}/i', $name)) {
            return "$field must not contain three (3) consecutive identical letters.";
        }
        $words = explode(' ', $name);
        foreach ($words as $w) {
            if ($w === '') continue;
            $first = mb_substr($w, 0, 1, 'UTF-8');
            $rest = mb_substr($w, 1, null, 'UTF-8');
            if ($first !== mb_strtoupper($first, 'UTF-8') || $rest !== mb_strtolower($rest, 'UTF-8')) {
                return "$field must be Capitalized properly. Example: Juan Carlo";
            }
        }
        return true;
    }
    
    // Validate names
    foreach (['First Name' => $first_name, 'Last Name' => $last_name, 'Extension' => $extension] as $k => $v) {
        if ($v === '') continue;
        $ok = name_valid($v, $k);
        if ($ok !== true) $errors[] = $ok;
    }
    
    return $errors;
}

// Helper function to validate step 2 (Address Information)
function validate_step_2($street, $barangay, $city, $province, $country, $zip) {
    $errors = [];
    
    // Required fields for step 2
    $required = [
        'Street/Purok' => $street,
        'Barangay' => $barangay,
        'City/Municipality' => $city,
        'Province' => $province,
        'Country' => $country,
        'ZIP Code' => $zip
    ];
    
    foreach ($required as $k => $v) {
        if (empty($v)) $errors[] = "$k is required.";
    }
    
    // ZIP Code validation
    if ($zip && !preg_match('/^[0-9]{4}$/', $zip)) {
        $errors[] = 'ZIP code must be 4 digits.';
    }
    
    // Address validation
    function address_valid($v, $field) {
        if (empty($v)) return "$field is required.";
        if (preg_match('/[^a-zA-Z0-9\s\-\.,\']/', $v)) return "$field contains invalid characters.";
        if (strpos($v, '  ') !== false) return "$field must not contain double spaces.";
        if (preg_match('/(.)\1{2,}/i', $v)) return "$field must not contain three (3) consecutive identical letters.";
        $first = mb_substr(trim($v), 0, 1, 'UTF-8');
        if ($first !== mb_strtoupper($first, 'UTF-8') || !preg_match('/[A-Z]/', $first)) return "$field must start with a capital letter.";
        return true;
    }
    
    $addrChecks = [
        'Street/Purok' => $street,
        'Barangay' => $barangay,
        'City/Municipality' => $city,
        'Province' => $province,
        'Country' => $country
    ];
    foreach ($addrChecks as $label => $val) {
        $ok = address_valid($val, $label);
        if ($ok !== true) $errors[] = $ok;
    }
    
    return $errors;
}

// Helper function to validate step 3 (Account Information)
function validate_step_3($username, $email, $password, $confirm_password) {
    $errors = [];
    
    if (empty($username)) $errors[] = 'Username is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    if (empty($password)) $errors[] = 'Password is required.';
    if (empty($confirm_password)) $errors[] = 'Confirm Password is required.';
    
    // Username validation
    if ($username && !preg_match('/^[A-Za-z0-9_.\-]{8,16}$/', $username)) {
        $errors[] = 'Invalid username. Use 8-16 chars: letters, numbers, underscore, dot, or dash.';
    }
    
    // Email validation
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    
    // Password validation
    if ($password && $password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    // Password length bounds
    if ($password && (strlen($password) < 8 || strlen($password) > 64)) {
        $errors[] = 'Password must be between 8 and 64 characters.';
    }
    
    // Password strength
    if ($password) {
        $pw_strength = 0;
        if (strlen($password) >= 8) $pw_strength++;
        if (preg_match('/[0-9]/', $password)) $pw_strength++;
        if (preg_match('/[A-Z]/', $password)) $pw_strength++;
        if (preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) $pw_strength++;
        if ($pw_strength < 2) $errors[] = 'Password too weak. Use at least 8 chars, include letters and numbers.';
    }
    
    return $errors;
}

// Helper function to validate step 4 (Security Questions)
function validate_step_4($auth_a1, $auth_a2, $auth_a3) {
    $errors = [];
    
    if (empty($auth_a1)) $errors[] = 'Security Answer 1 is required.';
    if (empty($auth_a2)) $errors[] = 'Security Answer 2 is required.';
    if (empty($auth_a3)) $errors[] = 'Security Answer 3 is required.';
    
    return $errors;
}

// STAGE 1: Validate current step being submitted
if ($current_step === 1) {
    $errors = validate_step_1($first_name, $middle_name, $last_name, $extension, $id_number, $birthdate);
} elseif ($current_step === 2) {
    $errors = validate_step_2($street, $barangay, $city, $province, $country, $zip);
} elseif ($current_step === 3) {
    $errors = validate_step_3($username, $email, $password, $confirm_password);
} elseif ($current_step === 4) {
    $errors = validate_step_4($auth_a1, $auth_a2, $auth_a3);
}

// STAGE 2: Enforce step progression - Steps 3 and 4 require Steps 1 and 2 to be completed
if (($current_step === 3 || $current_step === 4) && empty($errors)) {
    // When on step 3 or 4, must validate that steps 1 and 2 are complete
    $errors_step_1 = validate_step_1($first_name, $middle_name, $last_name, $extension, $id_number, $birthdate);
    $errors_step_2 = validate_step_2($street, $barangay, $city, $province, $country, $zip);
    
    // If there are errors in step 1, user must complete it first
    if (!empty($errors_step_1)) {
        $errors = array_merge($errors, ['ERROR_STEP_1' => 'Please complete Personal Information first. ' . implode(' ', $errors_step_1)]);
        $current_step = 1; // Force back to step 1
    } 
    // If there are errors in step 2, user must complete it before step 3/4
    elseif (!empty($errors_step_2)) {
        $errors = array_merge($errors, ['ERROR_STEP_2' => 'Please complete Address Information. ' . implode(' ', $errors_step_2)]);
        $current_step = 2; // Force back to step 2
    }
}

// STAGE 3: Check for duplicate username/email/id_number only when on step 3
if ($current_step >= 3 && empty($errors)) {
    if (!empty($username) || !empty($email) || !empty($id_number)) {
        $stmt = $conn->prepare('SELECT id_number FROM users WHERE id_number = ? OR email = ? OR username = ? LIMIT 1');
        $stmt->bind_param('sss', $id_number, $email, $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors[] = 'ID Number, Email or Username already registered.';
        }
        $stmt->close();
    }
}

// STAGE 4: Check for duplicate password only when on step 3
if ($current_step >= 3 && empty($errors) && !empty($password)) {
    $stmt = $conn->prepare('SELECT password FROM users');
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $passwordExists = false;
        
        while ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $passwordExists = true;
                break;
            }
        }
        
        if ($passwordExists) {
            $errors[] = 'Password already exists. Please choose a different password.';
        }
        $stmt->close();
    }
}

// Display errors and redirect to the appropriate step
if (!empty($errors)) {
    // Filter out internal error keys
    $display_errors = array_filter($errors, function($key) {
        return !in_array($key, ['ERROR_STEP_1', 'ERROR_STEP_2']);
    }, ARRAY_FILTER_USE_KEY);
    
    $_SESSION['registration_values'] = [
        'id_number' => $id_number,
        'first_name' => $first_name,
        'middle_name' => $middle_name,
        'last_name' => $last_name,
        'extension' => $extension,
        'birthdate' => $birthdate,
        'street' => $street,
        'barangay' => $barangay,
        'city' => $city,
        'province' => $province,
        'country' => $country,
        'zip' => $zip,
        'email' => $email,
        'username' => $username,
        'question1' => $question1,
        'question2' => $question2,
        'question3' => $question3,
        'auth_a1' => $auth_a1,
        'auth_a2' => $auth_a2,
        'auth_a3' => $auth_a3,
    ];
    
    // If we detected incomplete previous steps, force back to that step
    if (isset($errors['ERROR_STEP_1'])) {
        $_SESSION['registration_step'] = 1;
        $_SESSION['error'] = $errors['ERROR_STEP_1'];
    } elseif (isset($errors['ERROR_STEP_2'])) {
        $_SESSION['registration_step'] = 2;
        $_SESSION['error'] = $errors['ERROR_STEP_2'];
    } else {
        // Otherwise stay on current step
        $_SESSION['registration_step'] = $current_step;
        $_SESSION['error'] = implode(' ', $display_errors);
    }
    
    header('Location: sign.php');
    exit;
}

// All validation passed! Calculate age and prepare for database insertion
$age = null;
if ($birthdate) {
    $dob = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($dob)->y;
}

// Insert user into database
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$ha1 = password_hash($auth_a1, PASSWORD_DEFAULT);
$ha2 = password_hash($auth_a2, PASSWORD_DEFAULT);
$ha3 = password_hash($auth_a3, PASSWORD_DEFAULT);
$role = ROLE_USER;
$account_status = STATUS_PENDING;

// Use the selected questions (not hardcoded)
$aq1 = $question1;
$aq2 = $question2;
$aq3 = $question3;

$ins = $conn->prepare('INSERT INTO users (
    id_number, first_name, middle_name, last_name, extension, birthdate, age,
    street, barangay, city, province, country, zip,
    email, username, password, role, status, auth_q1, auth_a1, auth_q2, auth_a2, auth_q3, auth_a3
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

if (!$ins) {
    $_SESSION['registration_values'] = compact(
        'id_number', 'first_name', 'middle_name', 'last_name', 'extension', 'birthdate',
        'street', 'barangay', 'city', 'province', 'country', 'zip', 'email', 'username',
        'question1', 'question2', 'question3', 'auth_a1', 'auth_a2', 'auth_a3'
    );
    $_SESSION['registration_step'] = $current_step;
    $_SESSION['error'] = 'Database error while preparing registration. Please try again.';
    header('Location: sign.php');
    exit;
}

$ins->bind_param(
    'ssssssisssssssssssssssss',
    $id_number,
    $first_name,
    $middle_name,
    $last_name,
    $extension,
    $birthdate,
    $age,
    $street,
    $barangay,
    $city,
    $province,
    $country,
    $zip,
    $email,
    $username,
    $hashed_password,
    $role,
    $account_status,
    $aq1,
    $ha1,
    $aq2,
    $ha2,
    $aq3,
    $ha3
);

if ($ins->execute()) {
    unset($_SESSION['registration_values'], $_SESSION['registration_step']);
    $_SESSION['success'] = 'Registration submitted. An administrator must approve your account before you can log in.';
    header('Location: login.php');
} else {
    unset($_SESSION['registration_values'], $_SESSION['registration_step']);
    $_SESSION['error'] = 'Database error: ' . $conn->error;
    $_SESSION['registration_values'] = compact(
        'id_number', 'first_name', 'middle_name', 'last_name', 'extension', 'birthdate',
        'street', 'barangay', 'city', 'province', 'country', 'zip', 'email', 'username',
        'question1', 'question2', 'question3', 'auth_a1', 'auth_a2', 'auth_a3'
    );
    $_SESSION['registration_step'] = $current_step;
    header('Location: sign.php');
}

$ins->close();
$conn->close();
?>