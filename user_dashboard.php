<?php
require 'config.php';
if(!is_logged_in()){
    header('Location:index.php');
    exit;
}

$uid = isset($_GET['view_user']) && is_admin() ? intval($_GET['view_user']) : $_SESSION['user_id'];
$today = date('Y-m-d');

// Ensure tasks exist for today (if tasks table has some global tasks)
$tasks_result = $mysqli->query('SELECT * FROM tasks ORDER BY category DESC, id');
$tasks = $tasks_result ? $tasks_result->fetch_all(MYSQLI_ASSOC) : [];
foreach($tasks as $t){
    $stmt = $mysqli->prepare('INSERT IGNORE INTO user_tasks (user_id,task_id,task_date,status) VALUES (?,?,?,"pending")');
    $stmt->bind_param('iis', $uid, $t['id'], $today);
    $stmt->execute();
    $stmt->close();
    
    
}

// Fetch tasks for user today
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


// Fetch descriptions for all tasks and today's status per description
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

// Progress stats
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
  <title>User Dashboard</title>

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
      <div style="width:46px;height:46px;border-radius;display:flex;align-items:center;justify-content:center;font-weight:700">
        <img src="assets/logo.png" alt="Hospital Logo" style="height:32px;width:auto;">
      </div>
      <div>
        <div style="font-weight:700;font-size:18px">IT Daily Checklist</div>
        <div style="font-size:13px;opacity:0.9">
          <?= date('l, F jS, Y', strtotime($selected_date ?? date('Y-m-d'))) ?>
        </div>
      </div>
    </div>

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

<!-- Stats Cards -->
<div class="container my-4">
   <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card p-3 shadow-sm h-100">
        <small>Overall Progress</small>
        <h3 class="mt-2"><?php echo $completed . '/' . $total; ?></h3>
        <div class="progress mt-2">
          <div class="progress-bar" role="progressbar" style="width: <?php echo $total ? round($completed/$total*100) : 0; ?>%"></div>
        </div>
        <div class="text-muted small mt-2">
          <?php echo $total ? round($completed/$total*100) : 0; ?>% complete
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
    <div class="card p-3 shadow-sm h-100">
      <small>Completed Tasks</small>
      <h3 class="mt-2">
        <?php
          $stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM user_tasks WHERE user_id=? AND task_date=? AND status='completed'");
          $stmt->bind_param('is', $uid, $today);
          $stmt->execute();
          $result = $stmt->get_result();
          $completed_tasks = $result ? $result->fetch_assoc()['c'] : 0;
          $stmt->close();
          echo $completed_tasks;
        ?>
      </h3>
      <br>
      <div class="text-muted small">Tasks marked as completed</div>
    </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
    <div class="card p-3 shadow-sm h-100">
      <small>Pending Tasks</small>
      <h3 class="mt-2">
        <?php
          $stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM user_tasks WHERE user_id=? AND task_date=? AND status='pending'");
          $stmt->bind_param('is', $uid, $today);
          $stmt->execute();
          $result = $stmt->get_result();
          $pending_tasks = $result ? $result->fetch_assoc()['c'] : 0;
          $stmt->close();
          echo $pending_tasks;
        ?>
      </h3>
      <br>
      <div class="text-muted small">Tasks still pending</div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-lg-3">
      <div class="card p-3 shadow-sm h-100">
        <small>Actions</small>
        <div class="mt-2">
          <!-- Download Checklist Button with Download Icon -->
        <a class="btn btn-success w-100 mb-2" href="export_csv.php">
         <i class="bi bi-download"></i> Download Checklist
          </a>

          <!-- Reset All Button with Reset Icon -->
          <form method="post" action="reset_all.php">
           <button class="btn btn-primary w-100 mb-2">
            <i class="bi bi-arrow-counterclockwise"></i> Reset All
              </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Task list -->
  <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <h4>Daily IT Tasks</h4>
    <?php if(is_admin()): ?><a class="btn btn-primary" href="admin_dashboard.php">Admin</a><?php endif; ?>
  </div>

  <div class="tasks">
    <?php foreach($user_tasks as $ut): ?>
      <div class="task-card mb-3 d-flex align-items-start p-3 border rounded bg-white">
        <div class="flex-grow-1">
            <div style="font-weight:600"><?php echo htmlspecialchars($ut['title']); ?></div>
            <?php
            // Existing main description shown (if any) without checkbox change
           if (trim($ut['description']) !== ''): ?>
              <div class="text-muted small d-flex align-items-center mb-1">
                <input type="checkbox" class="me-2"
                      onchange="toggleMainDescription(<?php echo (int)$ut['utid']; ?>, this.checked)"
                      <?php echo $ut['status']==='completed' ? 'checked' : ''; ?>>
                <?php echo htmlspecialchars($ut['description']); ?>
              </div>
              <?php endif; ?>

            <?php if (!empty($descs_by_task[$ut['tid']])): foreach($descs_by_task[$ut['tid']] as $d): ?>
            <div class="text-muted small d-flex align-items-center mb-1">
              <input type="checkbox" class="me-2"
                    onchange="toggleDescription(<?php echo (int)$d['desc_id']; ?>, <?php echo (int)$ut['utid']; ?>, this.checked)"
                    <?php echo $d['dstatus']==='completed' ? 'checked' : ''; ?>>
              <?php echo htmlspecialchars($d['text']); ?>
            </div>
          <?php endforeach; endif; ?>

          <div class="mt-2"><span class="badge bg-info text-dark"><?php echo htmlspecialchars($ut['category']); ?></span></div>
        </div>
        <div class="text-end">
        
        
        </div>
      </div>

      <!-- Edit Task Modal -->
      <div class="modal fade" id="editTaskModal<?php echo $ut['tid']; ?>" tabindex="-1">
        <div class="modal-dialog">
          <form method="post" action="edit_task.php" class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Edit Task</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="task_id" value="<?php echo $ut['tid']; ?>">
              <div class="mb-2"><label>Title</label><input name="title" class="form-control" value="<?php echo htmlspecialchars($ut['title']); ?>" required></div>
              <div class="mb-2"><label>Description</label><textarea name="description" class="form-control"><?php echo htmlspecialchars($ut['description']); ?></textarea></div>
              <div class="mb-2"><label>Category</label>
                <select name="category" class="form-select">
                  <option value="Security" <?php if($ut['category']=="Security") echo "selected"; ?>>Security</option>
                  <option value="Server" <?php if($ut['category']=="Server") echo "selected"; ?>>Server</option>
                  <option value="Backup" <?php if($ut['category']=="Backup") echo "selected"; ?>>Backup</option>
                  <option value="Network" <?php if($ut['category']=="Network") echo "selected"; ?>>Network</option>
                </select>
              </div>

              <!-- Sub-descriptions (each line will have its own checkbox on the dashboard) -->
              <div class="mb-2">
                <label>Sub-descriptions</label>
                <div id="descList<?php echo $ut['tid']; ?>">
                  <?php
                  if (!empty($descs_by_task[$ut['tid']])):
                    foreach($descs_by_task[$ut['tid']] as $d):
                  ?>
                    <div class="input-group mb-1">
                      <input type="hidden" name="desc_id[]" value="<?php echo (int)$d['desc_id']; ?>">
                      <input type="text" name="desc_text[]" class="form-control" value="<?php echo htmlspecialchars($d['text']); ?>" placeholder="Sub-description">
                      <button type="button" class="btn btn-outline-secondary" onclick="this.parentElement.querySelector('input[name=\'desc_text[]\']').value=''">Clear</button>
                    </div>
                  <?php
                    endforeach;
                  endif;
                  ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addDescRow(<?php echo $ut['tid']; ?>)">+ Add another</button>
              </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Save Changes</button></div>
          </form>
        </div>
      </div>

      <!-- Delete Task Modal -->
      <div class="modal fade" id="deleteTaskModal<?php echo $ut['tid']; ?>" tabindex="-1">
        <div class="modal-dialog">
          <form method="post" action="delete_task.php" class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Delete Task</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="task_id" value="<?php echo $ut['tid']; ?>">
              <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($ut['title']); ?></strong>?</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button class="btn btn-danger">Delete</button>
            </div>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Add Task Modal (unchanged) -->
<div class="modal fade" id="addTask" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="add_task.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Task</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label>Title</label>
          <input name="title" class="form-control" placeholder="Enter task title" required>
        </div>
        <div class="mb-2">
          <label>Description</label>
          <textarea name="description" class="form-control" placeholder="Enter task description"></textarea>
        </div>
        <div class="mb-2">
          <label>Category</label>
          <select name="category" class="form-select" required>
            <option value="Security">Security</option>
            <option value="Server">Server</option>
            <option value="Backup">Backup</option>
            <option value="Network">Network</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Add</button>
      </div>
    </form>
  </div>
</div>


<!-- Change Password Modal (unchanged) -->
<div class="modal fade" id="changePasswordModal">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" action="change_password.php" class="modal-content">
      <div class="modal-header"><h5>Change Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
        <div class="mb-3"><label>Current Password</label><input type="password" name="current_password" class="form-control" required></div>
        <div class="mb-3"><label>New Password</label><input type="password" name="new_password" class="form-control" required></div>
        <div class="mb-3"><label>Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
</div>
<!-- Completion Modal -->
<div class="modal fade" id="completionModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">All Tasks Completed</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">Great job! You have completed all tasks for today 🎉</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleTask(utid, checked){
  fetch('toggle_task.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'utid='+encodeURIComponent(utid)+'&status='+(checked?'completed':'pending')
  }).then(r=>r.text()).then(()=>location.reload());
}

function toggleMainDescription(utid, checked){
  fetch('toggle_task.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'utid='+encodeURIComponent(utid)+'&status='+(checked?'completed':'pending')
  }).then(r=>r.text()).then(()=>location.reload());
}

// Updated function for sub-descriptions
function toggleDescription(desc_id, utid, checked){
  fetch('toggle_description.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'desc_id='+encodeURIComponent(desc_id)+'&utid='+encodeURIComponent(utid)+'&status='+(checked?'completed':'pending')
  }).then(r=>r.text()).then(()=>location.reload());
}

function addDescRow(tid){
  const wrap = document.getElementById('descList'+tid);
  if(!wrap) return;
  const div = document.createElement('div');
  div.className = 'input-group mb-1';
  div.innerHTML = '<input type="hidden" name="desc_id[]" value="0">'+
                  '<input type="text" name="desc_text[]" class="form-control" placeholder="Sub-description">'+
                  '<button type="button" class="btn btn-outline-secondary" onclick="this.parentElement.remove()">Remove</button>';
  wrap.appendChild(div);
}
</script>

</body>
</html>
