<?php
require 'config.php';
if(!is_logged_in()){
    header('Location:index.php');
    exit;
}

$uid = isset($_GET['view_user']) && is_admin() ? intval($_GET['view_user']) : $_SESSION['user_id'];
$today = date('Y-m-d');

// Ensure tasks exist for today
$tasks_result = $mysqli->query('SELECT * FROM tasks ORDER BY category DESC, id');
$tasks = $tasks_result ? $tasks_result->fetch_all(MYSQLI_ASSOC) : [];
foreach($tasks as $t){
    $stmt = $mysqli->prepare('INSERT IGNORE INTO user_tasks (user_id,task_id,task_date,status) VALUES (?,?,?,"pending")');
    $stmt->bind_param('iis', $uid, $t['id'], $today);
    $stmt->execute();
    $stmt->close();
}

// Fetch tasks for user
$stmt = $mysqli->prepare('SELECT ut.id AS utid, t.id AS tid, t.title, t.description, t.category, ut.status 
                          FROM user_tasks ut 
                          JOIN tasks t ON ut.task_id = t.id 
                          WHERE ut.user_id=? AND ut.task_date=? 
                          ORDER BY t.category DESC, t.id ASC');
$stmt->bind_param('is', $uid, $today);
$stmt->execute();
$res = $stmt->get_result();
$user_tasks = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// Sub-descriptions
$taskIds = array_column($user_tasks, 'tid');
$descs_by_task = [];
if (!empty($taskIds)) {
    $in = implode(',', array_map('intval', $taskIds));
    $sql = "SELECT td.id AS desc_id, td.task_id, td.text,
                   COALESCE(udc.status, 'pending') AS dstatus
            FROM task_descriptions td
            LEFT JOIN user_description_checks udc
              ON udc.task_description_id = td.id
             AND udc.user_id = ?
             AND udc.check_date = ?
            WHERE td.task_id IN ($in)
            ORDER BY td.id ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('is', $uid, $today);
    $stmt->execute();
    $resD = $stmt->get_result();
    while($row = $resD->fetch_assoc()){
        $descs_by_task[$row['task_id']][] = $row;
    }
    $stmt->close();
}

// Stats
$completed = 0;
foreach($user_tasks as $u){
    if($u['status'] === 'completed') $completed++;
}
$total = count($user_tasks);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- ✅ Added -->
  <title>User Task Manager</title>

  <!-- ✅ Tab Logo -->
<link rel="icon" type="image/png" href="assets/logo.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
      <!-- for admin only: date picker -->
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <input id="dashboardDate" type="date" class="form-control" 
          value="<?= htmlspecialchars($selected_date ?? date('Y-m-d')) ?>" 
          style="width:190px;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:#fff;color:#111;">
      <?php endif; ?>

      <!-- account dropdown -->
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

  

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>User Tasks</h3>
    <div class="d-flex gap-2">
      <!-- Home Button -->
      <a class="btn btn-outline-secondary" href="admin_dashboard.php" title="Go to Admin Dashboard">
        <i class="bi bi-house-door"></i> Home
      </a>

      <!-- Add Task Button -->
      <a class="btn btn-primary" href="#addTask" data-bs-toggle="modal">
        <i class="bi bi-plus-circle"></i> Add Task
      </a>
    </div>
  </div>


  <?php foreach($user_tasks as $ut): ?>
    <div class="task-card mb-3 p-3 border rounded bg-white">
      <div class="d-flex justify-content-between">
        <div>
          <strong><?= htmlspecialchars($ut['title']) ?></strong><br>
          <small><?= htmlspecialchars($ut['description']) ?></small><br>
          <span class="badge bg-info"><?= htmlspecialchars($ut['category']) ?></span>
        </div>
        <div>
          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTaskModal<?= $ut['tid'] ?>">
            <i class="bi bi-pencil"></i>
          </button>
          <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteTaskModal<?= $ut['tid'] ?>">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </div>
    </div>

   <!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal<?= $ut['tid'] ?>" tabindex="-1">
  <div class="modal-dialog modal-lg">
   <form method="post" action="edit_task.php" class="modal-content editTaskForm">
      <div class="modal-header">
        <h5>Edit Task</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="task_id" value="<?= $ut['tid'] ?>">

        <div class="mb-2">
          <label>Title</label>
          <input name="title" class="form-control" value="<?= htmlspecialchars($ut['title']) ?>" required>
        </div>
        <div class="mb-2">
          <label>Description</label>
          <textarea name="description" class="form-control"><?= htmlspecialchars($ut['description']) ?></textarea>
        </div>
        <div class="mb-2">
          <label>Category</label>
          <select name="category" class="form-select">
            <option <?= $ut['category']=="Servers"?"selected":"" ?>>Servers</option>
            <option <?= $ut['category']=="Security"?"selected":"" ?>>Security</option>
            <option <?= $ut['category']=="Primary Bizbox Server"?"selected":"" ?>>Primary Bizbox Server</option>
            <option <?= $ut['category']=="Backup"?"selected":"" ?>>Backup</option>
            <option <?= $ut['category']=="Network"?"selected":"" ?>>Network</option>
          </select>
        </div>

        <hr>
        <h6>Sub-Descriptions</h6>
        <div id="editSubDescContainer<?= $ut['tid'] ?>">
          <?php
          $subq = $mysqli->prepare("SELECT * FROM task_descriptions WHERE task_id=?");
          $subq->bind_param("i", $ut['tid']);
          $subq->execute();
          $subs = $subq->get_result();
          while($sd = $subs->fetch_assoc()): ?>
            <div class="input-group mb-2 subdesc-item">
              <input type="hidden" name="sub_ids[]" value="<?= $sd['id'] ?>">
              <input type="text" name="sub_descriptions[]" class="form-control" value="<?= htmlspecialchars($sd['text']) ?>">
              <button type="button" class="btn btn-outline-danger remove-subdesc"><i class="bi bi-x"></i></button>
            </div>
          <?php endwhile; ?>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="addSubDesc(<?= $ut['tid'] ?>)">
          <i class="bi bi-plus-circle"></i> 
        </button>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteTaskModal<?= $ut['tid'] ?>" tabindex="-1">
      <div class="modal-dialog">
        <form method="post" action="delete_task.php" class="modal-content">
          <div class="modal-header"><h5>Delete Task</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <input type="hidden" name="task_id" value="<?= $ut['tid'] ?>">
            <p>Are you sure you want to delete <b><?= htmlspecialchars($ut['title']) ?></b>?</p>
          </div>
          <div class="modal-footer"><button class="btn btn-danger">Delete</button></div>
        </form>
      </div>
    </div>

  <?php endforeach; ?>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTask" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" action="add_task.php" class="modal-content">
      <div class="modal-header">
        <h5>Add Task</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label>Title</label>
          <input name="title" class="form-control" required>
        </div>
        <div class="mb-2">
          <label>Description</label>
          <textarea name="description" class="form-control"></textarea>
        </div>
        <div class="mb-2">
          <label>Category</label>
          <select name="category" class="form-select">
            <option>Security</option>
            <option>Servers</option>
            <option>Primary Bizbox Server</option>
            <option>Backup</option>
            <option>Network</option>
          </select>
        </div>

        <hr>
        <h6>Sub-Descriptions</h6>
        <div id="subDescContainer">
          <div class="input-group mb-2 subdesc-item">
            <input type="text" name="sub_descriptions[]" class="form-control" placeholder="Enter sub-description">
            <button type="button" class="btn btn-outline-danger remove-subdesc"><i class="bi bi-x"></i></button>
          </div>
        </div>
        <button type="button" class="btn btn-primary btn-sm" id="addSubDesc">
          <i class="bi bi-plus-circle"></i>
        </button>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Add</button>
      </div>
    </form>
  </div>
</div>
<!-- Add Task Success Modal -->
<div class="modal fade" id="addSuccessModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body">
        <i class="bi bi-check-circle-fill text-success fs-1"></i>
        <h5 class="mt-2">Task Added!</h5>
      </div>
    </div>
  </div>
</div>

<!-- Edit Task Success Modal -->
<div class="modal fade" id="editSuccessModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body">
        <i class="bi bi-pencil-square text-primary fs-1"></i>
        <h5 class="mt-2">Task Updated!</h5>
      </div>
    </div>
  </div>
</div>

<!-- Delete Task Success Modal -->
<div class="modal fade" id="deleteSuccessModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body">
        <i class="bi bi-trash text-danger fs-1"></i>
        <h5 class="mt-2">Task Deleted!</h5>
      </div>
    </div>
  </div>
</div>

<script>
// --- Add new sub-description ---
document.getElementById('addSubDesc')?.addEventListener('click', function(){
  const container = document.getElementById('subDescContainer');
  if (!container) return;
  const div = document.createElement('div');
  div.className = 'input-group mb-2 subdesc-item';
  div.innerHTML = `
    <input type="text" name="sub_descriptions[]" class="form-control" placeholder="Enter sub-description">
    <button type="button" class="btn btn-outline-danger remove-subdesc"><i class="bi bi-x"></i></button>
  `;
  container.appendChild(div);
});

// --- Remove sub-description dynamically ---
document.addEventListener('click', function(e){
  if(e.target.closest('.remove-subdesc')){
    e.target.closest('.subdesc-item').remove();
  }
});

// --- Add sub-description inside edit form ---
function addSubDesc(taskId){
  const container = document.getElementById('editSubDescContainer'+taskId);
  const div = document.createElement('div');
  div.className = 'input-group mb-2 subdesc-item';
  div.innerHTML = `
    <input type="hidden" name="sub_ids[]" value="new">
    <input type="text" name="sub_descriptions[]" class="form-control" placeholder="Enter sub-description">
    <button type="button" class="btn btn-outline-danger remove-subdesc"><i class="bi bi-x"></i></button>
  `;
  container.appendChild(div);
}

// -------------------------
// Detect form changes before submit
// -------------------------
document.querySelectorAll('.editTaskForm').forEach(form => {
  const initialData = new FormData(form);

  function hasChanged() {
    const currentData = new FormData(form);
    for (let [key, val] of currentData.entries()) {
      if (initialData.getAll(key).join(",") !== currentData.getAll(key).join(",")) {
        return true;
      }
    }
    return false;
  }

  form.addEventListener('submit', function(e){
    if (!hasChanged()) {
      e.preventDefault(); // stop form submission
      // close modal silently
      const modalEl = form.closest('.modal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      modal.hide();
    }
  });
});

// -------------------------
// Show success modals only when triggered by PHP
// -------------------------
document.addEventListener("DOMContentLoaded", function(){
  <?php if(isset($_GET['modal'])): ?>
    var modalId = "";
    switch("<?= $_GET['modal'] ?>") {
      case "add_success": modalId = "#addSuccessModal"; break;
      case "edit_success": modalId = "#editSuccessModal"; break;
      case "delete_success": modalId = "#deleteSuccessModal"; break;
    }
    if(modalId) {
      var modal = new bootstrap.Modal(document.querySelector(modalId));
      modal.show();
      setTimeout(() => modal.hide(), 2000); // auto-close after 2s
    }
  <?php endif; ?>
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
