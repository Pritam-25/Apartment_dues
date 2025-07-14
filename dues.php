<?php
include "db.php";

// Handle add/update dues
if (isset($_POST['save'])) {
    $month = $_POST['month'];
    $amount = $_POST['amount'];
    $id = $_POST['id'] ?? null;

    if ($id) {
        mysqli_query($conn, "UPDATE monthly_dues SET month = '$month', amount = '$amount' WHERE id = $id");
        header("Location: dues.php?updated=1");
        exit();
    } else {
        $check = mysqli_query($conn, "SELECT * FROM monthly_dues WHERE month = '$month'");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "UPDATE monthly_dues SET amount = '$amount' WHERE month = '$month'");
            header("Location: dues.php?updated=1");
            exit();
        } else {
            mysqli_query($conn, "INSERT INTO monthly_dues (month, amount) VALUES ('$month', '$amount')");
            header("Location: dues.php?added=1");
            exit();
        }
    }
}

// Delete single
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    mysqli_query($conn, "DELETE FROM monthly_dues WHERE id = $id");
    header("Location: dues.php?deleted=1");
    exit();
}

// Multi-delete (pure PHP)
if (isset($_POST['delete_selected']) && !empty($_POST['delete_ids'])) {
    $ids = array_map('intval', $_POST['delete_ids']);
    $idList = implode(",", $ids);
    mysqli_query($conn, "DELETE FROM monthly_dues WHERE id IN ($idList)");
    header("Location: dues.php?deleted=1");
    exit();
}

// Toast message
if (isset($_GET['deleted'])) {
    $message = "🗑️ Deleted dues entry.";
} elseif (isset($_GET['updated'])) {
    $message = "✅ Dues updated successfully.";
} elseif (isset($_GET['added'])) {
    $message = "✅ Dues added successfully.";
}

// Editing dues
$editEntry = null;
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    $res = mysqli_query($conn, "SELECT * FROM monthly_dues WHERE id = $editId");
    if (mysqli_num_rows($res) > 0) {
        $editEntry = mysqli_fetch_assoc($res);
    }
}

// Sorting
$allowedSort = ['month', 'amount', 'created_at', 'time'];
$sort = in_array($_GET['sort'] ?? '', $allowedSort) ? $_GET['sort'] : 'month';

// Custom sorting for month to maintain chronological order
if ($sort == 'month') {
    $query = "SELECT * FROM monthly_dues ORDER BY 
              CASE month
                WHEN 'January' THEN 1
                WHEN 'February' THEN 2
                WHEN 'March' THEN 3
                WHEN 'April' THEN 4
                WHEN 'May' THEN 5
                WHEN 'June' THEN 6
                WHEN 'July' THEN 7
                WHEN 'August' THEN 8
                WHEN 'September' THEN 9
                WHEN 'October' THEN 10
                WHEN 'November' THEN 11
                WHEN 'December' THEN 12
              END";
} elseif ($sort == 'time') {
    $query = "SELECT * FROM monthly_dues ORDER BY TIME(created_at)";
} else {
    $query = "SELECT * FROM monthly_dues ORDER BY $sort";
}
$dues = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dues Manager</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <?php if (isset($message)): ?>
    <div class="message"> <?= $message ?> </div>
  <?php endif; ?>

  <h2>💸 Monthly Dues Manager</h2>

  <form method="POST">
    <?php if ($editEntry): ?>
      <input type="hidden" name="id" value="<?= $editEntry['id'] ?>">
    <?php endif; ?>
    <label>Month
      
      <select name="month" required>
        <?php
        $months = ["January","February","March","April","May","June","July","August","September","October","November","December"];
        foreach ($months as $m) {
            $sel = ($editEntry && $editEntry['month'] == $m) ? 'selected' : '';
            echo "<option value='$m' $sel>$m</option>";
        }
        ?>
      </select>
    </label>

    <label>Amount (₹)
      <input type="number" name="amount" required min="1" value="<?= $editEntry['amount'] ?? '' ?>">
    </label>

    <div class="form-actions<?= $editEntry ? ' editing' : '' ?>">
      <button type="submit" name="save">
        <?= $editEntry ? 'Update Dues' : 'Save Dues' ?>
      </button>
      <?php if ($editEntry): ?>
        <a href="dues.php" class="cancel-btn">Cancel</a>
      <?php endif; ?>
    </div>
  </form>

    <h3>📜 All Monthly Dues Entries</h3> 

  <!-- Table Controls -->
   <div>
  <div class="table-controls">
    <form method="GET" class="search-sort" id="sortForm">
      <select name="sort" onchange="this.form.submit()">
        <option value="month" <?= $sort == 'month' ? 'selected' : '' ?>>Month</option>
        <option value="amount" <?= $sort == 'amount' ? 'selected' : '' ?>>Amount</option>
        <option value="created_at" <?= $sort == 'created_at' ? 'selected' : '' ?>>Date</option>
        <option value="time" <?= $sort == 'time' ? 'selected' : '' ?>>Time</option>
      </select>
    </form>
    <button type="button" class="delete-multiple" onclick="deleteSelected()" disabled id="deleteBtn">
      <img src="assets/delete.svg" alt="Delete" class="icon" width="16" height="16" />
      <span id="deleteText">Delete Selected</span>
    </button>
  </div>

  <form method="POST" id="tableForm">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th><input type="checkbox" id="selectAll"></th>
            <th>Month</th>
            <th>Amount</th>
            <th>Date</th>
            <th>Time</th>
            <th>Edit</th>
            <th>Delete</th>
          </tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($dues) > 0): ?>
          <?php while ($d = mysqli_fetch_assoc($dues)): 
              $dateTime = new DateTime($d['created_at']); ?>
            <tr>
              <td><input type="checkbox" name="delete_ids[]" value="<?= $d['id'] ?>"></td>
              <td><?= $d['month'] ?></td>
              <td><?= $d['amount'] ?></td>
              <td><?= $dateTime->format('Y-m-d') ?></td>
              <td><?= $dateTime->format('H:i:s') ?></td>
              <td><a href="?edit=<?= $d['id'] ?>" title="Edit" style="display:inline-block;">
  <img src="assets/edit.svg" alt="Edit" width="18" height="18" style="vertical-align:middle; filter: invert(32%) sepia(99%) saturate(7499%) hue-rotate(210deg) brightness(97%) contrast(101%); transition: filter 0.2s;" onmouseover="this.style.filter='invert(24%) sepia(98%) saturate(2000%) hue-rotate(210deg) brightness(90%) contrast(110%)'" onmouseout="this.style.filter='invert(32%) sepia(99%) saturate(7499%) hue-rotate(210deg) brightness(97%) contrast(101%)'" />
</a></td>
              <td>
  <a href="?delete=<?= $d['id'] ?>" title="Delete" onclick="return confirm('Delete this entry?')" style="display:inline-block;">
    <img src="assets/delete.svg" alt="Delete" width="18" height="18" style="vertical-align:middle; filter: invert(32%) sepia(99%) saturate(7499%) hue-rotate(357deg) brightness(97%) contrast(101%); transition: filter 0.2s;" onmouseover="this.style.filter='invert(24%) sepia(98%) saturate(2000%) hue-rotate(357deg) brightness(90%) contrast(110%)'" onmouseout="this.style.filter='invert(32%) sepia(99%) saturate(7499%) hue-rotate(357deg) brightness(97%) contrast(101%)'" />
  </a>
</td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="7">No dues entries yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </form>
</div>
        </div>

<script>

function deleteSelected() {
    const checkboxes = document.querySelectorAll('input[name="delete_ids[]"]:checked');
    if (checkboxes.length > 0) {
        if (confirm(`Are you sure you want to delete ${checkboxes.length} selected item(s)?`)) {
            const form = document.getElementById('tableForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete_selected';
            input.value = '1';
            form.appendChild(input);
            form.submit();
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const checkboxes = document.querySelectorAll('input[name="delete_ids[]"]');
    const deleteBtn = document.getElementById('deleteBtn');
    const deleteText = document.getElementById('deleteText'); // Target only the text span
    const selectAll = document.getElementById('selectAll');

    function updateBtns() {
        const selected = document.querySelectorAll('input[name="delete_ids[]"]:checked').length;
        deleteBtn.disabled = selected === 0;
        // Only update the text span, not the entire button content
        deleteText.textContent = selected > 0 ? `Delete Selected (${selected})` : 'Delete Selected';
    }

    checkboxes.forEach(cb => cb.addEventListener('change', updateBtns));
    updateBtns();

    // Select all functionality
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => { cb.checked = selectAll.checked; });
            updateBtns();
        });
    }
});
</script>
</body>
</html>