<?php
require_once __DIR__ . "/auth.php";

$role = currentRole();

/*
|--------------------------------------------------------------------------
| Page configuration
|--------------------------------------------------------------------------
*/

$pages = [
    "term" => [
        "title" => "Term",
        "table" => "tbl_term",
        "column" => "TERM_NAME",
        "label" => "Term Name",
        "roles" => ["Admin", "Registrar"],
    ],

    "schoolyear" => [
        "title" => "School Year",
        "table" => "tbl_schoolyear",
        "column" => "SCHOOL_YEAR",
        "label" => "School Year",
        "roles" => ["Admin", "Registrar"],
    ],

    "section" => [
        "title" => "Section",
        "table" => "tbl_section",
        "column" => "SECTION_NAME",
        "label" => "Section Name",
        "roles" => ["Admin", "Dean", "Instructor"],
    ],

    "yearlevel" => [
        "title" => "Year Level",
        "table" => "tbl_yearlevel",
        "column" => "YEAR_LEVEL",
        "label" => "Year Level",
        "roles" => ["Admin", "Dean"],
    ],
];

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$pageKey = strtolower(trim($_GET["page"] ?? "term"));

if (!isset($pages[$pageKey])) {
    http_response_code(404);
    exit("404 Not Found: The requested page does not exist.");
}

$page = $pages[$pageKey];

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

requireRole($page["roles"]);

$canManage = in_array($role, ["Admin", "Registrar", "Dean"], true);

$message = "";
$messageType = "success";

/*
|--------------------------------------------------------------------------
| Add record
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_record"])) {
    if (!$canManage) {
        http_response_code(403);
        exit("403 Forbidden: You do not have permission to add this record.");
    }

    $value = trim($_POST["record_name"] ?? "");

    if ($value === "") {
        $message = "Please enter " . strtolower($page["label"]) . ".";
        $messageType = "error";
    } else {
        if ($pageKey === "yearlevel") {
            /*
             * This assumes YEAR_LEVEL and YEARLEVEL_NAME both exist
             * and should receive the same submitted value.
             */
            $stmt = $conn->prepare(
                "INSERT INTO tbl_yearlevel (YEAR_LEVEL, YEARLEVEL_NAME)
                 VALUES (?, ?)"
            );

            if ($stmt) {
                $stmt->bind_param("ss", $value, $value);
            }
        } else {
            $table = $page["table"];
            $column = $page["column"];

            $stmt = $conn->prepare(
                "INSERT INTO `$table` (`$column`) VALUES (?)"
            );

            if ($stmt) {
                $stmt->bind_param("s", $value);
            }
        }

        if (!isset($stmt) || !$stmt) {
            error_log("Record insert preparation failed: " . $conn->error);
            $message = "Unable to prepare the insert. Please check your database table structure.";
            $messageType = "error";
        } else {
            if ($stmt->execute()) {
                $stmt->close();

                header(
                    "Location: schoolpage.php?page="
                        . rawurlencode($pageKey)
                        . "&success=added"
                );
                exit;
            }

            error_log("Record insert failed: " . $stmt->error);

            if ($conn->errno === 1062) {
                $message = "This record already exists.";
            } else {
                $message = "Unable to add the record. Please check your database table structure.";
            }

            $messageType = "error";
            $stmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Delete record
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_record"])) {
    if (!$canManage) {
        http_response_code(403);
        exit("403 Forbidden: You do not have permission to delete this record.");
    }

    $id = filter_input(INPUT_POST, "record_id", FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        $message = "Invalid record ID.";
        $messageType = "error";
    } else {
        $table = $page["table"];

        $stmt = $conn->prepare(
            "DELETE FROM `$table` WHERE ID = ?"
        );

        if (!$stmt) {
            error_log("Record delete preparation failed: " . $conn->error);
            $message = "Unable to delete the record.";
            $messageType = "error";
        } else {
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $stmt->close();

                header(
                    "Location: schoolpage.php?page="
                        . rawurlencode($pageKey)
                        . "&success=deleted"
                );
                exit;
            }

            error_log("Record delete failed: " . $stmt->error);
            $message = "Unable to delete the record.";
            $messageType = "error";

            $stmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Flash messages after redirect
|--------------------------------------------------------------------------
*/

if (isset($_GET["success"])) {
    if ($_GET["success"] === "added") {
        $message = $page["title"] . " added successfully.";
        $messageType = "success";
    } elseif ($_GET["success"] === "deleted") {
        $message = $page["title"] . " deleted successfully.";
        $messageType = "success";
    }
}

/*
|--------------------------------------------------------------------------
| Retrieve records
|--------------------------------------------------------------------------
*/

$records = [];
$table = $page["table"];
$column = $page["column"];

$query = $conn->query(
    "SELECT ID, `$column` AS record_name
     FROM `$table`
     ORDER BY ID DESC"
);

if ($query) {
    while ($row = $query->fetch_assoc()) {
        $records[] = $row;
    }
} else {
    error_log("Record retrieval failed: " . $conn->error);
    $message = "Unable to load " . strtolower($page["title"]) . " records.";
    $messageType = "error";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page["title"]) ?> | Blossom</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #243047;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 24px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .subtitle {
            margin: 0;
            color: #68758a;
        }

        .navigation {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 24px;
        }

        .navigation a {
            padding: 10px 14px;
            border-radius: 7px;
            text-decoration: none;
            background: #e8edf5;
            color: #26364d;
            font-size: 14px;
        }

        .navigation a.active {
            color: white;
            background: #315fc4;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 3px 14px rgba(0, 0, 0, 0.05);
        }

        .card h2 {
            margin-top: 0;
            font-size: 20px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d5dce7;
            border-radius: 7px;
            font-size: 14px;
        }

        input:focus {
            outline: 2px solid #dce8ff;
            border-color: #3768c5;
        }

        .form-row {
            display: flex;
            align-items: end;
            gap: 12px;
        }

        .form-row .field {
            flex: 1;
        }

        button {
            border: none;
            border-radius: 7px;
            padding: 11px 16px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }

        .add-button {
            color: white;
            background: #315fc4;
        }

        .delete-button {
            color: #a51d2d;
            background: #fde8eb;
        }

        .message {
            padding: 13px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .message.success {
            color: #17683b;
            background: #e5f7ec;
        }

        .message.error {
            color: #9d2020;
            background: #fdeaea;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .table-header h2 {
            margin: 0;
        }

        .search {
            max-width: 280px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 520px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px 12px;
            border-bottom: 1px solid #e8edf3;
            text-align: left;
            font-size: 14px;
        }

        th {
            color: #536176;
            background: #f8f9fc;
        }

        .empty {
            padding: 24px;
            text-align: center;
            color: #68758a;
        }

        .delete-form {
            margin: 0;
        }

        @media (max-width: 650px) {
            body {
                padding: 16px;
            }

            .card {
                padding: 17px;
            }

            .form-row {
                flex-direction: column;
                align-items: stretch;
            }

            .form-row button {
                width: 100%;
            }

            .search {
                max-width: none;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container">

        <header class="page-header">
            <div>
                <h1><?= e($page["title"]) ?></h1>
                <p class="subtitle">
                    Manage <?= e(strtolower($page["title"])) ?> records.
                </p>
            </div>

            <a href="tblcourse.php" style="text-decoration:none;color:#315fc4;">
                Back to Subjects
            </a>
        </header>

        <nav class="navigation" aria-label="School management pages">
            <?php if (in_array($role, ["Admin", "Registrar"], true)): ?>
                <a
                    href="schoolpage.php?page=term"
                    class="<?= $pageKey === "term" ? "active" : "" ?>">Term</a>

                <a
                    href="schoolpage.php?page=schoolyear"
                    class="<?= $pageKey === "schoolyear" ? "active" : "" ?>">School Year</a>
            <?php endif; ?>

            <?php if (in_array($role, ["Admin", "Dean", "Instructor"], true)): ?>
                <a
                    href="schoolpage.php?page=section"
                    class="<?= $pageKey === "section" ? "active" : "" ?>">Section</a>
            <?php endif; ?>

            <?php if (in_array($role, ["Admin", "Dean"], true)): ?>
                <a
                    href="schoolpage.php?page=yearlevel"
                    class="<?= $pageKey === "yearlevel" ? "active" : "" ?>">Year Level</a>
            <?php endif; ?>
        </nav>

        <?php if ($message !== ""): ?>
            <div class="message <?= e($messageType) ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <section class="card">
                <h2>Add <?= e($page["title"]) ?></h2>

                <form method="POST">
                    <div class="form-row">
                        <div class="field">
                            <label for="record_name">
                                <?= e($page["label"]) ?> *
                            </label>

                            <input
                                id="record_name"
                                name="record_name"
                                type="text"
                                maxlength="100"
                                required
                                placeholder="Enter <?= e(strtolower($page["label"])) ?>">
                        </div>

                        <button
                            class="add-button"
                            type="submit"
                            name="add_record"
                            value="1">
                            Add Record
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="card">
            <div class="table-header">
                <h2><?= e($page["title"]) ?> List</h2>

                <input
                    class="search"
                    id="recordSearch"
                    type="search"
                    placeholder="Search records..."
                    aria-label="Search records">
            </div>

            <div class="table-wrapper">
                <table id="recordsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?= e($page["label"]) ?></th>

                            <?php if ($canManage): ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($records) > 0): ?>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?= e($record["ID"]) ?></td>
                                    <td><?= e($record["record_name"]) ?></td>

                                    <?php if ($canManage): ?>
                                        <td>
                                            <form
                                                class="delete-form"
                                                method="POST"
                                                onsubmit="return confirm('Are you sure you want to delete this record?');">
                                                <input
                                                    type="hidden"
                                                    name="record_id"
                                                    value="<?= e($record["ID"]) ?>">

                                                <button
                                                    class="delete-button"
                                                    type="submit"
                                                    name="delete_record"
                                                    value="1">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td
                                    colspan="<?= $canManage ? 3 : 2 ?>"
                                    class="empty">
                                    No <?= e(strtolower($page["title"])) ?> records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </div>

    <script>
        const searchInput = document.getElementById("recordSearch");
        const tableRows = document.querySelectorAll("#recordsTable tbody tr");

        searchInput.addEventListener("input", function() {
            const searchValue = this.value.toLowerCase().trim();

            tableRows.forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(searchValue) ?
                    "" :
                    "none";
            });
        });
    </script>
</body>

</html>