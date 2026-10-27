<?php
require_once "auth.php";

// Define the available pages and their permissions.
$pages = [
    "term" => [
        "title" => "Term",
        "table" => "tbl_term",
        "column" => "TERM_NAME",
        "label" => "Term Name",
        "roles" => ["Admin", "Registrar"]
    ],
    "schoolyear" => [
        "title" => "School Year",
        "table" => "tbl_schoolyear",
        "column" => "SCHOOL_YEAR",
        "label" => "School Year",
        "roles" => ["Admin", "Registrar"]
    ],
    "section" => [
        "title" => "Section",
        "table" => "tbl_section",
        "column" => "SECTION_NAME",
        "label" => "Section Name",
        "roles" => ["Admin", "Dean", "Instructor"]
    ],
    "yearlevel" => [
        "title" => "Year Level",
        "table" => "tbl_yearlevel",
        "column" => "YEAR_LEVEL",
        "label" => "Year Level",
        "roles" => ["Admin", "Dean"]
    ]
];

// Identify the requested page.
$pageKey = $_GET["page"] ?? "";

if (!isset($pages[$pageKey])) {
    http_response_code(404);
    exit("Page not found. Please open Term, School Year, Section, or Year Level from the dashboard.");
}

$page = $pages[$pageKey];

// Enforce page permissions.
requireRole($page["roles"]);

$role = $_SESSION["USERTYPE"];
$name = $_SESSION["USERNAME"];

$canManage = in_array(
    $role,
    ["Admin", "Registrar", "Dean"],
    true
);

$message = "";

// ADD RECORD
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add"])) {
    if (!$canManage) {
        http_response_code(403);
        exit("You do not have permission to add records.");
    }

    $value = trim($_POST["value"] ?? "");

    if ($value === "") {
        $message = "Please enter a value.";
    } else {
        $table = $page["table"];
        $column = $page["column"];

        $stmt = $conn->prepare(
            "INSERT INTO `$table` (`$column`) VALUES (?)"
        );

        $stmt->bind_param("s", $value);

        if ($stmt->execute()) {
            $stmt->close();

            header("Location: schoolpage.php?page=" . urlencode($pageKey));
            exit();
        } else {
            $message = $stmt->errno === 1062
                ? "This record already exists."
                : "Unable to add record. Please check the database.";
        }

        $stmt->close();
    }
}

// DELETE RECORD
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete"])) {
    if (!$canManage) {
        http_response_code(403);
        exit("You do not have permission to delete records.");
    }

    $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

    if ($id !== false && $id !== null && $id > 0) {
        $table = $page["table"];

        $stmt = $conn->prepare(
            "DELETE FROM `$table` WHERE ID = ?"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: schoolpage.php?page=" . urlencode($pageKey));
    exit();
}

// LOAD RECORDS
$table = $page["table"];
$column = $page["column"];

$result = $conn->query(
    "SELECT ID, `$column` FROM `$table` ORDER BY ID DESC"
);

if (!$result) {
    exit("Unable to load records. Check that the database table exists.");
}

$total = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= htmlspecialchars($page["title"]) ?> | Blossom</title>

    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Poppins:wght@400;500;600&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="app">

        <aside class="side">
            <div class="brand script">Blossom</div>

            <div class="profile">
                <div class="avatar">
                    <?= strtoupper(htmlspecialchars(substr($name, 0, 1))) ?>
                </div>

                <b><?= htmlspecialchars($name) ?></b>
                <small><?= htmlspecialchars($role) ?> ✨</small>
            </div>

            <nav class="menu">
                <a href="tblcourse.php">📚 Subjects</a>

                <?php if (in_array($role, ["Admin", "Registrar"], true)): ?>
                    <a href="term.php">📅 Term</a>
                    <a href="schoolyear.php">🗓️ School Year</a>
                <?php endif; ?>

                <?php if (in_array($role, ["Admin", "Dean", "Instructor"], true)): ?>
                    <a href="section.php">🏫 Section</a>
                <?php endif; ?>

                <?php if (in_array($role, ["Admin", "Dean"], true)): ?>
                    <a href="yearlevel.php">🎓 Year Level</a>
                <?php endif; ?>

                <?php if (in_array($role, ["Admin", "Registrar", "Dean"], true)): ?>
                    <a href="Instructor.PHP">👩‍🏫 Instructors</a>
                <?php endif; ?>

                <a class="out" href="logout.php">🚪 Logout</a>
            </nav>
        </aside>

        <main class="main">

            <div class="banner">
                <h1>Hello, <?= htmlspecialchars($name) ?>! 💖</h1>
                <p>Manage your <?= htmlspecialchars(strtolower($page["title"])) ?> records.</p>
            </div>

            <div class="stats">
                <div class="stat">
                    <div class="ic">📋</div>
                    <b><?= (int)$total ?></b>
                    <small>Total <?= htmlspecialchars($page["title"]) ?> records</small>
                </div>
            </div>

            <?php if ($message !== ""): ?>
                <div class="panel">
                    <p><?= htmlspecialchars($message) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($canManage): ?>
                <div class="panel">
                    <h3>➕ Add <?= htmlspecialchars($page["title"]) ?></h3>

                    <form method="POST" class="add-form">
                        <div class="field">
                            <label><?= htmlspecialchars(strtoupper($page["label"])) ?></label>

                            <div class="input-wrap">
                                <input
                                    type="text"
                                    name="value"
                                    placeholder="Enter <?= htmlspecialchars(strtolower($page["label"])) ?>"
                                    required
                                    maxlength="100">
                            </div>
                        </div>

                        <button class="btn" type="submit" name="add">
                            Add 🌸
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-head">
                    <h3>📋 <?= htmlspecialchars($page["title"]) ?> List</h3>

                    <input
                        class="search"
                        type="search"
                        id="recordSearch"
                        placeholder="🔍 Search records..."
                        aria-label="Search records">
                </div>

                <div class="table-wrap">
                    <table id="recordTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?= htmlspecialchars($page["label"]) ?></th>

                                <?php if ($canManage): ?>
                                    <th>Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>

                        <tbody>
                            <?php $n = 1; ?>

                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $n++ ?></td>

                                    <td>
                                        <?= htmlspecialchars($row[$column]) ?>
                                    </td>

                                    <?php if ($canManage): ?>
                                        <td>
                                            <form method="POST"
                                                onsubmit="return confirm('Delete this record?');">

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int)$row["ID"] ?>">

                                                <button
                                                    class="del"
                                                    type="submit"
                                                    name="delete">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <?php if ($total === 0): ?>
                        <div class="empty">No records yet 🌷</div>
                    <?php endif; ?>

                    <div id="noResults" class="empty" hidden>
                        No matching records found.
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script>
        document.getElementById("recordSearch").addEventListener("input", function() {
            const search = this.value.toLowerCase();
            const rows = document.querySelectorAll("#recordTable tbody tr");
            let visible = 0;

            rows.forEach(function(row) {
                const matches = row.textContent.toLowerCase().includes(search);

                row.style.display = matches ? "" : "none";

                if (matches) {
                    visible++;
                }
            });

            document.getElementById("noResults").hidden =
                visible !== 0 || rows.length === 0;
        });
    </script>

</body>

</html>