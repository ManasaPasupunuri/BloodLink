<?php
// Include database configuration
require_once 'config.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['rememberMe']);
    
    // Validate required fields
    if (empty($email) || empty($password)) {
        $errorMsg = urlencode("Email and password are required");
        header("Location: login.html?error=$errorMsg");
        exit;
    }
    
    try {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, email, password, role, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check if account is active
                if ($user['status'] === 'inactive') {
                    $errorMsg = urlencode("Your account is inactive. Please contact support.");
                    header("Location: login.html?error=$errorMsg");
                    exit;
                }
                
                if ($user['status'] === 'suspended') {
                    $errorMsg = urlencode("Your account has been suspended. Please contact support.");
                    header("Location: login.html?error=$errorMsg");
                    exit;
                }
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Set CSRF token
                $_SESSION['csrf_token'] = generateToken();
                
                // If remember me is checked, set a longer cookie
                if ($rememberMe) {
                    $token = bin2hex(random_bytes(64));
                    $expires = time() + (30 * 24 * 60 * 60); // 30 days
                    
                    // Store token in database
                    $stmt = $conn->prepare("INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))");
                    $stmt->execute([$user['id'], $token, $expires]);
                    
                    // Set cookie
                    setcookie('remember_token', $token, $expires, '/', '', true, true);
                }
                
                // Log login activity
                $ip = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                
                $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, ip_address, user_agent) VALUES (?, 'login', 'users', ?, ?)");
                $stmt->execute([$user['id'], $ip, $userAgent]);
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'hospital':
                        // Get hospital ID
                        $stmt = $conn->prepare("SELECT id FROM hospitals WHERE user_id = ?");
                        $stmt->execute([$user['id']]);
                        $hospital = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($hospital) {
                            $_SESSION['hospital_id'] = $hospital['id'];
                        }
                        
                        header("Location: hospital/dashboard.php");
                        break;
                    case 'donor':
                        // Get donor ID
                        $stmt = $conn->prepare("SELECT id FROM donors WHERE user_id = ?");
                        $stmt->execute([$user['id']]);
                        $donor = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($donor) {
                            $_SESSION['donor_id'] = $donor['id'];
                        }
                        
                        header("Location: donor/dashboard.php");
                        break;
                    default:
                        header("Location: index.html");
                }
                exit;
            } else {
                // Invalid password
                $errorMsg = urlencode("Invalid email or password");
                header("Location: login.html?error=$errorMsg");
                exit;
            }
        } else {
            // User not found
            $errorMsg = urlencode("Invalid email or password");
            header("Location: login.html?error=$errorMsg");
            exit;
        }
    } catch (PDOException $e) {
        $errorMsg = urlencode("Login failed: " . $e->getMessage());
        header("Location: login.html?error=$errorMsg");
        exit;
    }
}

// Redirect if accessed directly
header("Location: login.html");
exit;
?>