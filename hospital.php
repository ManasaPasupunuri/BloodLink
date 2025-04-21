
<?php
require_once '../config.php';
requireLogin();
requireHospital();

$hospitalId = $_SESSION['hospital_id'];

// Fetch hospital info
$stmt = $conn->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmt->execute([$hospitalId]);
$hospital = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch blood inventory
$stmt = $conn->prepare("
    SELECT blood_type, SUM(units_available) as total_units
    FROM blood_inventory
    WHERE hospital_id = ? AND status = 'available' AND expiry_date >= CURDATE()
    GROUP BY blood_type
    ORDER BY blood_type
");
$stmt->execute([$hospitalId]);
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent blood requests
$stmt = $conn->prepare("
    SELECT * FROM blood_requests
    WHERE hospital_id = ?
    ORDER BY request_date DESC, priority DESC
    LIMIT 5
");
$stmt->execute([$hospitalId]);
$recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent donations
$stmt = $conn->prepare("
    SELECT d.*, CONCAT(dn.first_name, ' ', dn.last_name) as donor_name
    FROM donations d
    JOIN donors dn ON d.donor_id = dn.id
    WHERE d.hospital_id = ?
    ORDER BY d.donation_date DESC
    LIMIT 5
");
$stmt->execute([$hospitalId]);
$recentDonations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch upcoming donation events
$stmt = $conn->prepare("
    SELECT * FROM donation_events
    WHERE hospital_id = ? AND event_date >= CURDATE()
    ORDER BY event_date ASC
    LIMIT 3
");
$stmt->execute([$hospitalId]);
$upcomingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process blood inventory update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_inventory'])) {
    $bloodType = sanitizeInput($_POST['blood_type']);
    $units = (int)$_POST['units'];
    $expiryDate = sanitizeInput($_POST['expiry_date']);
    
    // Validate inputs
    if (empty($bloodType) || $units <= 0 || empty($expiryDate)) {
        $updateError = "All fields are required and units must be positive.";
    } else {
        try {
            // Check if expiry date is valid (not in the past)
            $expiryDateTime = new DateTime($expiryDate);
            $today = new DateTime();
            
            if ($expiryDateTime < $today) {
                $updateError = "Expiry date cannot be in the past.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO blood_inventory (hospital_id, blood_type, units_available, expiry_date, status)
                    VALUES (?, ?, ?, ?, 'available')
                ");
                $stmt->execute([$hospitalId, $bloodType, $units, $expiryDate]);
                
                // Log the inventory update
                $userId = $_SESSION['user_id'];
                $stmt = $conn->prepare("
                    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
                    VALUES (?, 'add_inventory', 'blood_inventory', ?, ?)
                ");
                $details = "Added $units units of $bloodType blood type with expiry date $expiryDate";
                $stmt->execute([$userId, $conn->lastInsertId(), $details]);
                
                $updateSuccess = "Blood inventory updated successfully.";
                
                // Refresh inventory data
                $stmt = $conn->prepare("
                    SELECT blood_type, SUM(units_available) as total_units
                    FROM blood_inventory
                    WHERE hospital_id = ? AND status = 'available' AND expiry_date >= CURDATE()
                    GROUP BY blood_type
                    ORDER BY blood_type
                ");
                $stmt->execute([$hospitalId]);
                $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $updateError = "Failed to update inventory: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Dashboard - BloodLink</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/styles.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="inventory.php">Inventory</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="requests.php">Blood Requests</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="donors.php">Donors</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">Events</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <div class="dropdown">
                        <button class="btn btn-outline-success dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-hospital me-1"></i> <?php echo htmlspecialchars($hospital['name']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php">Hospital Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid py-5">
        <div class="row">
            <div class="col-lg-3">
                <!-- Sidebar -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Hospital Dashboard</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="dashboard.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a href="inventory.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-vials me-2"></i> Blood Inventory
                        </a>
                        <a href="requests.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-clipboard-list me-2"></i> Blood Requests
                        </a>
                        <a href="donations.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-hand-holding-heart me-2"></i> Donations
                        </a>
                        <a href="donors.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-users me-2"></i> Donors
                        </a>
                        <a href="events.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-alt me-2"></i> Donation Events
                        </a>
                        <a href="reports.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                        <a href="../logout.php" class="list-group-item list-group-item-action text-danger">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-9">
                <?php if (isset($updateSuccess)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $updateSuccess; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($updateError)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $updateError; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Quick Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card dashboard-stat h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Total Blood Units</h6>
                                        <h3 class="card-title mb-0">
                                            <?php 
                                                $totalUnits = 0;
                                                foreach ($inventory as $item) {
                                                    $totalUnits += $item['total_units'];
                                                }
                                                echo $totalUnits;
                                            ?>
                                        </h3>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-vial text-success fa-2x"></i>
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
                                        <h6 class="card-subtitle mb-2 text-muted">Open Requests</h6>
                                        <h3 class="card-title mb-0">
                                            <?php
                                                $openRequests = 0;
                                                foreach ($recentRequests as $request) {
                                                    if ($request['status'] === 'pending' || $request['status'] === 'approved') {
                                                        $openRequests++;
                                                    }
                                                }
                                                echo $openRequests;
                                            ?>
                                        </h3>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-clipboard-list text-warning fa-2x"></i>
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
                                        <h6 class="card-subtitle mb-2 text-muted">Upcoming Events</h6>
                                        <h3 class="card-title mb-0"><?php echo count($upcomingEvents); ?></h3>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-calendar-alt text-primary fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Blood Inventory -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Blood Inventory</h5>
                                <a href="inventory.php" class="btn btn-sm btn-outline-success">Manage Inventory</a>
                            </div>
                            <div class="card-body">
                                <?php if (count($inventory) > 0): ?>
                                    <canvas id="bloodInventoryChart" height="250"></canvas>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-vial text-muted fa-3x mb-3"></i>
                                        <p>No blood units in inventory.</p>
                                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
                                            Add Inventory
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Add Inventory</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="dashboard.php">
                                    <div class="mb-3">
                                        <label for="blood_type" class="form-label">Blood Type</label>
                                        <select class="form-select" id="blood_type" name="blood_type" required>
                                            <option value="" selected disabled>Select Type</option>
                                            <option value="A+">A+</option>
                                            <option value="A-">A-</option>
                                            <option value="B+">B+</option>
                                            <option value="B-">B-</option>
                                            <option value="AB+">AB+</option>
                                            <option value="AB-">AB-</option>
                                            <option value="O+">O+</option>
                                            <option value="O-">O-</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="units" class="form-label">Units</label>
                                        <input type="number" class="form-control" id="units" name="units" min="1" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="expiry_date" class="form-label">Expiry Date</label>
                                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" required>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" name="update_inventory" class="btn btn-success">Add Units</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Requests and Donations -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-4 mb-lg-0">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Blood Requests</h5>
                                <a href="requests.php" class="btn btn-sm btn-outline-success">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (count($recentRequests) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Blood Type</th>
                                                    <th>Units</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentRequests as $request): ?>
                                                    <tr>
                                                        <td><?php echo date('M d', strtotime($request['request_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($request['blood_type']); ?></td>
                                                        <td><?php echo htmlspecialchars($request['units_required']); ?></td>
                                                        <td>
                                                            <?php if ($request['status'] === 'pending'): ?>
                                                                <span class="badge bg-warning">Pending</span>
                                                            <?php elseif ($request['status'] === 'approved'): ?>
                                                                <span class="badge bg-primary">Approved</span>
                                                            <?php elseif ($request['status'] === 'fulfilled'): ?>
                                                                <span class="badge bg-success">Fulfilled</span>
                                                            <?php elseif ($request['status'] === 'denied'): ?>
                                                                <span class="badge bg-danger">Denied</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Cancelled</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-clipboard-list text-muted fa-3x mb-3"></i>
                                        <p>No recent blood requests.</p>
                                        <a href="request-form.php" class="btn btn-success">Create Request</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Donations</h5>
                                <a href="donations.php" class="btn btn-sm btn-outline-success">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (count($recentDonations) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Donor</th>
                                                    <th>Blood Type</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentDonations as $donation): ?>
                                                    <tr>
                                                        <td><?php echo date('M d', strtotime($donation['donation_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($donation['donor_name']); ?></td>
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
                                        <i class="fas fa-hand-holding-heart text-muted fa-3x mb-3"></i>
                                        <p>No recent donations recorded.</p>
                                        <a href="record-donation.php" class="btn btn-success">Record Donation</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Upcoming Events -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Upcoming Donation Events</h5>
                        <a href="events.php" class="btn btn-sm btn-outline-success">Manage Events</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($upcomingEvents) > 0): ?>
                            <div class="row">
                                <?php foreach ($upcomingEvents as $event): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="card h-100 border-0 shadow-sm">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                                <p class="card-text text-muted">
                                                    <i class="fas fa-calendar-alt me-2"></i> <?php echo date('F j, Y', strtotime($event['event_date'])); ?><br>
                                                    <i class="fas fa-clock me-2"></i> <?php echo date('g:i A', strtotime($event['start_time'])); ?> - <?php echo date('g:i A', strtotime($event['end_time'])); ?><br>
                                                    <i class="fas fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars($event['location']); ?><br>
                                                    <i class="fas fa-user-friends me-2"></i> <?php echo $event['registrations_count']; ?> / <?php echo $event['capacity'] ?: 'Unlimited'; ?> Registered
                                                </p>
                                                <a href="event-detail.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-success">View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-alt text-muted fa-3x mb-3"></i>
                                <p>No upcoming donation events.</p>
                                <a href="create-event.php" class="btn btn-success">Create Event</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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
                        <li><a href="../donate.html" class="text-light">Donate</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5 class="text-white">Hospital Resources</h5>
                    <ul class="list-unstyled">
                        <li><a href="inventory.php" class="text-light">Inventory Management</a></li>
                        <li><a href="requests.php" class="text-light">Blood Requests</a></li>
                        <li><a href="donors.php" class="text-light">Donor Management</a></li>
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
    
    <script>
        // Blood inventory chart
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (count($inventory) > 0): ?>
                const ctx = document.getElementById('bloodInventoryChart').getContext('2d');
                
                const bloodTypes = <?php echo json_encode(array_column($inventory, 'blood_type')); ?>;
                const units = <?php echo json_encode(array_column($inventory, 'total_units')); ?>;
                
                // Threshold levels for coloring
                const threshold = {
                    'A+': 10, 'A-': 5, 'B+': 10, 'B-': 5, 
                    'AB+': 5, 'AB-': 2, 'O+': 15, 'O-': 5
                };
                
                // Set colors based on inventory levels
                const backgroundColors = units.map((count, i) => {
                    const type = bloodTypes[i];
                    return count <= threshold[type]/2 ? '#dc3545' : // critical
                           count <= threshold[type] ? '#ffc107' : // low
                           '#198754'; // normal
                });
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: bloodTypes,
                        datasets: [{
                            label: 'Available Units',
                            data: units,
                            backgroundColor: backgroundColors,
                            borderColor: backgroundColors,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Units'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Blood Type'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            <?php endif; ?>
            
            // Set min expiry date to today
            const expiryDateInput = document.getElementById('expiry_date');
            if (expiryDateInput) {
                const today = new Date();
                const yyyy = today.getFullYear();
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const dd = String(today.getDate()).padStart(2, '0');
                const todayStr = `${yyyy}-${mm}-${dd}`;
                expiryDateInput.min = todayStr;
            }
        });
    </script>
</body>
</html>
