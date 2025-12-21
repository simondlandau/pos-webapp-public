<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once "config.php";
include "header.php";

// ✅ Add user logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $forename = ucfirst(strtolower(trim($_POST['forename'])));
    $surname  = ucfirst(strtolower(trim($_POST['surname'])));
    $email    = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone    = preg_match('/^\+\d{7,15}$/', $_POST['phone']) ? $_POST['phone'] : null;
    $receive  = isset($_POST['receive']) ? 1 : 0;

    if ($forename && $surname && $email && $phone) {
        $stmt = $pdo->prepare("INSERT INTO users (forename, surname, email, phone, receive) 
                               VALUES (:forename, :surname, :email, :phone, :receive)");
        $stmt->execute([
            ':forename' => $forename,
            ':surname'  => $surname,
            ':email'    => $email,
            ':phone'    => $phone,
            ':receive'  => $receive
        ]);
    }
}

// ✅ Update Weekly Target logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_target'])) {
    $weekly_target = floatval($_POST['weekly_target']);
    $work_days = intval($_POST['work_days']);
    
    if ($weekly_target > 0 && $work_days > 0) {
        $daily_target = $weekly_target / $work_days;
        
        $stmt = $pdo->prepare("UPDATE target 
                               SET weekly_target = :weekly_target, 
                                   work_days = :work_days, 
                                   daily_target = :daily_target 
                               WHERE target_ID = 1");
        $stmt->execute([
            ':weekly_target' => $weekly_target,
            ':work_days'     => $work_days,
            ':daily_target'  => $daily_target
        ]);
        
        $success_message = "Weekly target updated successfully!";
    }
}

// ✅ Fetch all users
$stmt = $pdo->query("SELECT id, forename, surname, email, phone, receive 
                     FROM users ORDER BY surname, forename");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Fetch current target values
$stmt = $pdo->query("SELECT weekly_target, work_days, daily_target 
                     FROM target WHERE target_ID = 1");
$target = $stmt->fetch(PDO::FETCH_ASSOC);

// Set defaults if no record exists
if (!$target) {
    $target = ['weekly_target' => 0, 'work_days' => 5, 'daily_target' => 0];
}
?>

<div class="container mt-4">
    <h3>Setup Notifications</h3>

    <!-- Add User Form -->
    <form method="POST">
        <div class="row">
            <div class="col-md-2"><input type="text" name="forename" class="form-control" placeholder="Forename" required></div>
            <div class="col-md-2"><input type="text" name="surname" class="form-control" placeholder="Surname" required></div>
            <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
            <div class="col-md-3"><input type="text" name="phone" class="form-control" placeholder="+353123456789" required></div>
            <div class="col-md-1">
                <input type="checkbox" name="receive"> Receive
            </div>
            <div class="col-md-1">
                <button type="submit" name="add_user" class="btn btn-primary btn-sm">Add</button>
            </div>
        </div>
    </form>

    <hr>

    <!-- User List -->
    <table class="table table-bordered mt-3">
        <thead class="table-light">
            <tr>
                <th>Forename</th>
                <th>Surname</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Request Emails</th>
                <th>Remove User</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $row): ?>
            <tr id="user-<?php echo $row['id']; ?>">
                <td><?php echo htmlspecialchars($row['forename']); ?></td>
                <td><?php echo htmlspecialchars($row['surname']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                <td>
                    <input type="checkbox" class="toggle-receive" data-id="<?php echo $row['id']; ?>" <?php if($row['receive']) echo "checked"; ?>>
                </td>
                <td>
                    <button class="btn btn-danger btn-sm remove-user" data-id="<?php echo $row['id']; ?>">Remove</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <hr>

    <!-- Weekly Target Section -->
    <h3>Weekly Target</h3>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="row mb-3">
            <div class="col-md-3">
                <label for="weekly_target" class="form-label">Weekly Target</label>
                <input type="number" step="0.01" name="weekly_target" id="weekly_target" 
                       class="form-control" value="<?php echo htmlspecialchars($target['weekly_target']); ?>" required>
            </div>
            <div class="col-md-3">
                <label for="work_days" class="form-label">No. of Working Days</label>
                <input type="number" step="1" min="1" max="7" name="work_days" id="work_days" 
                       class="form-control" value="<?php echo htmlspecialchars($target['work_days']); ?>" required>
            </div>
            <div class="col-md-3">
                <label for="daily_target" class="form-label">Daily Target (Calculated)</label>
                <input type="text" id="daily_target" class="form-control" 
                       value="<?php echo number_format($target['daily_target'], 2); ?>" readonly>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" name="update_target" class="btn btn-success">Update Target</button>
            </div>
        </div>
    </form>

    <hr>

    <a href="reports.php" class="btn btn-secondary mt-3">Return to Menu</a>
</div>

<!-- ✅ AJAX Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).on("change", ".toggle-receive", function() {
    var userId = $(this).data("id");
    var receive = $(this).is(":checked") ? 1 : 0;

    $.post("user_actions.php", { action: "toggle_receive", id: userId, receive: receive }, function(response) {
        console.log(response);
    });
});

$(document).on("click", ".remove-user", function() {
    var userId = $(this).data("id");
    if (confirm("Are you sure you want to remove this user?")) {
        $.post("user_actions.php", { action: "remove_user", id: userId }, function(response) {
            console.log(response);
            $("#user-" + userId).fadeOut();
        });
    }
});

// Calculate daily target in real-time
$("#weekly_target, #work_days").on("input", function() {
    var weeklyTarget = parseFloat($("#weekly_target").val()) || 0;
    var workDays = parseInt($("#work_days").val()) || 1;
    
    if (workDays > 0) {
        var dailyTarget = weeklyTarget / workDays;
        $("#daily_target").val(dailyTarget.toFixed(2));
    }
});
</script>

<?php include "footer.php"; ?>
