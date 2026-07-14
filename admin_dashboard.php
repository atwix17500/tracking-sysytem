<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

function status_badge($status) {
    return "<span class='status " . htmlspecialchars($status) . "'>" . ucfirst(htmlspecialchars($status)) . "</span>";
}

// Same late-payment penalty rule used across the system (5% per month late, due by the 15th of the following month)
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

// ---- System-wide compliance stats ----
$total_employers = $conn->query("SELECT COUNT(*) AS c FROM employers")->fetch_assoc()['c'];
$total_employees_all = $conn->query("SELECT COUNT(*) AS c FROM employees")->fetch_assoc()['c'];

$all_contributions = $conn->query("SELECT * FROM contributions");
$count_paid = 0; $count_pending = 0; $count_overdue = 0;
$total_collected = 0; $total_penalties_owed = 0;
while ($row = $all_contributions->fetch_assoc()) {
    if ($row['status'] == 'paid') {
        $count_paid++;
        $total_collected += $row['total_contribution'];
    } elseif ($row['status'] == 'pending') {
        $count_pending++;
    } elseif ($row['status'] == 'overdue') {
        $count_overdue++;
        $total_penalties_owed += calculate_penalty($row['contribution_month'], $row['contribution_year'], $row['total_contribution'], $row['status']);
    }
}
$total_records = $count_paid + $count_pending + $count_overdue;
$compliance_rate = $total_records > 0 ? round(($count_paid / $total_records) * 100) : 0;

$active_panel = isset($_GET['panel']) ? $_GET['panel'] : 'overview';
if (isset($_GET['added']) || isset($_GET['updated']) || isset($_GET['deleted'])) {
    $active_panel = 'employers';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="topbar">
        <span class="brand"><button class="hamburger" onclick="toggleSidebar()">&#9776;</button>NSSF &middot; Admin</span>
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> &nbsp;|&nbsp; <a href="logout.php">Logout</a></span>
    </div>

    <div class="layout">
        <div class="sidebar open" id="sidebar">
            <div class="sidebar-title">Menu</div>
            <div class="sidebar-nav">
                <a href="#" data-panel="overview" class="nav-link">Overview</a>
                <a href="#" data-panel="employers" class="nav-link">Employers <span class="badge"><?php echo $total_employers; ?></span></a>
                <a href="#" data-panel="employees" class="nav-link">Employees <span class="badge"><?php echo $total_employees_all; ?></span></a>
                <a href="#" data-panel="contributions" class="nav-link">Contributions <span class="badge"><?php echo $total_records; ?></span></a>
                <a href="#" data-panel="activity" class="nav-link">Activity Log</a>
            </div>
        </div>

        <div class="main-content">
            <?php if (isset($_GET['added'])) { ?>
                <p class="message success">Employer added successfully.</p>
            <?php } ?>
            <?php if (isset($_GET['updated'])) { ?>
                <p class="message success">Employer updated successfully.</p>
            <?php } ?>
            <?php if (isset($_GET['deleted'])) { ?>
                <p class="message success">Employer deleted successfully (their employees and contribution records were removed too).</p>
            <?php } ?>

            <!-- ===== OVERVIEW PANEL ===== -->
            <div class="panel" id="panel-overview">
                <div class="welcome-banner">
                    <div>
                        <h2>System Overview</h2>
                        <p>NSSF Contributions Tracking &middot; Admin Console</p>
                    </div>
                    <div class="welcome-date"><?php echo date('l, d F Y'); ?></div>
                </div>

                <div class="section-label">At a Glance</div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-icon">🏢</span>
                        <div class="stat-value"><?php echo $total_employers; ?></div>
                        <div class="stat-label">Employers</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">👥</span>
                        <div class="stat-value"><?php echo $total_employees_all; ?></div>
                        <div class="stat-label">Employees</div>
                    </div>
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
                        <div class="stat-value">UGX <?php echo number_format($total_collected, 0); ?></div>
                        <div class="stat-label">Total Collected</div>
                    </div>
                    <div class="stat-card <?php echo $total_penalties_owed > 0 ? 'stat-bad' : ''; ?>">
                        <span class="stat-icon">🔺</span>
                        <div class="stat-value">UGX <?php echo number_format($total_penalties_owed, 0); ?></div>
                        <div class="stat-label">Penalties Owed</div>
                    </div>
                </div>

                <div class="compliance-block">
                    <h4>System-wide Compliance Rate</h4>
                    <div class="progress-track">
                        <div class="progress-fill <?php echo $compliance_rate >= 80 ? 'good' : ($compliance_rate >= 50 ? 'warn' : 'bad'); ?>" style="width: <?php echo $compliance_rate; ?>%;"></div>
                    </div>
                    <div class="progress-caption">
                        <span><?php echo $count_paid; ?> of <?php echo $total_records; ?> contribution records paid</span>
                        <span><strong><?php echo $compliance_rate; ?>%</strong></span>
                    </div>
                </div>

                <div class="section-label">Quick Actions</div>
                <div class="actions-bar" style="margin-top:10px;">
                    <a href="add_employer.php">+ Add New Employer</a>
                </div>
            </div>

            <!-- ===== EMPLOYERS PANEL ===== -->
            <div class="panel" id="panel-employers">
                <h2>Employers</h2>
                <div class="actions-bar">
                    <a href="add_employer.php">+ Add New Employer</a>
                </div>
                <div class="card">
                    <p class="context-hint">Right-click a row to Edit or Delete.</p>
                    <table>
                        <tr>
                            <th>ID</th><th>Company Name</th><th>Registration No.</th><th>Address</th><th>Phone</th><th>Employees</th>
                        </tr>
                        <?php
                        $sql = "SELECT e.*, COUNT(emp.employee_id) AS employee_count
                                FROM employers e
                                LEFT JOIN employees emp ON emp.employer_id = e.employer_id
                                GROUP BY e.employer_id";
                        $result = $conn->query($sql);
                        if ($result->num_rows == 0) {
                            echo "<tr><td colspan='6' style='text-align:center; color:#5B6B62;'>No employers registered yet.</td></tr>";
                        }
                        while ($row = $result->fetch_assoc()) {
                            $edit_url = "edit_employer.php?id=" . $row['employer_id'];
                            $delete_url = "delete_employer.php?id=" . $row['employer_id'];
                            $confirm_msg = "Delete this employer? This will also delete all their employees and contribution records. This cannot be undone.";
                            echo "<tr class='has-context' oncontextmenu=\"return openContextMenu(event, '$edit_url', '$delete_url', '$confirm_msg');\">";
                            echo "<td>" . $row['employer_id'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['company_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['registration_number']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['address']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                            echo "<td>" . $row['employee_count'] . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>

            <!-- ===== EMPLOYEES PANEL (system-wide) ===== -->
            <div class="panel" id="panel-employees">
                <h2>All Employees</h2>
                <div class="card">
                    <table>
                        <tr>
                            <th>ID</th><th>NSSF Number</th><th>First Name</th><th>Last Name</th><th>National ID</th><th>Employer ID</th><th>Date Joined</th>
                        </tr>
                        <?php
                        $result = $conn->query("SELECT * FROM employees");
                        if ($result->num_rows == 0) {
                            echo "<tr><td colspan='7' style='text-align:center; color:#5B6B62;'>No employees registered yet.</td></tr>";
                        }
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $row['employee_id'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['nssf_number']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['first_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['last_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['national_id']) . "</td>";
                            echo "<td>" . $row['employer_id'] . "</td>";
                            echo "<td>" . $row['date_joined'] . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>

            <!-- ===== CONTRIBUTIONS PANEL (system-wide) ===== -->
            <div class="panel" id="panel-contributions">
                <h2>All Contributions</h2>
                <div class="card">
                    <table>
                        <tr>
                            <th>ID</th><th>Employee ID</th><th>Month</th><th>Year</th><th>Gross Salary</th><th>Employee</th><th>Employer</th><th>Total</th><th>Status</th><th>Penalty</th>
                        </tr>
                        <?php
                        $result = $conn->query("SELECT * FROM contributions ORDER BY contribution_year DESC, contribution_month DESC");
                        if ($result->num_rows == 0) {
                            echo "<tr><td colspan='10' style='text-align:center; color:#5B6B62;'>No contributions recorded yet.</td></tr>";
                        }
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $row['contribution_id'] . "</td>";
                            echo "<td>" . $row['employee_id'] . "</td>";
                            echo "<td>" . $row['contribution_month'] . "</td>";
                            echo "<td>" . $row['contribution_year'] . "</td>";
                            echo "<td>" . number_format($row['gross_salary'], 2) . "</td>";
                            echo "<td>" . number_format($row['employee_contribution'], 2) . "</td>";
                            echo "<td>" . number_format($row['employer_contribution'], 2) . "</td>";
                            echo "<td>" . number_format($row['total_contribution'], 2) . "</td>";
                            echo "<td>" . status_badge($row['status']) . "</td>";
                            $penalty = calculate_penalty($row['contribution_month'], $row['contribution_year'], $row['total_contribution'], $row['status']);
                            if ($penalty > 0) {
                                echo "<td style='color:#B3261E; font-weight:600;'>UGX " . number_format($penalty, 2) . "</td>";
                            } else {
                                echo "<td>&mdash;</td>";
                            }
                            echo "</tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>

            <!-- ===== ACTIVITY LOG PANEL ===== -->
            <div class="panel" id="panel-activity">
                <h2>Activity Log</h2>
                <p style="color:#5B6B62; font-size:0.9rem; margin-top:-8px;">A record of who added, edited, or deleted records across the system. Most recent first.</p>
                <div class="card">
                    <table>
                        <tr>
                            <th>Date/Time</th><th>User</th><th>Role</th><th>Action</th><th>Area</th><th>Details</th>
                        </tr>
                        <?php
                        $log_result = $conn->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 200");
                        if ($log_result->num_rows == 0) {
                            echo "<tr><td colspan='6' style='text-align:center; color:#5B6B62;'>No activity recorded yet.</td></tr>";
                        }
                        while ($log = $log_result->fetch_assoc()) {
                            $action_color = $log['action'] == 'deleted' ? '#B3261E' : ($log['action'] == 'added' ? '#1E7A46' : '#9A7115');
                            echo "<tr>";
                            echo "<td>" . date('d M Y, H:i', strtotime($log['created_at'])) . "</td>";
                            echo "<td>" . htmlspecialchars($log['username']) . "</td>";
                            echo "<td>" . ucfirst(htmlspecialchars($log['role'])) . "</td>";
                            echo "<td style='color:$action_color; font-weight:600; text-transform:capitalize;'>" . htmlspecialchars($log['action']) . "</td>";
                            echo "<td>" . ucfirst(htmlspecialchars($log['table_name'])) . "</td>";
                            echo "<td>" . htmlspecialchars($log['details']) . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="rowContextMenu" class="context-menu"></div>
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

        function openContextMenu(event, editUrl, deleteUrl, confirmMsg) {
            event.preventDefault();
            const menu = document.getElementById('rowContextMenu');
            menu.innerHTML =
                "<a href='" + editUrl + "'>Edit</a>" +
                "<a href='#' class='danger' onclick=\"if(confirm('" + confirmMsg + "')){ window.location='" + deleteUrl + "'; } return false;\">Delete</a>";
            menu.style.left = event.pageX + 'px';
            menu.style.top = event.pageY + 'px';
            menu.style.display = 'block';
            return false;
        }
        document.addEventListener('click', function () {
            document.getElementById('rowContextMenu').style.display = 'none';
        });
    </script>
</body>
</html>