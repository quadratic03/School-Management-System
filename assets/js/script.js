/**
 * Custom JavaScript for School Management System
 */

document.addEventListener('DOMContentLoaded', function() {
    // Debug elements
    console.log("Sidebar exists:", !!document.getElementById('sidebar'));
    console.log("Sidebar toggle button exists:", !!document.getElementById('sidebarToggleBtn'));
    
    // Quick Actions buttons
    const quickAction1 = document.getElementById('quickAction1');
    const quickAction2 = document.getElementById('quickAction2');
    const quickAction3 = document.getElementById('quickAction3');
    const quickSettings = document.getElementById('quickSettings');
    
    // Quick Action 1 handler
    if (quickAction1) {
        quickAction1.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Quick Action 1: This feature is coming soon!');
        });
    }
    
    // Quick Action 2 handler
    if (quickAction2) {
        quickAction2.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Quick Action 2: This feature is coming soon!');
        });
    }
    
    // Quick Action 3 handler
    if (quickAction3) {
        quickAction3.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Quick Action 3: This feature is coming soon!');
        });
    }
    
    // Quick Settings handler
    if (quickSettings) {
        quickSettings.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Settings: This feature is coming soon!');
        });
    }
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl)
    });
    
    // Toggle sidebar - FIXED VERSION
    var sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    var sidebar = document.getElementById('sidebar');
    var sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (sidebarToggleBtn && sidebar) {
        console.log("Toggle button and sidebar found"); // Debug log
        
        sidebarToggleBtn.addEventListener('click', function(e) {
            console.log("Toggle button clicked"); // Debug log
            e.preventDefault(); // Prevent default behavior
            
            // Check if we're on mobile or desktop view
            if (window.innerWidth >= 992) {
                // Desktop - toggle collapsed class on body
                document.body.classList.toggle('sidebar-collapsed');
                console.log("Desktop view - toggled sidebar-collapsed");
            } else {
                // Mobile - toggle show class on sidebar and overlay
                sidebar.classList.toggle('show');
                console.log("Mobile view - toggled show class");
                if (sidebarOverlay) {
                    sidebarOverlay.classList.toggle('show');
                }
            }
        });
    } else {
        console.log("Toggle button or sidebar not found"); // Debug log
        console.log("sidebarToggleBtn:", sidebarToggleBtn);
        console.log("sidebar:", sidebar);
    }
    
    // Close sidebar when clicking the overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            // Desktop view
            if (sidebar && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
            if (sidebarOverlay && sidebarOverlay.classList.contains('show')) {
                sidebarOverlay.classList.remove('show');
            }
        }
    });
    
    // Add active class to nav links based on current URL
    var currentLocation = window.location.pathname;
    var navLinks = document.querySelectorAll('.sidebar .nav-link');
    
    navLinks.forEach(function(link) {
        if (link.getAttribute('href') === currentLocation || 
            currentLocation.indexOf(link.getAttribute('href')) !== -1) {
            link.classList.add('active');
        }
    });
    
    // Ensure all links in sidebar are working
    document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            // If on mobile, close sidebar on navigation
            if (window.innerWidth < 992) {
                if (sidebar) {
                    sidebar.classList.remove('show');
                }
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('show');
                }
            }
        });
    });
    
    // Handle form submission with validation
    var forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Confirm actions with a confirmation dialog
    document.querySelectorAll('[data-confirm]').forEach(function(element) {
        element.addEventListener('click', function(event) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });
    
    // Handle file input display
    document.querySelectorAll('.custom-file-input').forEach(function(input) {
        input.addEventListener('change', function() {
            var fileName = this.files[0].name;
            var label = this.nextElementSibling;
            label.textContent = fileName;
        });
    });
    
    // Initialize charts if they exist on the page
    initializeCharts();
    
    // Initialize data tables if they exist
    initializeDataTables();
});

/**
 * Initialize Charts
 */
function initializeCharts() {
    // Student attendance chart
    var attendanceChartEl = document.getElementById('attendanceChart');
    if (attendanceChartEl) {
        var attendanceChart = new Chart(attendanceChartEl, {
            type: 'pie',
            data: {
                labels: ['Present', 'Absent', 'Late', 'Excused'],
                datasets: [{
                    data: [85, 5, 8, 2],
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Academic performance chart
    var performanceChartEl = document.getElementById('performanceChart');
    if (performanceChartEl) {
        var performanceChart = new Chart(performanceChartEl, {
            type: 'bar',
            data: {
                labels: ['Mathematics', 'Science', 'English', 'History', 'Geography', 'Art'],
                datasets: [{
                    label: 'Current Grade',
                    data: [85, 72, 90, 78, 88, 95],
                    backgroundColor: '#007bff'
                }, {
                    label: 'Class Average',
                    data: [75, 68, 82, 74, 80, 85],
                    backgroundColor: '#6c757d'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }
    
    // Enrollment trend chart
    var enrollmentChartEl = document.getElementById('enrollmentChart');
    if (enrollmentChartEl) {
        var enrollmentChart = new Chart(enrollmentChartEl, {
            type: 'line',
            data: {
                labels: ['2018', '2019', '2020', '2021', '2022', '2023'],
                datasets: [{
                    label: 'Total Students',
                    data: [750, 820, 900, 850, 950, 1020],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

/**
 * Initialize Data Tables
 */
function initializeDataTables() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('.datatable').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search..."
            }
        });
    }
}

/**
 * Format date
 * 
 * @param {Date|string} date Date to format
 * @param {string} format Date format
 * @return {string} Formatted date
 */
function formatDate(date, format = 'YYYY-MM-DD') {
    const d = new Date(date);
    
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const seconds = String(d.getSeconds()).padStart(2, '0');
    
    return format
        .replace('YYYY', year)
        .replace('MM', month)
        .replace('DD', day)
        .replace('HH', hours)
        .replace('mm', minutes)
        .replace('ss', seconds);
}

/**
 * Show notification
 * 
 * @param {string} message Message to display
 * @param {string} type Notification type (success, error, warning, info)
 * @param {number} duration Duration in milliseconds
 */
function showNotification(message, type = 'info', duration = 5000) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show notification`;
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Add to notifications container (create if it doesn't exist)
    let container = document.querySelector('.notifications-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'notifications-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    
    // Add notification to container
    container.appendChild(notification);
    
    // Initialize Bootstrap alert
    const bootstrapAlert = new bootstrap.Alert(notification);
    
    // Auto-dismiss after duration
    if (duration > 0) {
        setTimeout(() => {
            bootstrapAlert.close();
        }, duration);
    }
    
    // Remove from DOM after hidden
    notification.addEventListener('hidden.bs.alert', function() {
        notification.remove();
    });
}

/**
 * Print element
 * 
 * @param {string} elementId ID of element to print
 * @param {string} title Page title for print
 */
function printElement(elementId, title = '') {
    const element = document.getElementById(elementId);
    
    if (!element) {
        console.error(`Element with ID "${elementId}" not found.`);
        return;
    }
    
    // Create print window
    const printWindow = window.open('', '_blank', 'height=600,width=800');
    
    // Create print content
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${title || document.title}</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { padding: 20px; }
                @media print {
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="d-flex justify-content-between mb-4">
                <h1>${title || document.title}</h1>
                <button class="btn btn-primary no-print" onclick="window.print()">Print</button>
            </div>
            <div>${element.innerHTML}</div>
            <div class="text-center mt-4">
                <p class="text-muted">${new Date().toLocaleString()}</p>
            </div>
        </body>
        </html>
    `);
    
    // Focus and trigger print
    printWindow.document.close();
    printWindow.focus();
} 