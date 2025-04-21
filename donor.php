
<?php
require_once '../config.php';
requireLogin();

if ($_SESSION['role'] !== 'donor') {
    header("Location: ../unauthorized.html");
    exit;
}

$donorId = $_SESSION['donor_id'];

// Get donor information
$stmt = $conn->prepare("
    SELECT d.*, u.email 
    FROM donors d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.id = ?
");
$stmt->execute([$donorId]);
$donor = $stmt->fetch(PDO::FETCH_ASSOC);

// Get donation history
$stmt = $conn->prepare("
    SELECT d.*, h.name as hospital_name 
    FROM donations d 
    JOIN hospitals h ON d.hospital_id = h.id 
    WHERE d.donor_id = ? 
    ORDER BY d.donation_date DESC
");
$stmt->execute([$donorId]);
$donations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming donation events
$stmt = $conn->prepare("
    SELECT e.*, r.status as registration_status 
    FROM donation_events e 
    LEFT JOIN event_registrations r ON e.id = r.event_id AND r.donor_id = ? 
    WHERE e.event_date >= CURDATE() 
    ORDER BY e.event_date ASC 
    LIMIT 5
");
$stmt->execute([$donorId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get donor badges
$stmt = $conn->prepare("
    SELECT b.* 
    FROM donor_badges b 
    JOIN donor_badge_awards a ON b.id = a.badge_id 
    WHERE a.donor_id = ?
");
$stmt->execute([$donorId]);
$badges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate next eligible donation date
$nextEligibleDate = null;
if (!empty($donor['last_donation_date'])) {
    $lastDonationDate = new DateTime($donor['last_donation_date']);
    $nextEligibleDate = clone $lastDonationDate;
    $nextEligibleDate->add(new DateInterval('P56D')); // Add 56 days (8 weeks)
}

// Check if currently eligible
$isEligible = true;
$eligibilityMessage = "";

if ($nextEligibleDate !== null && $nextEligibleDate > new DateTime()) {
    $isEligible = false;
    $eligibilityMessage = "You will be eligible to donate again on " . $nextEligibleDate->format('F j, Y');
} else {
    $eligibilityMessage = "You are currently eligible to donate blood!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard - BloodLink</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/styles.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="../index.html"><span class="text-danger fw-bold">Blood</span><span class="text-success">Link</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.html">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../donate.html">Donate</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">Events</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <div class="dropdown">
                        <button class="btn btn-outline-danger dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($donor['first_name']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                            <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                            <li><a class="dropdown-item" href="donations.php">My Donations</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5">
        <!-- Welcome Banner -->
        <div class="card bg-danger text-white mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-3">Welcome back, <?php echo htmlspecialchars($donor['first_name']); ?>!</h2>
                        <p class="mb-0"><?php echo $eligibilityMessage; ?></p>
                    </div>
                    <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                        <?php if ($isEligible): ?>
                            <a href="donate.php" class="btn btn-light btn-lg">Donate Now</a>
                        <?php else: ?>
                            <button class="btn btn-light btn-lg" disabled>Donate Now</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card dashboard-stat h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Total Donations</h6>
                                <h3 class="card-title mb-0"><?php echo count($donations); ?></h3>
                            </div>
                            <div class="bg-danger bg-opacity-10 p-3 rounded">
                                <i class="fas fa-tint text-danger fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-stat h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Lives Impacted</h6>
                                <h3 class="card-title mb-0"><?php echo count($donations) * 3; ?></h3>
                            </div>
                            <div class="bg-danger bg-opacity-10 p-3 rounded">
                                <i class="fas fa-heart text-danger fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-stat h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Badges Earned</h6>
                                <h3 class="card-title mb-0"><?php echo count($badges); ?></h3>
                            </div>
                            <div class="bg-danger bg-opacity-10 p-3 rounded">
                                <i class="fas fa-award text-danger fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Donation History -->
            <div class="col-lg-8 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Donations</h5>
                        <a href="donations.php" class="btn btn-sm btn-outline-danger">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($donations) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Location</th>
                                            <th>Blood Type</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach(array_slice($donations, 0, 5) as $donation): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($donation['hospital_name']); ?></td>
                                                <td><?php echo htmlspecialchars($donation['blood_type']); ?></td>
                                                <td>
                                                    <?php if ($donation['status'] === 'completed'): ?>
                                                        <span class="badge bg-success">Completed</span>
                                                    <?php elseif ($donation['status'] === 'deferred'): ?>
                                                        <span class="badge bg-warning">Deferred</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Failed</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-tint text-muted fa-3x mb-3"></i>
                                <p>You haven't made any donations yet.</p>
                                <?php if ($isEligible): ?>
                                    <a href="donate.php" class="btn btn-danger">Make Your First Donation</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Events -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Upcoming Events</h5>
                        <a href="events.php" class="btn btn-sm btn-outline-danger">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($events) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($events as $event): ?>
                                    <div class="list-group-item border-0 px-0">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <h6 class="mb-2"><?php echo htmlspecialchars($event['title']); ?></h6>
                                            <?php if (!empty($event['registration_status'])): ?>
                                                <span class="badge bg-success">Registered</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-muted mb-1">
                                            <i class="fas fa-calendar-alt me-2"></i> <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                        </p>
                                        <p class="text-muted mb-1">
                                            <i class="fas fa-clock me-2"></i> <?php echo date('h:i A', strtotime($event['start_time'])); ?> - <?php echo date('h:i A', strtotime($event['end_time'])); ?>
                                        </p>
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars($event['location']); ?>
                                        </p>
                                        <?php if (empty($event['registration_status'])): ?>
                                            <a href="event_register.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-danger">Register</a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled>Registered</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-alt text-muted fa-3x mb-3"></i>
                                <p>No upcoming events at this time.</p>
                                <a href="events.php" class="btn btn-danger">Browse All Events</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Badges -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Your Badges</h5>
            </div>
            <div class="card-body">
                <?php if (count($badges) > 0): ?>
                    <div class="row g-4">
                        <?php foreach ($badges as $badge): ?>
                            <div class="col-md-3 col-sm-6">
                                <div class="text-center">
                                    <div class="mb-3">
                                        <img src="../<?php echo htmlspecialchars($badge['image_url']); ?>" alt="<?php echo htmlspecialchars($badge['name']); ?>" class="img-fluid" style="max-height: 80px;">
                                    </div>
                                    <h6><?php echo htmlspecialchars($badge['name']); ?></h6>
                                    <p class="text-muted small"><?php echo htmlspecialchars($badge['description']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-medal text-muted fa-3x mb-3"></i>
                        <p>You haven't earned any badges yet. Make a donation to start earning badges!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer py-5 bg-dark">
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <h5 class="text-white">BloodLink</h5>
                    <p class="text-light">Connecting donors and recipients for a healthier tomorrow.</p>
                </div>
                <div class="col-md-3 mb-4">
                    <h5 class="text-white">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="../index.html" class="text-light">Home</a></li>
                        <li><a href="../eligibility.html" class="text-light">Eligibility</a></li>
                        <li><a href="../donate.html" class="text-light">Donate</a></li>
                        <li><a href="../about.html" class="text-light">About Us</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5 class="text-white">For Hospitals</h5>
                    <ul class="list-unstyled">
                        <li><a href="../hospital-register.html" class="text-light">Register Hospital</a></li>
                        <li><a href="../hospital-login.html" class="text-light">Hospital Login</a></li>
                        <li><a href="../request-blood.html" class="text-light">Request Blood</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5 class="text-white">Contact</h5>
                    <ul class="list-unstyled text-light">
                        <li>Email: info@bloodlink.org</li>
                        <li>Phone: +1 (555) 123-4567</li>
                        <li>Emergency: +1 (555) 987-6543</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 bg-light">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="text-light">© 2025 BloodLink Rescue Network. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="text-light">Made with <i class="fas fa-heart text-danger"></i> for a better world</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../js/scripts.js"></script>
</body>
</html>
