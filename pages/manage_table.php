<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

$table = $_GET['table'] ?? null;
if (!$table) {
    header("Location: dashboard.php");
    exit();
}

// Get table structure first
$columns = $db_connection->query("SHOW COLUMNS FROM $table");
if (!$columns) {
    die("Error fetching table structure: " . $db_connection->error);
}

$structure = [];
while ($column = $columns->fetch_assoc()) {
    $structure[] = $column;
}

// Now we can safely use $structure
$orderBy = $_GET['orderBy'] ?? $structure[0]['Field'] ?? 'id';
$order = $_GET['order'] ?? 'ASC';
$search = $_GET['search'] ?? '';

// Get table data with pagination
$page = $_GET['page'] ?? 1;
$per_page = 10;
$start = ($page - 1) * $per_page;

// Build the search clause
$whereClause = '';
if ($search) {
    $searchTerms = [];
    foreach ($structure as $column) {
        $searchTerms[] = "`{$column['Field']}` LIKE '%" . $db_connection->real_escape_string($search) . "%'";
    }
    $whereClause = "WHERE " . implode(' OR ', $searchTerms);
}

// Get total records for pagination
$count_query = "SELECT COUNT(*) as count FROM `$table` $whereClause";
$total_records = $db_connection->query($count_query)->fetch_assoc()['count'];
$total_pages = ceil($total_records / $per_page);

// Fetch the records
$query = "SELECT * FROM `$table` $whereClause ORDER BY `$orderBy` $order LIMIT ?, ?";
$stmt = $db_connection->prepare($query);

if (!$stmt) {
    die("Query preparation failed: " . $db_connection->error);
}

$stmt->bind_param("ii", $start, $per_page);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Query execution failed: " . $stmt->error);
}

// Add error handling function
function displayError($message) {
    echo "<div class='error-message'>Error: " . htmlspecialchars($message) . "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage <?php echo ucfirst($table); ?></title>
    <style>
        /* Add the styles from earlier plus: */
        .table-actions {
            margin: 20px 0;
        }
        
        .pagination {
            margin: 20px 0;
            display: flex;
            gap: 10px;
        }
        
        .page-link {
            padding: 5px 10px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #007bff;
        }
        
        .page-link.active {
            background: #007bff;
            color: white;
        }

        .table-controls {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .search-form {
            display: flex;
            gap: 10px;
        }

        .search-form input[type="search"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 300px;
        }

        .sort-link {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sort-link.active {
            font-weight: bold;
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            min-width: 400px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
        }

        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .bulk-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            background: #007bff;
            color: white;
        }

        .btn:hover {
            background: #0056b3;
        }

        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            border: 1px solid #ef9a9a;
        }

        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            border: 1px solid #a5d6a7;
        }

        .table-meta {
            margin: 10px 0;
            color: #666;
        }

        .loading {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h1>Manage <?php echo ucfirst($table); ?></h1>
        
        <div class="table-actions">
            <button onclick="showAddForm()" class="admin-btn">Add New Record</button>
            <button onclick="exportTable()" class="admin-btn">Export to CSV</button>
        </div>

        <div class="table-controls">
            <form class="search-form" method="GET">
                <input type="hidden" name="table" value="<?php echo $table; ?>">
                <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search in table...">
                <button type="submit" class="btn">Search</button>
                <?php if ($search): ?>
                    <a href="?table=<?php echo $table; ?>" class="btn">Clear</a>
                <?php endif; ?>
            </form>

            <div class="bulk-actions">
                <select id="bulkAction">
                    <option value="">Bulk Actions</option>
                    <option value="delete">Delete Selected</option>
                    <option value="export">Export Selected</option>
                </select>
                <button onclick="executeBulkAction()" class="btn">Apply</button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                    <?php foreach ($structure as $column): ?>
                        <th>
                            <a href="?table=<?php echo $table; ?>&orderBy=<?php echo $column['Field']; ?>&order=<?php echo ($orderBy === $column['Field'] && $order === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo $search ? "&search=$search" : ''; ?>" 
                               class="sort-link <?php echo $orderBy === $column['Field'] ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($column['Field']); ?>
                                <?php if ($orderBy === $column['Field']): ?>
                                    <span class="sort-indicator"><?php echo $order === 'ASC' ? '↑' : '↓'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                    <?php endforeach; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <?php foreach ($structure as $column): ?>
                            <td><?php echo htmlspecialchars($row[$column['Field']]); ?></td>
                        <?php endforeach; ?>
                        <td>
                            <button onclick="editRecord(<?php echo $row[$structure[0]['Field']]; ?>)" 
                                    class="btn btn-edit">Edit</button>
                            <button onclick="deleteRecord(<?php echo $row[$structure[0]['Field']]; ?>)" 
                                    class="btn btn-delete">Delete</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?table=<?php echo $table; ?>&page=<?php echo $i; ?>" 
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <script>
        function showAddForm() {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <h2>Add New Record</h2>
                    <form action="../actions/manage_table_action.php" method="POST">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="table" value="<?php echo $table; ?>">
                        <?php foreach ($structure as $column): ?>
                            <?php if ($column['Extra'] !== 'auto_increment'): ?>
                                <div class="form-group">
                                    <label><?php echo htmlspecialchars($column['Field']); ?></label>
                                    <input type="text" name="<?php echo $column['Field']; ?>" 
                                           <?php echo $column['Null'] === 'NO' ? 'required' : ''; ?>>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <button type="button" onclick="closeModal()" class="btn">Cancel</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(modal);
        }

        function closeModal() {
            document.querySelector('.modal').remove();
        }

        function toggleAll(source) {
            const checkboxes = document.getElementsByName('record[]');
            for (let checkbox of checkboxes) {
                checkbox.checked = source.checked;
            }
        }

        function handleError(error) {
            console.error(error);
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = error.message || 'An error occurred';
            document.querySelector('.dashboard-container').prepend(errorDiv);
            setTimeout(() => errorDiv.remove(), 5000);
        }

        function showLoading() {
            const loader = document.createElement('div');
            loader.className = 'loading';
            loader.innerHTML = '<div>Loading...</div>';
            document.body.appendChild(loader);
        }

        function hideLoading() {
            const loader = document.querySelector('.loading');
            if (loader) loader.remove();
        }

        async function executeBulkAction() {
            try {
                showLoading();
                const action = document.getElementById('bulkAction').value;
                const selected = document.querySelectorAll('input[name="record[]"]:checked');
                
                if (selected.length === 0) {
                    alert('Please select at least one record');
                    return;
                }

                const ids = Array.from(selected).map(cb => cb.value);

                switch (action) {
                    case 'delete':
                        if (confirm(`Delete ${selected.length} selected records?`)) {
                            window.location.href = `../actions/manage_table_action.php?action=bulk_delete&table=<?php echo $table; ?>&ids=${ids.join(',')}`;
                        }
                        break;
                    case 'export':
                        window.location.href = `../actions/export_table.php?table=<?php echo $table; ?>&ids=${ids.join(',')}`;
                        break;
                }
            } catch (error) {
                handleError(error);
            } finally {
                hideLoading();
            }
        }

        function editRecord(id) {
            // Fetch record data
            fetch(`../actions/get_record.php?table=<?php echo $table; ?>&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    const modal = document.createElement('div');
                    modal.className = 'modal';
                    modal.innerHTML = `
                        <div class="modal-content">
                            <h2>Edit Record</h2>
                            <form action="../actions/manage_table_action.php" method="POST">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="table" value="<?php echo $table; ?>">
                                <input type="hidden" name="id" value="${id}">
                                <?php foreach ($structure as $column): ?>
                                    <?php if ($column['Extra'] !== 'auto_increment'): ?>
                                        <div class="form-group">
                                            <label><?php echo htmlspecialchars($column['Field']); ?></label>
                                            <input type="text" name="<?php echo $column['Field']; ?>" 
                                                   value="${data['<?php echo $column['Field']; ?>'] || ''}"
                                                   <?php echo $column['Null'] === 'NO' ? 'required' : ''; ?>>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                    <button type="button" onclick="closeModal()" class="btn">Cancel</button>
                                </div>
                            </form>
                        </div>
                    `;
                    document.body.appendChild(modal);
                })
                .catch(error => handleError(error));
        }

        function deleteRecord(id) {
            if (confirm('Are you sure you want to delete this record?')) {
                showLoading();
                window.location.href = `../actions/manage_table_action.php?action=delete&table=<?php echo $table; ?>&id=${id}`;
            }
        }

        function exportTable() {
            window.location.href = `../actions/export_table.php?table=<?php echo $table; ?>`;
        }
    </script>
</body>
</html>