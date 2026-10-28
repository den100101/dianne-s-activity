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
        :root {
            --pink: #ec4899;
            --rose: #f472b6;
            --lav: #c084fc;
            --peach: #fdba74;
            --cream: #fff5f9;
            --ink: #4a2c40;
            --muted: #9d7a90;
            --line: #fbd5e5;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            padding: 32px 36px;
            min-height: 100vh;
            font-family: "Poppins", system-ui, sans-serif;
            color: var(--ink);
            background: var(--cream);
        }

        button,
        input {
            font-family: inherit;
        }

        button:focus-visible,
        a:focus-visible,
        input:focus-visible {
            outline: 3px solid var(--lav);
            outline-offset: 3px;
        }

        .container {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
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
            color: var(--pink);
            font-size: 28px;
            font-weight: 700;
        }

        .subtitle {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .navigation {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 26px;
            padding: 10px;
            border: 2px solid var(--line);
            border-radius: 24px;
            background: #fff;
        }

        .navigation a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 17px;
            border-radius: 999px;
            text-decoration: none;
            background: #fff7fb;
            color: var(--ink);
            font-size: 13px;
            font-weight: 600;
            transition: background 0.2s, color 0.2s, transform 0.2s;
        }

        .navigation a:hover:not(.active) {
            background: #fce7f3;
            transform: translateY(-1px);
        }

        .navigation a.active {
            color: #fff;
            background: linear-gradient(135deg, var(--pink), var(--lav));
            box-shadow: 0 8px 18px rgba(236, 72, 153, 0.25);
        }

        .card {
            padding: 26px;
            margin-bottom: 26px;
            background: #fff;
            border: 2px solid var(--line);
            border-radius: 28px;
            box-shadow: 0 10px 30px rgba(236, 72, 153, 0.08);
        }

        .card h2 {
            margin: 0 0 20px;
            color: var(--pink);
            font-size: 19px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: var(--pink);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        input {
            width: 100%;
            min-width: 0;
            padding: 13px 16px;
            border: 2px solid var(--line);
            border-radius: 999px;
            background: #fff9fc;
            color: var(--ink);
            font-size: 13px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--rose);
            box-shadow: 0 0 0 4px rgba(244, 114, 182, 0.16);
        }

        .form-row {
            display: flex;
            align-items: end;
            gap: 12px;
        }

        .form-row .field {
            flex: 1;
            min-width: 0;
        }

        button {
            border: none;
            border-radius: 999px;
            padding: 13px 22px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .add-button {
            flex-shrink: 0;
            color: #fff;
            background: linear-gradient(135deg, var(--pink), var(--lav));
            box-shadow: 0 8px 18px rgba(236, 72, 153, 0.25);
        }

        .add-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(236, 72, 153, 0.32);
        }

        .delete-button {
            padding: 8px 15px;
            color: #be123c;
            background: #ffe4e6;
            font-size: 12px;
        }

        .delete-button:hover {
            color: #fff;
            background: #e11d48;
        }

        .message {
            padding: 14px 18px;
            margin-bottom: 22px;
            border: 2px solid transparent;
            border-radius: 18px;
            font-size: 13px;
        }

        .message.success {
            color: #15803d;
            background: #dcfce7;
            border-color: #86efac;
        }

        .message.error {
            color: #be123c;
            background: #ffe4e6;
            border-color: #fda4af;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .table-header h2 {
            margin: 0;
        }

        .search {
            max-width: 280px;
            background: #fff9fc;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 520px;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        th {
            padding: 0 16px 8px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 14px 16px;
            background: #fff7fb;
            border: none;
            font-size: 14px;
            vertical-align: middle;
        }

        tbody tr {
            transition: transform 0.2s;
        }

        tbody tr:hover {
            transform: translateY(-1px);
        }

        tbody tr:hover td {
            background: #fce7f3;
        }

        tbody td:first-child {
            border-radius: 18px 0 0 18px;
        }

        tbody td:last-child {
            border-radius: 0 18px 18px 0;
        }

        .empty {
            padding: 26px;
            color: var(--muted);
            text-align: center;
        }

        .delete-form {
            margin: 0;
        }

        .page-header>a {
            color: #9d174d !important;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none !important;
            padding: 10px 16px;
            border: 2px solid var(--line);
            border-radius: 999px;
            background: #fff;
            transition: background 0.2s;
        }

        .page-header>a:hover {
            background: #fce7f3;
        }

        @media (max-width: 1100px) {
            body {
                padding: 28px 22px;
            }
        }

        @media (max-width: 860px) {
            body {
                padding: 22px 16px;
            }

            .card {
                padding: 22px 18px;
            }

            .navigation {
                border-radius: 20px;
            }
        }

        @media (max-width: 650px) {
            body {
                padding: 18px 12px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            h1 {
                font-size: 24px;
            }

            .card {
                padding: 18px 14px;
                border-radius: 22px;
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

            .navigation {
                gap: 6px;
                padding: 8px;
            }

            .navigation a {
                flex: 1 1 auto;
                padding: 10px 12px;
            }

            th,
            td {
                padding: 12px 10px;
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