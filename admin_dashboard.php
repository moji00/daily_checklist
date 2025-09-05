<?php
require 'config.php';
if (!is_logged_in() || !is_admin()) {
    header('Location: index.php');
    exit;
}

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

// Active users
$stmt = $mysqli->prepare("
    SELECT COUNT(DISTINCT u.id) AS active_users
    FROM users u
    JOIN user_tasks ut ON u.id = ut.user_id
    WHERE ut.task_date = ? AND u.role != 'admin'
");
$stmt->bind_param('s', $selected_date);
$stmt->execute();
$result = $stmt->get_result();
$active_users = $result ? $result->fetch_assoc()['active_users'] : 0;
$stmt->close();

// Pending tasks
$stmt = $mysqli->prepare("
    SELECT COUNT(*) AS pending_tasks
    FROM user_tasks ut
    JOIN users u ON ut.user_id = u.id
    WHERE ut.task_date = ? AND ut.status = 'pending' AND u.role != 'admin'
");
$stmt->bind_param('s', $selected_date);
$stmt->execute();
$result = $stmt->get_result();
$pending_tasks = $result ? $result->fetch_assoc()['pending_tasks'] : 0;
$stmt->close();

$stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM user_tasks WHERE task_date = ? AND status = 'completed'");
$stmt->bind_param('s', $selected_date);
$stmt->execute(); $completed = $stmt->get_result()->fetch_assoc()['c'] ?? 0; $stmt->close();

$stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM user_tasks WHERE task_date = ? AND status = 'in_progress'");
$stmt->bind_param('s', $selected_date);
$stmt->execute(); $in_progress = $stmt->get_result()->fetch_assoc()['c'] ?? 0; $stmt->close();

$stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM user_tasks WHERE task_date = ? AND status = 'pending'");
$stmt->bind_param('s', $selected_date);
$stmt->execute(); $pending = $stmt->get_result()->fetch_assoc()['c'] ?? 0; $stmt->close();

// users
$users = $mysqli->query("SELECT id,name,email,role,created_at FROM users ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

$action = $_GET['action'] ?? '';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1"> 
  <title>Admin Dashboard</title>
  <link rel="icon" type="image/png" href="assets/logo.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
  <style>
  /* Responsive tweaks */
  .topbar .brand {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  @media (max-width: 767px) {
    .topbar .container {
      flex-direction: column;
      align-items: flex-start !important;
      gap: 10px;
    }
    .topbar .brand {
      flex-direction: row;
      width: 100%;
      justify-content: flex-start;
    }
    .header-right {
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 10px;
    }
    .tasks .task-card {
      flex-direction: column !important;
      align-items: stretch !important;
    }
    .task-card .text-end {
      margin-top: 10px;
      text-align: left !important;
    }
  }
  @media (max-width: 575px) {
    .container, .container-fluid {
      padding-left: 8px !important;
      padding-right: 8px !important;
    }
    .card-ghost {
      padding: 10px !important;
    }
    .modal-dialog {
      margin: 1rem auto;
      max-width: 98vw;
    }
  }
  /* Table fix for small screens */
    .table td, .table th {
      white-space: nowrap;
      text-overflow: ellipsis;
      overflow: hidden;
      max-width: 200px;
    }
</style>
</head>
<body>

<!-- topbar -->
<nav class="navbar mb-3" style="background:linear-gradient(90deg,#343a40,#495057);color:#fff;">
  <div class="container-fluid">
    <div class="d-flex align-items-center gap-3">
      <div style="width:46px;height:46px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700">
        <img src="assets/logo.png" alt="Hospital Logo" style="height:32px;width:auto;">
      </div>
      <div>
        <div style="font-weight:700;font-size:18px">IT Daily Checklist</div>
        <div style="font-size:13px;opacity:0.9">
          <?= date('l, F jS, Y', strtotime($selected_date ?? date('Y-m-d'))) ?>
        </div>
      </div>
    </div>

    <div class="d-flex align-items-center ms-auto gap-3">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <input id="dashboardDate" type="date" class="form-control" 
          value="<?= htmlspecialchars($selected_date ?? date('Y-m-d')) ?>" 
          style="width:190px;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:#fff;color:#111;">
      <?php endif; ?>

      <div class="dropdown">
        <button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
          <?= htmlspecialchars($_SESSION['name']) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change password</a></li>
          <li><a class="dropdown-item" href="logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<div class="container-fluid px-4"><!-- ✅ full width with padding -->

  <!-- KPI cards -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card p-3 shadow-sm h-100">
        <small class="text-muted">Active Users</small>
        <h3 class="mt-2"><?= (int)$active_users ?></h3><br>
        <div class="text-muted small">Users with checklists on <?= date('M j, Y', strtotime($selected_date)) ?></div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card p-3 shadow-sm h-100">
        <small class="text-muted">Completed</small>
        <h3 class="mt-2"><?= (int)$completed ?></h3><br>
        <div class="text-muted small">Completed tasks</div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card p-3 shadow-sm h-100">
        <small>Pending Tasks (Active Users)</small>
        <h3 class="mt-2">
          <?php
            $today = date('Y-m-d');
            $stmt = $mysqli->prepare("
              SELECT COUNT(*) AS c
              FROM user_tasks ut
              JOIN users u ON ut.user_id = u.id
              WHERE ut.task_date = ? AND ut.status = 'pending' AND u.role != 'admin'
              AND ut.user_id IN (SELECT DISTINCT user_id FROM user_tasks WHERE task_date = ?)
            ");
            $stmt->bind_param('ss', $today, $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $pending_active_users = $result ? $result->fetch_assoc()['c'] : 0;
            $stmt->close();
            echo $pending_active_users;
          ?>
        </h3><br>
        <div class="text-muted small">Tasks still pending for active users</div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card p-3 shadow-sm h-100">
        <small>Actions</small>
        <div class="mt-3 d-flex flex-column gap-2 align-items-center">
          <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-person-plus"></i> Add User
          </button>
          <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#printReportModal">
            <i class="bi bi-printer"></i> Print Report
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Manage Users header -->
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Manage Users</h5>
  </div>

  <!-- Users table -->
  <div class="card p-3 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>  
            <th>Name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Created</th>
            <th>Tasks</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= htmlspecialchars($u['name']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['role']) ?></td>
            <td><?= htmlspecialchars($u['created_at']) ?></td>
            <td>
              <?php if ($u['role'] !== 'admin'): ?>
                <a href="user_task.php?user_id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Tasks">
                  <i class="bi bi-list-task"></i>
                </a>
              <?php endif; ?>
            </td>
            <td>
              <button class="btn btn-sm btn-outline-secondary" 
                      onclick='openEditModal(<?= json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' 
                      title="Edit User">
                <i class="bi bi-pencil-square"></i>
              </button>
              <?php if ($u['id'] != $_SESSION['user_id']): ?>
                <a href="delete_user.php?id=<?= (int)$u['id'] ?>" 
                  class="btn btn-sm btn-danger" 
                  onclick="return confirm('Delete user?')" 
                  title="Delete User">
                  <i class="bi bi-trash"></i>
                </a>
              <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary" disabled>
                  <i class="bi bi-person-check"></i>
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div><!-- end container-fluid -->

<!-- ✅ All modals use modal-lg for large screen scaling -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" action="add_user.php" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Full name</label><input name="name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Password</label><input name="password" type="password" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Role</label><select name="role" class="form-select"><option value="user">User</option><option value="admin">Admin</option></select></div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Create</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" action="edit_user.php" class="modal-content" id="editUserForm">
      <input type="hidden" name="id" id="editUserId">
      <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Full name</label><input name="name" id="editName" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input name="email" id="editEmail" type="email" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Password <small class="text-muted">(leave blank to keep current)</small></label><input name="password" id="editPassword" type="password" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Role</label><select name="role" id="editRole" class="form-select"><option value="user">User</option><option value="admin">Admin</option></select></div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save changes</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="changePasswordModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="post" action="change_password.php" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Change password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>">
        <div class="mb-2"><label class="form-label">Current password</label><input name="current_password" type="password" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">New password</label><input name="new_password" type="password" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Confirm new</label><input name="confirm_password" type="password" class="form-control" required></div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="printReportModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="print_report.php" method="GET">
        <div class="modal-header">
          <h5 class="modal-title">Print Completed Tasks Report</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Date From</label><input type="date" class="form-control" name="date_from" required></div>
          <div class="mb-3"><label class="form-label">Date To</label><input type="date" class="form-control" name="date_to" required></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-printer"></i> Print</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Success modal (dynamic) -->
<div class="modal fade" id="resultModal" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content" id="resultModalContent"><div class="modal-body text-center p-4"><div id="resultIcon" style="font-size:28px"></div><div id="resultMessage" class="mt-2"></div></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // date picker change -> reload admin dashboard with chosen date
  document.getElementById('dashboardDate').addEventListener('change', function(){
    const d = this.value;
    window.location.href = '?date=' + encodeURIComponent(d);
  });

  // populate Edit modal
  function openEditModal(userObj){
    // userObj is an object with id,name,email,role (passed via JSON on server)
    document.getElementById('editUserId').value = userObj.id || '';
    document.getElementById('editName').value = userObj.name || '';
    document.getElementById('editEmail').value = userObj.email || '';
    document.getElementById('editRole').value = userObj.role || 'user';
    // clear password
    document.getElementById('editPassword').value = '';
    // show modal
    var myModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    myModal.show();
  }

  // Show success modal if ?action=added|edited|deleted present
  (function(){
    const params = new URLSearchParams(window.location.search);
    const action = params.get('action');
    if(!action) return;
    let color = '#198754'; // default green
    let message = 'Done';
    let icon = '✓';
    if(action === 'added'){ color = '#198754'; message = 'User added successfully!'; icon = '✔️'; }
    if(action === 'edited'){ color = '#0d6efd'; message = 'User updated successfully!'; icon = '✎'; }
    if(action === 'deleted'){ color = '#dc3545'; message = 'User deleted successfully!'; icon = '🗑️'; }
    // set style & text
    const content = document.getElementById('resultModalContent');
    content.style.borderTop = '6px solid ' + color;
    document.getElementById('resultIcon').textContent = icon;
    document.getElementById('resultMessage').textContent = message;
    const m = new bootstrap.Modal(document.getElementById('resultModal'));
    m.show();
    // auto-close after 2.5s and remove query param
    setTimeout(()=>{ m.hide(); // remove action param from URL
      const url = new URL(window.location);
      url.searchParams.delete('action');
      history.replaceState({}, document.title, url.pathname + url.search);
    }, 2500);
  })();
</script>
</body>
</html>
