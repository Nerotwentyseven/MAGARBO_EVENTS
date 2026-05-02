<?php
session_name('ADMINSESSID');
session_start();
require_once 'admin_auth.php';
require_once '../db_connection.php';
$adminTheme = 'Light Mode';
$themeQuery = mysqli_query($conn, "SELECT theme FROM admin_settings WHERE id = 1 LIMIT 1");
if ($themeQuery && mysqli_num_rows($themeQuery) > 0) {
    $themeRow = mysqli_fetch_assoc($themeQuery);
    $adminTheme = $themeRow['theme'] ?? 'Light Mode';
}

// ADD / UPDATE EMPLOYEE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_employee'])) {
    $id = (int)($_POST['employee_id'] ?? 0);
    $name = trim($_POST['empName'] ?? '');
    $role = trim($_POST['empRole'] ?? '');
    $email = trim($_POST['empEmail'] ?? '');
    $phone = trim($_POST['empPhone'] ?? '');

    if ($name !== '' && $role !== '' && $email !== '' && $phone !== '') {
        $name = mysqli_real_escape_string($conn, $name);
        $role = mysqli_real_escape_string($conn, $role);
        $email = mysqli_real_escape_string($conn, $email);
        $phone = mysqli_real_escape_string($conn, $phone);

        if ($id > 0) {
            $sql = "UPDATE employees 
                    SET full_name='$name', role='$role', email='$email', phone='$phone'
                    WHERE id=$id";
        } else {
            $sql = "INSERT INTO employees (full_name, role, email, phone)
                    VALUES ('$name', '$role', '$email', '$phone')";
        }

        mysqli_query($conn, $sql);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// DELETE EMPLOYEE
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];

    if ($deleteId > 0) {
        mysqli_query($conn, "DELETE FROM employees WHERE id = $deleteId");
    }

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// FETCH EMPLOYEES
$employees = [];
$result = mysqli_query($conn, "SELECT * FROM employees ORDER BY id DESC");

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $employees[] = $row;
    }
}

$totalEmployees = count($employees);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magarbo Events - Employee List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="employees.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="sidebar.css?v=<?php echo time(); ?>">
</head>

<body class="<?php echo ($adminTheme === 'Dark Mode') ? 'dark-mode' : ''; ?>">

    <div class="ADMIN-EMPLOYEE-LIST">
        <?php include('sidebar.php'); ?>

        <main class="main-content">
            <header class="header-section">
                <div class="header-title">
                    <h1>Employee List</h1>
                    <p>Manage employee information</p>
                </div>
                <button class="add-btn" onclick="addEmployee()">
                    <span class="plus-icon">+</span> Add Employee
                </button>
            </header>

            <section class="stats-overview">
                <div class="stat-card">
                    <span class="stat-label">Total Employees</span>
                    <span class="stat-value"><?php echo $totalEmployees; ?></span>
                    <p class="stat-sub">Active employees</p>
                </div>
            </section>

            <section class="table-card">
                <div class="table-header">
                    <h2>Employee List</h2>
                    <div class="search-container">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" id="searchInput" placeholder="Search employees..." onkeyup="filterTable()">
                    </div>
                </div>

                <div class="table-wrapper">
                    <table id="empTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($employees)): ?>
                                <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td class="font-bold"><?php echo htmlspecialchars($emp['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['role']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['phone']); ?></td>
                                        <td class="actions-cell text-right">
                                            <button
                                                onclick="editEmp(
                                                    '<?php echo $emp['id']; ?>',
                                                    '<?php echo htmlspecialchars(addslashes($emp['full_name'])); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($emp['role'])); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($emp['email'])); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($emp['phone'])); ?>'
                                                )"
                                                class="action-btn">
                                                <i class="fa-solid fa-pen-to-square" style="color: #666;"></i>
                                            </button>

                                            <button onclick="deleteEmp('<?php echo $emp['id']; ?>', '<?php echo htmlspecialchars(addslashes($emp['full_name'])); ?>')" class="action-btn">
                                                <i class="fa-solid fa-trash" style="color: #ff4d4d;"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:20px;">No employees found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <div id="employeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 id="modalTitle">Add New Employee</h2>
                    <p id="modalSub">Enter the employee details below</p>
                </div>
                <span class="close" onclick="closeModal('employeeModal')">&times;</span>
            </div>

            <form id="employeeForm" method="POST">
                <input type="hidden" name="employee_id" id="employee_id">
                <input type="hidden" name="save_employee" value="1">

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="empName" name="empName" placeholder="Enter employee full name" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <input type="text" id="empRole" name="empRole" placeholder="Enter employee role" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="empEmail" name="empEmail" placeholder="Enter employee email" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" id="empPhone" name="empPhone" placeholder="+63 XXX XXX XXXX" required>
                </div>
                <button type="submit" class="save-btn">Save Employee</button>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content delete-content">
            <div class="modal-header" style="justify-content: center;">
                <h2>Delete Employee?</h2>
            </div>
            <p style="margin-bottom: 20px;">Are you sure you want to remove <strong id="deleteTarget"></strong>? This action cannot be undone.</p>
            <div class="delete-actions">
                <button class="cancel-btn" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="confirm-delete-btn" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).style.display = "flex";
        }

        function closeModal(id) {
            document.getElementById(id).style.display = "none";
        }

        function addEmployee() {
            document.getElementById("modalTitle").innerText = "Add New Employee";
            document.getElementById("modalSub").innerText = "Enter the employee details below";
            document.getElementById("employee_id").value = "";
            document.getElementById("employeeForm").reset();
            openModal("employeeModal");
        }

        function editEmp(id, name, role, email, phone) {
            document.getElementById("modalTitle").innerText = "Edit Employee";
            document.getElementById("modalSub").innerText = "Update employee information";

            document.getElementById("employee_id").value = id;
            document.getElementById("empName").value = name;
            document.getElementById("empRole").value = role;
            document.getElementById("empEmail").value = email;
            document.getElementById("empPhone").value = phone;

            openModal("employeeModal");
        }

        let currentDeleteId = 0;

        function deleteEmp(id, name) {
            currentDeleteId = id;
            document.getElementById("deleteTarget").innerText = name;
            openModal("deleteModal");
        }

        document.getElementById("confirmDeleteBtn").onclick = function () {
            if (currentDeleteId > 0) {
                window.location.href = "?delete=" + encodeURIComponent(currentDeleteId);
            }
        };

        window.onclick = function (event) {
            if (event.target.className === "modal") {
                event.target.style.display = "none";
            }
        };

        function filterTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toUpperCase();
            const table = document.getElementById("empTable");
            const tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                let found = false;
                const td = tr[i].getElementsByTagName("td");

                for (let j = 0; j < td.length - 1; j++) {
                    if (td[j] && td[j].innerText.toUpperCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }

                tr[i].style.display = found ? "" : "none";
            }
        }
    </script>
</body>
</html>