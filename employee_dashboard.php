<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM employees WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

if (!$employee) {
    die("No employee profile found for this account.");
}

$employee_id = $employee['employee_id'];

$stmt = $conn->prepare("SELECT * FROM employers WHERE employer_id = ?");
$stmt->bind_param("i", $employee['employer_id']);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();

function status_badge($status) {
    return "<span class='status " . htmlspecialchars($status) . "'>" . ucfirst(htmlspecialchars($status)) . "</span>";
}

// Same late-payment penalty rule used across the system
function calculate_penalty($month, $year, $total_contribution, $status) {
    if ($status != 'overdue') {
        return 0;
    }
    $due_timestamp = mktime(0, 0, 0, $month + 1, 15, $year);
    $today_timestamp = strtotime(date('Y-m-d'));
    if ($today_timestamp <= $due_timestamp) {
        return 0;
    }
    $months_late = floor(($today_timestamp - $due_timestamp) / (30 * 24 * 60 * 60)) + 1;
    return round($total_contribution * 0.05 * $months_late, 2);
}

// ---- This employee's contributions ----
$stmt = $conn->prepare("SELECT * FROM contributions WHERE employee_id = ? ORDER BY contribution_year DESC, contribution_month DESC");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$contributions_result = $stmt->get_result();
$total_records = $contributions_result->num_rows;

// ---- Summary stats ----
$stmt = $conn->prepare("SELECT * FROM contributions WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$all_contributions = $stmt->get_result();

$count_paid = 0; $count_pending = 0; $count_overdue = 0;
$total_saved = 0; $total_penalties_owed = 0;
while ($row = $all_contributions->fetch_assoc()) {
    if ($row['status'] == 'paid') {
        $count_paid++;
        $total_saved += $row['total_contribution'];
    } elseif ($row['status'] == 'pending') {
        $count_pending++;
    } elseif ($row['status'] == 'overdue') {
        $count_overdue++;
        $total_penalties_owed += calculate_penalty($row['contribution_month'], $row['contribution_year'], $row['total_contribution'], $row['status']);
    }
}
$compliance_rate = $total_records > 0 ? round(($count_paid / $total_records) * 100) : 0;

$active_panel = isset($_GET['panel']) ? $_GET['panel'] : 'overview';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Employee Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="topbar">
        <span class="brand"><button class="hamburger" onclick="toggleSidebar()">&#9776;</button>NSSF &middot; Employee</span>
        <span>Welcome, <?php echo htmlspecialchars($employee['first_name']); ?> &nbsp;|&nbsp; <a href="logout.php">Logout</a></span>
    </div>

    <div class="layout">
        <div class="sidebar open" id="sidebar">
            <div class="sidebar-title">Menu</div>
            <div class="sidebar-nav">
                <a href="#" data-panel="overview" class="nav-link">Overview</a>
                <a href="#" data-panel="contributions" class="nav-link">My Contributions <span class="badge"><?php echo $total_records; ?></span></a>
                <a href="#" data-panel="profile" class="nav-link">My Profile</a>
            </div>
        </div>

        <div class="main-content">

            <!-- ===== OVERVIEW PANEL ===== -->
            <div class="panel" id="panel-overview">
                <div class="welcome-banner">
                    <div>
                        <h2><?php echo htmlspecialchars($employee['first_name'] . " " . $employee['last_name']); ?></h2>
                        <p>NSSF No. <?php echo htmlspecialchars($employee['nssf_number']); ?> &nbsp;&middot;&nbsp; <?php echo htmlspecialchars($employer['company_name']); ?></p>
                    </div>
                    <div class="welcome-date"><?php echo date('l, d F Y'); ?></div>
                </div>

                <div class="section-label">At a Glance</div>
                <div class="stats-grid">
                    <div class="stat-card stat-good">
                        <span class="stat-icon">✅</span>
                        <div class="stat-value"><?php echo $count_paid; ?></div>
                        <div class="stat-label">Paid</div>
                    </div>
                    <div class="stat-card stat-warn">
                        <span class="stat-icon">⏳</span>
                        <div class="stat-value"><?php echo $count_pending; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-card stat-bad">
                        <span class="stat-icon">⚠️</span>
                        <div class="stat-value"><?php echo $count_overdue; ?></div>
                        <div class="stat-label">Overdue</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">💰</span>
                        <div class="stat-value">UGX <?php echo number_format($total_saved, 0); ?></div>
                        <div class="stat-label">Total Saved</div>
                    </div>
                    <div class="stat-card <?php echo $total_penalties_owed > 0 ? 'stat-bad' : ''; ?>">
                        <span class="stat-icon">🔺</span>
                        <div class="stat-value">UGX <?php echo number_format($total_penalties_owed, 0); ?></div>
                        <div class="stat-label">Penalties Owed</div>
                    </div>
                </div>

                <div class="compliance-block">
                    <h4>My Compliance Rate</h4>
                    <div class="progress-track">
                        <div class="progress-fill <?php echo $compliance_rate >= 80 ? 'good' : ($compliance_rate >= 50 ? 'warn' : 'bad'); ?>" style="width: <?php echo $compliance_rate; ?>%;"></div>
                    </div>
                    <div class="progress-caption">
                        <span><?php echo $count_paid; ?> of <?php echo $total_records; ?> contributions paid</span>
                        <span><strong><?php echo $compliance_rate; ?>%</strong></span>
                    </div>
                </div>
            </div>

            <!-- ===== CONTRIBUTIONS PANEL ===== -->
            <div class="panel" id="panel-contributions">
                <h2>My Contribution History</h2>
                <div class="card">
                    <table>
                        <tr>
                            <th>Month</th><th>Year</th><th>Gross Salary</th><th>My Contribution</th><th>Employer's Contribution</th><th>Total</th><th>Date Paid</th><th>Status</th><th>Penalty</th><th>Receipt</th>
                        </tr>
                        <?php
                        if ($total_records == 0) {
                            echo "<tr><td colspan='10' style='text-align:center; color:#5B6B62;'>No contributions recorded yet.</td></tr>";
                        }
                        while ($row = $contributions_result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $row['contribution_month'] . "</td>";
                            echo "<td>" . $row['contribution_year'] . "</td>";
                            echo "<td>" . number_format($row['gross_salary'], 2) . "</td>";
                            echo "<td>" . number_format($row['employee_contribution'], 2) . "</td>";
                            echo "<td>" . number_format($row['employer_contribution'], 2) . "</td>";
                            echo "<td>" . number_format($row['total_contribution'], 2) . "</td>";
                            echo "<td>" . ($row['date_paid'] ? $row['date_paid'] : "-") . "</td>";
                            echo "<td>" . status_badge($row['status']) . "</td>";
                            $penalty = calculate_penalty($row['contribution_month'], $row['contribution_year'], $row['total_contribution'], $row['status']);
                            if ($penalty > 0) {
                                echo "<td style='color:#B3261E; font-weight:600;'>UGX " . number_format($penalty, 2) . "</td>";
                            } else {
                                echo "<td>&mdash;</td>";
                            }
                            if (!empty($row['proof_file'])) {
                                echo "<td><a href='uploads/receipts/" . htmlspecialchars($row['proof_file']) . "' target='_blank'>View</a></td>";
                            } else {
                                echo "<td>&mdash;</td>";
                            }
                            echo "</tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>

            <!-- ===== PROFILE PANEL ===== -->
            <div class="panel" id="panel-profile">
                <h2>My Profile</h2>
                <div class="card">
                    <p><strong>Full Name:</strong> <?php echo htmlspecialchars($employee['first_name'] . " " . $employee['last_name']); ?></p>
                    <p><strong>NSSF Number:</strong> <?php echo htmlspecialchars($employee['nssf_number']); ?></p>
                    <p><strong>National ID:</strong> <?php echo htmlspecialchars($employee['national_id']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($employee['phone']); ?></p>
                    <p><strong>Date Joined:</strong> <?php echo $employee['date_joined']; ?></p>
                    <p><strong>Employer:</strong> <?php echo htmlspecialchars($employer['company_name']); ?> (Reg. No. <?php echo htmlspecialchars($employer['registration_number']); ?>)</p>
                </div>
            </div>

        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }
        function showPanel(name) {
            document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.nav-link').forEach(a => a.classList.remove('active'));
            const panel = document.getElementById('panel-' + name);
            if (panel) panel.classList.add('active');
            const link = document.querySelector('.nav-link[data-panel="' + name + '"]');
            if (link) link.classList.add('active');
        }
        document.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                showPanel(this.dataset.panel);
            });
        });
        showPanel("<?php echo htmlspecialchars($active_panel); ?>");
    </script>
</body>
</html>