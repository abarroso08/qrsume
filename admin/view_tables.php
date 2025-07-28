<?php
include('../assets/db.php');

if (!isset($_SESSION['privilege']) || $_SESSION['privilege'] !== 'admin') {
    die("Access denied. You must be an admin.");
}

$tables = [
    'super_table', // NEW!
    'aptitudes', 'blogarticles', 'contactinfo', 'education', 'experience',
    'feedback', 'interests', 'languages', 'page_views', 'personalinfo',
    'photos', 'users', 'visitor_logs', 'web_statistics','page_stats','user_statistics'
];

$joinable_tables = [
    'aptitudes', 'blogarticles', 'contactinfo', 'education', 'experience',
    'interests', 'languages', 'personalinfo', 'photos','page_stats','user_statistics'
];

$selected_table = isset($_GET['table']) && in_array($_GET['table'], $tables) ? $_GET['table'] : null;
$selected_user = isset($_GET['user']) ? $_GET['user'] : "";

// Get all usernames for dropdown
$stmt = $db->prepare("SELECT username FROM users ORDER BY username");
$stmt->execute();
$usernames = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get user_id from username
function getUserId($db, $username) {
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    return $stmt->fetchColumn();
}

// Get rows from table
function getTableData($db, $table, $join = false, $user = null) {
    if ($join) {
        $sql = "SELECT $table.*, users.username FROM `$table` LEFT JOIN users ON $table.user_id = users.id";
        if ($user) {
            $sql .= " WHERE users.username = :username";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':username', $user);
        } else {
            $stmt = $db->prepare($sql);
        }
    } else {
        $sql = "SELECT * FROM `$table`";
        $stmt = $db->prepare($sql);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Tables</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        td {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-4">
    <h2 class="mb-4">Database Tables</h2>
    <div class="row">
        <!-- Table List -->
        <div class="col-md-3">
            <h5 class="mb-3">Tables:</h5>
            <ul class="list-group">
                <?php foreach ($tables as $table): ?>
                    <li class="list-group-item <?php echo ($selected_table === $table) ? 'active' : ''; ?>">
                        <a href="?table=<?php echo $table; ?>" class="<?php echo ($selected_table === $table) ? 'text-white' : ''; ?>">
                            <?php echo $table === 'super_table' ? '🔗 Super Table' : $table; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Table Content -->
        <div class="col-md-9">
            <?php if ($selected_table === 'super_table'): ?>
                <h5 class="mb-3">Super Table: Joined view of all related data</h5>

                <!-- Filter Dropdown -->
                <form method="get" class="mb-3 row">
                    <input type="hidden" name="table" value="super_table">
                    <label class="col-sm-2 col-form-label">Filter by user:</label>
                    <div class="col-sm-6">
                        <select name="user" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Select User --</option>
                            <?php foreach ($usernames as $username): ?>
                                <option value="<?php echo $username; ?>" <?php echo ($username === $selected_user ? 'selected' : ''); ?>>
                                    <?php echo $username; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if ($selected_user): ?>
                    <?php
                    $user_id = getUserId($db, $selected_user);
                    if ($user_id):
                        echo "<h6 class='text-secondary'>Showing data for <code>$selected_user</code></h6>";

                        foreach ($joinable_tables as $table):
                            $rows = getTableData($db, $table, true, $selected_user);
                            echo "<h6 class='mt-4'>" . ucfirst($table) . "</h6>";
                            if (!empty($rows)):
                                echo '<div class="table-responsive"><table class="table table-bordered table-sm">';
                                echo '<thead class="table-dark"><tr>';
                                foreach (array_keys($rows[0]) as $col) {
                                    echo "<th>" . htmlspecialchars($col) . "</th>";
                                }
                                echo "</tr></thead><tbody>";
                                foreach ($rows as $row) {
                                    echo "<tr>";
                                    foreach ($row as $cell) {
                                        echo "<td>" . htmlspecialchars($cell) . "</td>";
                                    }
                                    echo "</tr>";
                                }
                                echo "</tbody></table></div>";
                            else:
                                echo "<p class='text-muted'>No data found in <code>$table</code>.</p>";
                            endif;
                        endforeach;
                    else:
                        echo "<div class='alert alert-danger'>User not found.</div>";
                    endif;
                    ?>
                <?php else: ?>
                    <p class="text-muted">Select a user to view the super table.</p>
                <?php endif; ?>

            <?php elseif ($selected_table): ?>
                <h5 class="mb-3">Contents of <code><?php echo $selected_table; ?></code></h5>

                <!-- Username Filter -->
                <?php if (in_array($selected_table, $joinable_tables)): ?>
                    <form method="get" class="mb-3 row">
                        <input type="hidden" name="table" value="<?php echo $selected_table; ?>">
                        <label class="col-sm-2 col-form-label">Filter by user:</label>
                        <div class="col-sm-6">
                            <select name="user" class="form-select" onchange="this.form.submit()">
                                <option value="">-- All Users --</option>
                                <?php foreach ($usernames as $username): ?>
                                    <option value="<?php echo $username; ?>" <?php echo ($username === $selected_user ? 'selected' : ''); ?>>
                                        <?php echo $username; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($selected_user): ?>
                            <div class="col-sm-4">
                                <a href="?table=<?php echo $selected_table; ?>" class="btn btn-secondary">Clear Filter</a>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <!-- Data Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm">
                        <thead class="table-dark">
                            <tr>
                                <?php
                                $join = in_array($selected_table, $joinable_tables);
                                $rows = getTableData($db, $selected_table, $join, $selected_user);
                                if (!empty($rows)) {
                                    foreach (array_keys($rows[0]) as $column) {
                                        echo "<th>" . htmlspecialchars($column) . "</th>";
                                    }
                                    echo "</tr></thead><tbody>";
                                    foreach ($rows as $row) {
                                        echo "<tr>";
                                        foreach ($row as $cell) {
                                            echo "<td>" . htmlspecialchars($cell) . "</td>";
                                        }
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<th>No data found.</th></tr></thead>";
                                }
                                ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Select a table from the list to view its contents.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
