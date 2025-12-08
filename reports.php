<?php
require_once 'config.php';

// Set page variables for header
$pageTitle = 'SVP Reports';
$headerTitle = 'SVP Reports - St. Vincents, Main Street, Letterkenny';
$additionalCSS = '
<style>
.metric-card {
    transition: transform 0.2s;
}
.metric-card:hover {
    transform: translateY(-2px);
}
.section-header {
    border-bottom: 2px solid #dee2e6;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
</style>';

include 'header.php';
?>

<div class="container-fluid my-4">
    <!-- Back Button -->
    <div class="row mb-4">
        <div class="col-12">
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Page Title -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-3">Reporting and Mailing Functions</h2>
            <p class="text-muted">Configure notifications and generate various reports</p>
        </div>
    </div>

    <!-- Configuration Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="section-header">Configuration</h4>
        </div>
    </div>

    <div class="row mb-5 g-3">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm metric-card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-bell-fill text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title">Notifications</h5>
                    <p class="card-text">Add / Modify email recipients</p>
                    <a href="email_users.php" class="btn btn-warning btn-lg">Configure</a>
                    <small class="d-block text-muted mt-2">Automated email reporting</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Ad-Hoc Reports Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="section-header">Ad-Hoc Reports</h4>
        </div>
    </div>

    <div class="row mb-5 g-3">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm metric-card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-database-fill text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title">MySQL Reports</h5>
                    <p class="card-text">Generate reports from MySQL database records</p>
                    <a href="mysql_reports.php" class="btn btn-success btn-lg">Reconciliation History</a>
                    <small class="d-block text-muted mt-2">Daily cash reconciliation records</small>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 shadow-sm metric-card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-bar-chart-fill text-info" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title mb-3">MSSQL Sales Analysis</h5>
                    <p class="card-text">Item-based sales report from MSSQL database</p>
                    <a href="mssql_reports.php" class="btn btn-info btn-lg">Sales Analysis</a>
                    <small class="d-block text-muted mt-2">Detailed item sales breakdown</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Reports Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="section-header">Daily Reports</h4>
        </div>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm metric-card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-file-earmark-text-fill text-secondary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title">EOD Summary</h5>
                    <p class="card-text">End of day summary report for head office</p>
                    <a href="testsend.php" class="btn btn-secondary btn-lg">EOD Summary</a>
                    <small class="d-block text-muted mt-2">HO Data</small>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 shadow-sm metric-card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-file-earmark-text-fill text-secondary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title">Weekly Summary</h5>
                    <p class="card-text">Weekly Transaction summary report for head office. (White Book Helper)</p>
                    <a href="weekly_report.php" class="btn btn-secondary btn-lg">Weekly Summary</a>
                    <small class="d-block text-muted mt-2">White Book</small>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>
