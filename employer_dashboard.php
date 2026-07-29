<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();

if (!$employer) {
    die("No employer profile found for this account.");
}

$employer_id = $employer['employer_id'];

function status_badge($status) {
    return "<span class='status " . htmlspecialchars($status) . "'>" . ucfirst(htmlspecialchars($status)) . "</span>";
}

// ---- Late payment penalty calculation ----
// Assumption for this project: contributions are due by the 15th of the month
// FOLLOWING the contribution month. If still unpaid (status = overdue) past that
// date, a 5% penalty is applied per full month late, based on the total contribution.
// (This is a simplified demo rule, not an official NSSF policy figure.)
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

// ---- Search / filter inputs (all optional) ----
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : "";
$filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : "";
$filter_year = isset($_GET['filter_year']) ? $_GET['filter_year'] : "";
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : "";

$sql = "SELECT c.*, e.first_name, e.last_name
        FROM contributions c
        JOIN employees e ON c.employee_id = e.employee_id
        WHERE e.employer_id = ?";
$types = "i";
$params = [$employer_id];

if ($search_name != "") {
    $sql .= " AND CONCAT(e.first_name, ' ', e.last_name) LIKE ?";
    $types .= "s";
    $params[] = "%" . $search_name . "%";
}
if ($filter_month != "") {
    $sql .= " AND c.contribution_month = ?";
    $types .= "i";
    $params[] = $filter_month;
}
if ($filter_year != "") {
    $sql .= " AND c.contribution_year = ?";
    $types .= "i";
    $params[] = $filter_year;
}
if ($filter_status != "") {
    $sql .= " AND c.status = ?";
    $types .= "s";
    $params[] = $filter_status;
}

$sql .= " ORDER BY c.contribution_year DESC, c.contribution_month DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$contributions_result = $stmt->get_result();

// ---- Employees (for the Employees panel) ----
$stmt = $conn->prepare("SELECT * FROM employees WHERE employer_id = ?");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$employees_result = $stmt->get_result();
$total_employees = $employees_result->num_rows;

// ---- Compliance summary stats (always based on ALL data, ignoring search/filter) ----
$stmt = $conn->prepare("SELECT c.* FROM contributions c
                         JOIN employees e ON c.employee_id = e.employee_id
                         WHERE e.employer_id = ?");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$all_contributions = $stmt->get_result();

$count_paid = 0;
$count_pending = 0;
$count_overdue = 0;
$total_collected = 0;
$total_penalties_owed = 0;

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
$total_contribution_records = $count_paid + $count_pending + $count_overdue;
$compliance_rate = $total_contribution_records > 0 ? round(($count_paid / $total_contribution_records) * 100) : 0;

// ---- Analytics: contributions collected per month (paid only), across all recorded periods ----
$stmt = $conn->prepare("
    SELECT c.contribution_year, c.contribution_month, SUM(c.total_contribution) AS collected
    FROM contributions c
    JOIN employees e ON c.employee_id = e.employee_id
    WHERE e.employer_id = ? AND c.status = 'paid'
    GROUP BY c.contribution_year, c.contribution_month
    ORDER BY c.contribution_year, c.contribution_month
");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$monthly_result = $stmt->get_result();

$month_short = ["", "Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
$analytics_labels = [];
$analytics_values = [];
while ($row = $monthly_result->fetch_assoc()) {
    $analytics_labels[] = $month_short[$row['contribution_month']] . " " . $row['contribution_year'];
    $analytics_values[] = (float)$row['collected'];
}

// Which panel to show first - remembers via URL, defaults to overview
$active_panel = isset($_GET['panel']) ? $_GET['panel'] : 'overview';
if (isset($_GET['updated']) || isset($_GET['deleted'])) {
    $active_panel = 'contributions';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Employer Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>
<body>
    <div class="topbar">
        <span class="brand"><button class="hamburger" onclick="toggleSidebar()">&#9776;</button>NSSF &middot; Employer</span>
        <span>Welcome, <?php echo htmlspecialchars($employer['company_name']); ?> &nbsp;|&nbsp; <a href="logout.php">Logout</a></span>
    </div>

    <div class="layout">
        <div class="sidebar open" id="sidebar">
            <div class="sidebar-title">Menu</div>
            <div class="sidebar-nav">
                <a href="#" data-panel="overview" class="nav-link">Overview</a>
                <a href="#" data-panel="employees" class="nav-link">Employees <span class="badge"><?php echo $total_employees; ?></span></a>
                <a href="#" data-panel="contributions" class="nav-link">Contributions <span class="badge"><?php echo $total_contribution_records; ?></span></a>
                <a href="#" data-panel="trns" class="nav-link">TRN History</a>
                <a href="#" data-panel="analytics" class="nav-link">Analytics</a>
                <a href="#" data-panel="profile" class="nav-link">Company Profile</a>
            </div>
        </div>

        <div class="main-content">
            <?php if (isset($_GET['updated'])) { ?>
                <p class="message success">Contribution updated successfully.</p>
            <?php } ?>
            <?php if (isset($_GET['deleted'])) { ?>
                <p class="message success">Contribution deleted successfully.</p>
            <?php } ?>

            <!-- ===== OVERVIEW PANEL ===== -->
            <div class="panel" id="panel-overview">
                <div class="welcome-banner">
                    <div>
                        <h2><?php echo htmlspecialchars($employer['company_name']); ?></h2>
                        <p>Registration No. <?php echo htmlspecialchars($employer['registration_number']); ?> &nbsp;&middot;&nbsp; NSSF Contributions Overview</p>
                    </div>
                    <div class="welcome-date"><?php echo date('l, d F Y'); ?></div>
                </div>

                <div class="section-label">At a Glance</div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-icon">👥</span>
                        <div class="stat-value"><?php echo $total_employees; ?></div>
                        <div class="stat-label">Employees</div>
                    </div>
                    <div class="stat-card stat-good">
                        <span class="stat-icon">✅</span>
                        <div class="stat-value"><?php echo $count_paid; ?></div>
                        <div class="stat-label">Paid Contributions</div>
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
                    <h4>Compliance Rate</h4>
                    <div class="progress-track">
                        <div class="progress-fill <?php echo $compliance_rate >= 80 ? 'good' : ($compliance_rate >= 50 ? 'warn' : 'bad'); ?>" style="width: <?php echo $compliance_rate; ?>%;"></div>
                    </div>
                    <div class="progress-caption">
                        <span><?php echo $count_paid; ?> of <?php echo $total_contribution_records; ?> contribution records paid</span>
                        <span><strong><?php echo $compliance_rate; ?>%</strong></span>
                    </div>
                </div>

                <div class="section-label">Quick Actions</div>
                <div class="actions-bar" style="margin-top:10px;">
                    <a href="generate_trn.php">Generate TRN</a>
                    <a href="add_contribution.php">+ Add New Contribution</a>
                    <a href="add_employee.php">+ Add New Employee</a>
                </div>
            </div>

            <!-- ===== EMPLOYEES PANEL ===== -->
            <div class="panel" id="panel-employees">
                <h2>My Employees</h2>
                <div class="actions-bar">
                    <a href="add_employee.php">+ Add New Employee</a>
                </div>
                <div class="card">
                    <p class="context-hint">Right-click a row to Edit (set/update monthly salary).</p>
                    <table>
                        <tr>
                            <th>ID</th><th>NSSF Number</th><th>First Name</th><th>Last Name</th><th>National ID</th><th>Monthly Salary</th><th>Date Joined</th>
                        </tr>
                        <?php
                        if ($total_employees == 0) {
                            echo "<tr><td colspan='7' style='text-align:center; color:#5B6B62;'>No employees added yet.</td></tr>";
                        }
                        while ($row = $employees_result->fetch_assoc()) {
                            $edit_emp_url = "edit_employee.php?id=" . $row['employee_id'];
                            echo "<tr class='has-context' oncontextmenu=\"return openEmployeeContextMenu(event, '$edit_emp_url');\">";
                            echo "<td>" . $row['employee_id'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['nssf_number']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['first_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['last_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['national_id']) . "</td>";
                            if ($row['monthly_salary'] > 0) {
                                echo "<td>UGX " . number_format($row['monthly_salary'], 2) . "</td>";
                            } else {
                                echo "<td style='color:#B3261E;'>Not set</td>";
                            }
                            echo "<td>" . $row['date_joined'] . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>

            <!-- ===== CONTRIBUTIONS PANEL ===== -->
            <div class="panel" id="panel-contributions">
                <h2>Contributions</h2>
                <div class="actions-bar">
                    <a href="add_contribution.php">+ Add New Contribution</a>
                </div>

                <div class="card">
                    <form method="GET" action="employer_dashboard.php" style="max-width:none; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:12px;">
                        <input type="hidden" name="panel" value="contributions">
                        <div style="flex:1; min-width:160px;">
                            <label style="margin-top:0;">Employee Name</label>
                            <input type="text" id="liveSearchInput" name="search_name" placeholder="Start typing to filter..." value="<?php echo htmlspecialchars($search_name); ?>" oninput="liveFilterRows()" autocomplete="off">
                        </div>
                        <div style="min-width:140px;">
                            <label style="margin-top:0;">Month</label>
                            <select name="filter_month">
                                <option value="">All</option>
                                <?php
                                $months = ["January","February","March","April","May","June","July","August","September","October","November","December"];
                                foreach ($months as $index => $name) {
                                    $num = $index + 1;
                                    $selected = ($filter_month == $num) ? "selected" : "";
                                    echo "<option value='$num' $selected>$name</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div style="min-width:110px;">
                            <label style="margin-top:0;">Year</label>
                            <input type="number" name="filter_year" placeholder="2026" value="<?php echo htmlspecialchars($filter_year); ?>">
                        </div>
                        <div style="min-width:130px;">
                            <label style="margin-top:0;">Status</label>
                            <select name="filter_status">
                                <option value="">All</option>
                                <?php
                                foreach (['pending','paid','overdue'] as $s) {
                                    $selected = ($filter_status == $s) ? "selected" : "";
                                    echo "<option value='$s' $selected>" . ucfirst($s) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" style="margin-top:0;">Search</button>
                        </div>
                        <?php if ($search_name || $filter_month || $filter_year || $filter_status) { ?>
                            <div><a href="employer_dashboard.php?panel=contributions" style="display:inline-block; padding:11px 4px;">Clear filters</a></div>
                        <?php } ?>
                        <div>
                            <a href="export_contributions.php?search_name=<?php echo urlencode($search_name); ?>&filter_month=<?php echo urlencode($filter_month); ?>&filter_year=<?php echo urlencode($filter_year); ?>&filter_status=<?php echo urlencode($filter_status); ?>"
                               style="display:inline-block; padding:11px 16px; background:var(--gold); color:#2b2109; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.9rem;">
                                Export to CSV
                            </a>
                        </div>
                    </form>

                    <p class="context-hint">Right-click a row to Edit or Delete.</p>
                    <table>
                        <tr>
                            <th>ID</th><th>Employee Name</th><th>Month</th><th>Year</th><th>Gross Salary</th><th>Employee</th><th>Employer</th><th>Total</th><th>Status</th><th>Penalty</th><th>Receipt</th>
                        </tr>
                        <?php
                        if ($contributions_result->num_rows == 0) {
                            echo "<tr><td colspan='11' style='text-align:center; color:#5B6B62;'>No matching contributions found.</td></tr>";
                        }
                        while ($row = $contributions_result->fetch_assoc()) {
                            $edit_url = "edit_contribution.php?id=" . $row['contribution_id'];
                            $delete_url = "delete_contribution.php?id=" . $row['contribution_id'];
                            echo "<tr class='has-context contribution-row' oncontextmenu=\"return openContextMenu(event, '$edit_url', '$delete_url', 'Delete this contribution record?');\">";
                            echo "<td>" . $row['contribution_id'] . "</td>";
                            echo "<td><span class='emp-name-text'>" . htmlspecialchars($row['first_name'] . " " . $row['last_name']) . "</span></td>";
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

            <!-- ===== TRN HISTORY PANEL ===== -->
            <div class="panel" id="panel-trns">
                <h2>TRN History</h2>
                <div class="actions-bar">
                    <a href="generate_trn.php">Generate New TRN</a>
                </div>
                <div class="card">
                    <table>
                        <tr>
                            <th>TRN Number</th><th>Period</th><th>Employees</th><th>Total Amount</th><th>Status</th><th>Generated</th>
                        </tr>
                        <?php
                        $trn_sql = "SELECT c.trn_number,
                                           c.contribution_month, c.contribution_year,
                                           COUNT(*) AS emp_count,
                                           SUM(c.total_contribution) AS total_amount,
                                           SUM(CASE WHEN c.status != 'paid' THEN 1 ELSE 0 END) AS unpaid_count,
                                           MIN(c.contribution_id) AS first_id
                                    FROM contributions c
                                    JOIN employees e ON c.employee_id = e.employee_id
                                    WHERE e.employer_id = ? AND c.trn_number IS NOT NULL
                                    GROUP BY c.trn_number, c.contribution_month, c.contribution_year
                                    ORDER BY first_id DESC";
                        $trn_stmt = $conn->prepare($trn_sql);
                        $trn_stmt->bind_param("i", $employer_id);
                        $trn_stmt->execute();
                        $trn_result = $trn_stmt->get_result();

                        $months_list = ["", "January","February","March","April","May","June","July","August","September","October","November","December"];

                        if ($trn_result->num_rows == 0) {
                            echo "<tr><td colspan='6' style='text-align:center; color:#5B6B62;'>No TRNs generated yet.</td></tr>";
                        }
                        while ($trn = $trn_result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td><a href='view_trn.php?trn=" . urlencode($trn['trn_number']) . "' style='font-family:monospace;'>" . htmlspecialchars($trn['trn_number']) . "</a></td>";
                            echo "<td>" . $months_list[$trn['contribution_month']] . " " . $trn['contribution_year'] . "</td>";
                            echo "<td>" . $trn['emp_count'] . "</td>";
                            echo "<td>UGX " . number_format($trn['total_amount'], 2) . "</td>";
                            if ($trn['unpaid_count'] == 0) {
                                echo "<td><span class='status paid'>Fully Paid</span></td>";
                            } else {
                                echo "<td><span class='status pending'>Awaiting Payment</span></td>";
                            }
                            echo "<td>-</td>";
                            echo "</tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>

            <!-- ===== ANALYTICS PANEL ===== -->
            <div class="panel" id="panel-analytics">
                <h2>Analytics</h2>
                <p style="color:#5B6B62; font-size:0.9rem; margin-top:-8px;">Visual summary of your contribution activity.</p>

                <div class="card">
                    <h4 style="margin-top:0; color:var(--green-dark); font-size:0.95rem;">Contributions Collected by Month (UGX)</h4>
                    <?php if (count($analytics_labels) == 0) { ?>
                        <p style="color:#5B6B62; text-align:center; padding:30px 0;">No paid contributions recorded yet. This chart will populate once contributions are marked as paid.</p>
                    <?php } else { ?>
                        <canvas id="monthlyChart" height="90"></canvas>
                    <?php } ?>
                </div>

                <div class="card">
                    <h4 style="margin-top:0; color:var(--green-dark); font-size:0.95rem;">Contribution Status Breakdown</h4>
                    <?php if ($total_contribution_records == 0) { ?>
                        <p style="color:#5B6B62; text-align:center; padding:30px 0;">No contributions recorded yet.</p>
                    <?php } else { ?>
                        <div style="max-width:320px; margin:0 auto;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- ===== COMPANY PROFILE PANEL ===== -->
            <div class="panel" id="panel-profile">
                <h2>Company Profile</h2>
                <div class="card">
                    <p><strong>Company Name:</strong> <?php echo htmlspecialchars($employer['company_name']); ?></p>
                    <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($employer['registration_number']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($employer['phone']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($employer['address']); ?></p>
                </div>

                <?php if (!empty($employer['address'])) { ?>
                    <div class="card">
                        <h4 style="margin-top:0; color:var(--green-dark); font-size:0.95rem;">Location</h4>
                        <div class="map-wrap">
                            <iframe
                                src="https://maps.google.com/maps?q=<?php echo urlencode($employer['address']); ?>&output=embed"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                        </div>
                        <div class="actions-bar" style="margin-top:14px;">
                            <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo urlencode($employer['address']); ?>" target="_blank" rel="noopener">
                                Get Directions
                            </a>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="card">
                        <p style="color:#5B6B62;">No address on file yet. Ask an admin to add one to see the location on a map.</p>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div id="rowContextMenu" class="context-menu"></div>
    <script>
        // ---- Sidebar toggle (hamburger) ----
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }

        // ---- Sidebar panel switching (client-side, no reload) ----
        let analyticsInitialized = false;
        function showPanel(name) {
            document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.nav-link').forEach(a => a.classList.remove('active'));
            const panel = document.getElementById('panel-' + name);
            if (panel) panel.classList.add('active');
            const link = document.querySelector('.nav-link[data-panel="' + name + '"]');
            if (link) link.classList.add('active');

            // Charts can't render correctly while hidden (display:none), so build them
            // only the first time the Analytics panel is actually opened.
            if (name === 'analytics' && !analyticsInitialized) {
                analyticsInitialized = true;
                initAnalyticsCharts();
            }
        }
        document.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                showPanel(this.dataset.panel);
            });
        });
        // Show the correct panel on page load (based on PHP $active_panel)
        showPanel("<?php echo htmlspecialchars($active_panel); ?>");

        // ---- Build the analytics charts (called once, lazily) ----
        function initAnalyticsCharts() {
            const monthlyLabels = <?php echo json_encode($analytics_labels); ?>;
            const monthlyValues = <?php echo json_encode($analytics_values); ?>;

            const monthlyCanvas = document.getElementById('monthlyChart');
            if (monthlyCanvas && monthlyLabels.length > 0) {
                new Chart(monthlyCanvas, {
                    type: 'bar',
                    data: {
                        labels: monthlyLabels,
                        datasets: [{
                            label: 'Collected (UGX)',
                            data: monthlyValues,
                            backgroundColor: '#0B5D3B',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString() } }
                        }
                    }
                });
            }

            const statusCanvas = document.getElementById('statusChart');
            if (statusCanvas) {
                new Chart(statusCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: ['Paid', 'Pending', 'Overdue'],
                        datasets: [{
                            data: [<?php echo $count_paid; ?>, <?php echo $count_pending; ?>, <?php echo $count_overdue; ?>],
                            backgroundColor: ['#1E7A46', '#C79A3B', '#B3261E']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            }
        }

        // ---- Right-click context menu for Employee rows (Edit only) ----
        function openEmployeeContextMenu(event, editUrl) {
            event.preventDefault();
            const menu = document.getElementById('rowContextMenu');
            menu.innerHTML = "<a href='" + editUrl + "'>Edit</a>";
            menu.style.left = event.pageX + 'px';
            menu.style.top = event.pageY + 'px';
            menu.style.display = 'block';
            return false;
        }

        // ---- Right-click context menu for row actions ----
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

        // ---- Live filter + highlight as the user types in the Employee Name box ----
        function liveFilterRows() {
            const input = document.getElementById('liveSearchInput');
            if (!input) return;
            const query = input.value.trim().toLowerCase();
            const rows = document.querySelectorAll('.contribution-row');
            let visibleCount = 0;

            rows.forEach(function (row) {
                const nameEl = row.querySelector('.emp-name-text');
                const originalText = nameEl.dataset.original || nameEl.textContent;
                nameEl.dataset.original = originalText;

                if (query === "") {
                    nameEl.innerHTML = originalText;
                    row.style.display = "";
                    visibleCount++;
                    return;
                }

                const lowerText = originalText.toLowerCase();
                if (lowerText.includes(query)) {
                    const startIndex = lowerText.indexOf(query);
                    const before = originalText.substring(0, startIndex);
                    const match = originalText.substring(startIndex, startIndex + query.length);
                    const after = originalText.substring(startIndex + query.length);
                    nameEl.innerHTML = before + "<mark>" + match + "</mark>" + after;
                    row.style.display = "";
                    visibleCount++;
                } else {
                    row.style.display = "none";
                }
            });

            let noResultsRow = document.getElementById('liveNoResultsRow');
            if (visibleCount === 0) {
                if (!noResultsRow) {
                    const table = document.querySelector('.contribution-row')
                        ? document.querySelector('.contribution-row').closest('table')
                        : null;
                    if (table) {
                        noResultsRow = document.createElement('tr');
                        noResultsRow.id = 'liveNoResultsRow';
                        noResultsRow.innerHTML = "<td colspan='11' style='text-align:center; color:#5B6B62;'>No employees match \"" + query + "\".</td>";
                        table.appendChild(noResultsRow);
                    }
                }
            } else if (noResultsRow) {
                noResultsRow.remove();
            }
        }
    </script>
</body>
</html>