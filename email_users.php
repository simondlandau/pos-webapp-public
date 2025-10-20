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

// ✅ Fetch all users
$stmt = $pdo->query("SELECT id, forename, surname, email, phone, receive 
                     FROM users ORDER BY surname, forename");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
</script>

<?php include "footer.php"; ?>

