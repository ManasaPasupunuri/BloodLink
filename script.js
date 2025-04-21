


// Wait for the document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Navbar scroll effect
    const navbar = document.querySelector('.navbar');
    
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('shadow-sm');
                navbar.classList.add('bg-white');
            } else {
                navbar.classList.remove('shadow-sm');
                navbar.classList.remove('bg-white');
            }
        });
    }
    
    // Form validation for all forms with class needs-validation
    const forms = document.querySelectorAll('.needs-validation');
    
    if (forms.length > 0) {
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }
    
    // Registration form handling
    const registrationForm = document.getElementById('registrationForm');
    
    if (registrationForm) {
        registrationForm.addEventListener('submit', function(event) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const passwordFeedback = document.getElementById('passwordFeedback');
            
            // Check if passwords match
            if (password !== confirmPassword) {
                event.preventDefault();
                passwordFeedback.textContent = 'Passwords do not match';
                document.getElementById('confirmPassword').classList.add('is-invalid');
            } else {
                passwordFeedback.textContent = '';
                document.getElementById('confirmPassword').classList.remove('is-invalid');
            }
            
            // Client-side age verification
            const dob = new Date(document.getElementById('dob').value);
            const today = new Date();
            const age = today.getFullYear() - dob.getFullYear();
            const dobFeedback = document.getElementById('dobFeedback');
            
            if (age < 18) {
                event.preventDefault();
                dobFeedback.textContent = 'You must be at least 18 years old to register';
                document.getElementById('dob').classList.add('is-invalid');
            } else {
                dobFeedback.textContent = '';
                document.getElementById('dob').classList.remove('is-invalid');
            }
        });
    }
    
    // Initialize popovers and tooltips
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    if (popoverTriggerList.length > 0) {
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    }
    
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    if (tooltipTriggerList.length > 0) {
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Show custom toast notifications
    function showNotification(message, type = 'info') {
        // Create the toast container if it doesn't exist
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }
        
        // Create toast element
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        
        // Toast content
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        // Add toast to container
        toastContainer.appendChild(toastEl);
        
        // Initialize and show toast
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
        
        // Remove toast after it's hidden
        toastEl.addEventListener('hidden.bs.toast', function() {
            toastEl.remove();
        });
    }
    
    // Export the notification function to make it globally available
    window.showNotification = showNotification;
    
    // Handle URL parameters for notifications (e.g., after form submission)
    const urlParams = new URLSearchParams(window.location.search);
    const successMessage = urlParams.get('success');
    const errorMessage = urlParams.get('error');
    
    if (successMessage) {
        showNotification(decodeURIComponent(successMessage), 'success');
    }
    
    if (errorMessage) {
        showNotification(decodeURIComponent(errorMessage), 'danger');
    }
    
    // Blood inventory chart (for hospital dashboard)
    const bloodChart = document.getElementById('bloodInventoryChart');
    if (bloodChart) {
        initBloodInventoryChart();
    }
});

// Blood inventory chart function
function initBloodInventoryChart() {
    const ctx = document.getElementById('bloodInventoryChart').getContext('2d');
    
    // This data would normally come from the server
    const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    const inventory = [24, 8, 6, 3, 12, 2, 18, 5];
    const threshold = [10, 5, 10, 5, 5, 2, 15, 5];
    
    // Set colors based on inventory levels
    const backgroundColors = inventory.map((count, i) => {
        return count <= threshold[i]/2 ? '#dc3545' :  // Critical (red)
               count <= threshold[i] ? '#ffc107' :    // Low (yellow)
               '#198754';                            // Normal (green)
    });
    
    // Create chart using Chart.js (make sure Chart.js is included in your page)
    if (typeof Chart !== 'undefined') {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: bloodTypes,
                datasets: [{
                    label: 'Available Units',
                    data: inventory,
                    backgroundColor: backgroundColors,
                    borderColor: backgroundColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
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
    }
}

// Donor eligibility checker
function checkEligibility() {
    const age = document.getElementById('eligibility-age').value;
    const weight = document.getElementById('eligibility-weight').value;
    const lastDonation = document.getElementById('eligibility-lastDonation').value;
    const medicalConditions = Array.from(document.querySelectorAll('input[name="medicalCondition"]:checked')).map(el => el.value);
    
    let isEligible = true;
    const reasons = [];
    
    // Check age
    if (age < 18) {
        isEligible = false;
        reasons.push("You must be at least 18 years old to donate blood.");
    }
    
    // Check weight
    if (weight < 50) {
        isEligible = false;
        reasons.push("You must weigh at least 50kg (110lbs) to donate blood.");
    }
    
    // Check last donation (if provided)
    if (lastDonation) {
        const lastDonationDate = new Date(lastDonation);
        const today = new Date();
        const diffTime = Math.abs(today - lastDonationDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays < 56) { // 8 weeks = 56 days
            isEligible = false;
            reasons.push("You must wait at least 56 days (8 weeks) between whole blood donations.");
        }
    }
    
    // Check medical conditions
    if (medicalConditions.length > 0) {
        isEligible = false;
        reasons.push("You have selected one or more medical conditions that may affect your eligibility.");
    }
    
    // Display result
    const resultContainer = document.getElementById('eligibility-result');
    resultContainer.innerHTML = '';
    
    if (isEligible) {
        resultContainer.innerHTML = `
            <div class="alert alert-success">
                <h5><i class="fas fa-check-circle me-2"></i> You appear to be eligible to donate blood!</h5>
                <p>Please note that this is just a preliminary check. Final eligibility will be determined during your in-person screening.</p>
                <a href="register.html" class="btn btn-success mt-2">Register to Donate</a>
            </div>
        `;
    } else {
        let reasonsList = '<ul class="mb-0">';
        reasons.forEach(reason => {
            reasonsList += `<li>${reason}</li>`;
        });
        reasonsList += '</ul>';
        
        resultContainer.innerHTML = `
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle me-2"></i> You may not be eligible to donate at this time</h5>
                ${reasonsList}
                <p class="mt-2">For more information, please contact our donor support team or consult with your healthcare provider.</p>
            </div>
        `;
    }
    
    resultContainer.scrollIntoView({ behavior: 'smooth' });
}
