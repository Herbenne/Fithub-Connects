<?php
session_start();
include '../config/database.php';

// Get gym_id first so it's available for all operations
$gym_id = $_GET['gym_id'] ?? null;

// Handle delete operation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_plan'])) {
    $plan_id = $_POST['plan_id'];

    // First verify the plan belongs to this gym
    $verify_query = "SELECT * FROM membership_plans WHERE plan_id = ? AND gym_id = ?";
    $stmt = $db_connection->prepare($verify_query);
    $stmt->bind_param("ii", $plan_id, $gym_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Plan exists and belongs to this gym, proceed with deletion
        $delete_query = "DELETE FROM membership_plans WHERE plan_id = ?";
        $stmt = $db_connection->prepare($delete_query);
        $stmt->bind_param("i", $plan_id);
        
        if ($stmt->execute()) {
            header("Location: manage_plans.php?gym_id=" . $gym_id . "&success=deleted");
            exit();
        } else {
            header("Location: manage_plans.php?gym_id=" . $gym_id . "&error=delete_failed");
            exit();
        }
    } else {
        header("Location: manage_plans.php?gym_id=" . $gym_id . "&error=invalid_plan");
        exit();
    }
}

// Ensure user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Available duration options
$duration_options = [1, 3, 6, 12]; // Common membership durations in months

// Verify this gym belongs to the admin
$verify_query = "SELECT * FROM gyms WHERE gym_id = ? AND owner_id = ?";
$stmt = $db_connection->prepare($verify_query);
$stmt->bind_param("ii", $gym_id, $_SESSION['user_id']);
$stmt->execute();
$gym = $stmt->get_result()->fetch_assoc();

if (!$gym) {
    header("Location: dashboard.php");
    exit();
}

// Fetch existing plans
$plans_query = "SELECT * FROM membership_plans WHERE gym_id = ? ORDER BY price ASC";
$stmt = $db_connection->prepare($plans_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$plans = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Plans - FitHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/manage_plans.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <h2>Manage Membership Plans - <?php echo htmlspecialchars($gym['gym_name']); ?></h2>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php 
                $message = 'Plan was successfully ';
                $message .= $_GET['success'] === 'create' ? 'created.' : 
                          ($_GET['success'] === 'update' ? 'updated.' : 'deleted.');
                echo $message;
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php 
                $error_message = '';
                switch($_GET['error']) {
                    case 'delete_failed':
                        $error_message = 'Failed to delete the plan. Please try again.';
                        break;
                    case 'invalid_plan':
                        $error_message = 'Invalid plan or you do not have permission to delete it.';
                        break;
                    default:
                        $error_message = htmlspecialchars($_GET['error']);
                }
                echo $error_message;
                ?>
            </div>
        <?php endif; ?>

        <div class="plans-container">
            <button onclick="showAddPlanForm()" class="add-plan-btn">
                <i class="fas fa-plus"></i> Add New Plan
            </button>

            <div id="addPlanForm" class="plan-form" style="display: none;">
                <h3>Add New Plan</h3>
                <form action="../actions/add_plan.php" method="POST">
                    <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                    
                    <div class="form-group">
                        <label>Plan Name *</label>
                        <input type="text" name="plan_name" required>
                    </div>

                    <div class="form-group">
                        <label>Duration *</label>
                        <select name="duration" required>
                            <?php foreach ($duration_options as $months): ?>
                                <option value="<?php echo $months; ?>">
                                    <?php echo $months . ' ' . ($months == 1 ? 'month' : 'months'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Price (₱) *</label>
                        <input type="number" name="price" required min="0" step="0.01">
                    </div>

                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" required rows="4" 
                                placeholder="List the benefits and features of this plan..."></textarea>
                    </div>

                    <button type="submit" class="submit-btn">Create Plan</button>
                    <button type="button" onclick="hideAddPlanForm()" class="cancel-btn">Cancel</button>
                </form>
            </div>

            <div id="editPlanForm" class="plan-form" style="display: none;">
                <h3>Edit Plan</h3>
                <form action="../actions/edit_plan.php" method="POST">
                    <input type="hidden" name="plan_id" id="edit_plan_id">
                    <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                    
                    <div class="form-group">
                        <label>Plan Name *</label>
                        <input type="text" name="plan_name" id="edit_plan_name" required>
                    </div>

                    <div class="form-group">
                        <label>Duration *</label>
                        <select name="duration" id="edit_duration" required>
                            <?php foreach ($duration_options as $months): ?>
                                <option value="<?php echo $months; ?>">
                                    <?php echo $months . ' ' . ($months == 1 ? 'month' : 'months'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Price (₱) *</label>
                        <input type="number" name="price" id="edit_price" required min="0" step="0.01">
                    </div>

                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" id="edit_description" required rows="4"></textarea>
                    </div>

                    <button type="submit" class="submit-btn">Update Plan</button>
                    <button type="button" onclick="hideEditPlanForm()" class="cancel-btn">Cancel</button>
                </form>
            </div>

            <div class="plans-grid">
                <?php if ($plans->num_rows > 0): 
                    while ($plan = $plans->fetch_assoc()): ?>
                        <div class="plan-card">
                            <div class="plan-header">
                                <h3><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                                <div class="plan-actions">
                                    <button onclick='showEditPlanForm(<?php echo json_encode($plan); ?>)' 
                                            class="edit-btn">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this plan?');">
                                        <input type="hidden" name="plan_id" value="<?php echo $plan['plan_id']; ?>">
                                        <input type="hidden" name="delete_plan" value="1">
                                        <button type="submit" class="delete-btn">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="plan-details">
                                <p class="plan-duration">
                                    <i class="fas fa-clock"></i> <?php echo htmlspecialchars($plan['duration']); ?>
                                </p>
                                <p class="plan-price">
                                    ₱<?php echo number_format($plan['price'], 2); ?>
                                </p>
                                <p class="plan-description">
                                    <?php echo nl2br(htmlspecialchars($plan['description'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endwhile;
                else: ?>
                    <p class="no-plans">No membership plans found. Add your first plan above.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function showAddPlanForm() {
        document.getElementById('addPlanForm').style.display = 'block';
    }

    function hideAddPlanForm() {
        document.getElementById('addPlanForm').style.display = 'none';
    }

    function showEditPlanForm(plan) {
        document.getElementById('edit_plan_id').value = plan.plan_id;
        document.getElementById('edit_plan_name').value = plan.plan_name;
        
        // Extract the number from duration string (e.g., "3 months" -> 3)
        const durationNumber = parseInt(plan.duration);
        document.getElementById('edit_duration').value = durationNumber;
        
        document.getElementById('edit_price').value = plan.price;
        document.getElementById('edit_description').value = plan.description;
        document.getElementById('editPlanForm').style.display = 'block';
        document.getElementById('addPlanForm').style.display = 'none';
    }

    function hideEditPlanForm() {
        document.getElementById('editPlanForm').style.display = 'none';
    }
    </script>
</body>
</html>