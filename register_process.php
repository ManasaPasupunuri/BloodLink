<?php
// Include database configuration
require_once 'config.php';

// Initialize variables
$errors = [];
$success = false;

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate inputs
    $firstName = sanitizeInput($_POST['firstName'] ?? '');
    $lastName = sanitizeInput($_POST['lastName'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $bloodType = sanitizeInput($_POST['bloodType'] ?? '');
    $dob = sanitizeInput($_POST['dob'] ?? '');
    $weight = sanitizeInput($_POST['weight'] ?? '');
    $lastDonation = sanitizeInput($_POST['lastDonation'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $zipCode = sanitizeInput($_POST['zipCode'] ?? '');
    $emailUpdates = isset($_POST['emailUpdates']) ? 1 : 0;
    
    // Validate required fields
    if (empty($firstName)) $errors[] = "First name is required";
    if (empty($lastName)) $errors[] = "Last name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (empty($password)) $errors[] = "Password is required";
    if ($password !== $confirmPassword) $errors[] = "Passwords do not match";
    if (empty($bloodType)) $errors[] = "Blood type is required";
    if (empty($dob)) $errors[] = "Date of birth is required";
    if (empty($weight)) $errors[] = "Weight is required";
    if (empty($address)) $errors[] = "Address is required";
    if (empty($city)) $errors[] = "City is required";
    if (empty($zipCode)) $errors[] = "Zip code is required";
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Email already registered";
    }
    
    // Check age eligibility (must be 18+)
    $dobDate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($dobDate)->y;
    if ($age < 18) {
        $errors[] = "You must be at least 18 years old to register";
    }
    
    // Check weight eligibility (minimum 50kg/110lbs)
    if ($weight < 50) {
        $errors[] = "Minimum weight requirement is 50kg/110lbs";
    }
    
    // Process if no errors
    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into users table
            $stmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'donor')");
            $stmt->execute([$email, $hashedPassword]);
            $userId = $conn->lastInsertId();
            
            // Insert into donors table
            $stmt = $conn->prepare("
                INSERT INTO donors 
                (user_id, first_name, last_name, email, phone_number, blood_type, date_of_birth, 
                weight, last_donation_date, address, city, postal_code, email_updates) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId, $firstName, $lastName, $email, $phone, 
                $bloodType, $dob, $weight, $lastDonation ?: null, 
                $address, $city, $zipCode, $emailUpdates
            ]);
            
            // Commit transaction
            $conn->commit();
            
            // Set success flag
            $success = true;
            
            // Send confirmation email
            $to = $email;
            $subject = "Welcome to BloodLink";
            $message = "Dear $firstName,\n\nThank you for registering with BloodLink! Your account has been created successfully.\n\nRegards,\nThe BloodLink Team";
            $headers = "From: noreply@bloodlink.org";
            
            mail($to, $subject, $message, $headers);
            
            // Redirect to login page with success message
            $successMessage = urlencode("Registration successful! You can now login with your credentials.");
            header("Location: login.html?success=$successMessage");
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $errorMsg = urlencode("Registration failed: " . $e->getMessage());
            header("Location: register.html?error=$errorMsg");
            exit;
        }
    } else {
        // Redirect back with error messages
        $errorMsg = urlencode(implode(", ", $errors));
        header("Location: register.html?error=$errorMsg");
        exit;
    }
}
?>
