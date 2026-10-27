<?php
require_once "auth.php";
requirePageAccess("Instructor");

$message = "";

$uploadDir = __DIR__ . "/uploads/instructors/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add-instructor"])
) {

    requireRole(["Admin", "Registrar", "Dean"]);

    $salutation = trim($_POST["salutation"] ?? "");
    $firstname = trim($_POST["firstname"] ?? "");
    $middlename = trim($_POST["middlename"] ?? "");
    $lastname = trim($_POST["lastname"] ?? "");
    $photo = null;

    if ($salutation === "" || $firstname === "" || $lastname === "") {
        $message = "Salutation, first name, and last name are required.";
    } else {
        if (
            isset($_FILES["photo"]) &&
            $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            $file = $_FILES["photo"];

            if ($file["error"] !== UPLOAD_ERR_OK) {
                $message = "Photo upload failed.";
            } elseif ($file["size"] > 2 * 1024 * 1024) {
                $message = "Photo must be 2 MB or smaller.";
            } else {
                $imageInfo = getimagesize($file["tmp_name"]);
                $mime = $imageInfo["mime"] ?? "";

                $extensions = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                if (!isset($extensions[$mime])) {
                    $message = "Only JPG, PNG, and WEBP photos are allowed.";
                } else {
                    $filename = bin2hex(random_bytes(16))
                        . "." . $extensions[$mime];

                    if (move_uploaded_file(
                        $file["tmp_name"],
                        $uploadDir . $filename
                    )) {
                        $photo = "uploads/instructors/" . $filename;
                    } else {
                        $message = "Could not save the photo.";
                    }
                }
            }
        }

        if ($message === "") {
            $stmt = $conn->prepare(
                "INSERT INTO tbl_instructors
                 (SALUTATION, FIRSTNAME, MIDDLENAME, LASTNAME, PHOTO)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "sssss",
                $salutation,
                $firstname,
                $middlename,
                $lastname,
                $photo
            );

            if ($stmt->execute()) {
                header("Location: Instructor.PHP?success=1");
                exit();
            }

            if ($photo && is_file(__DIR__ . "/" . $photo)) {
                unlink(__DIR__ . "/" . $photo);
            }

            $message = "Failed to save instructor.";
            $stmt->close();
        }
    }
}

if (isset($_GET["success"])) {
    $message = "Instructor added successfully!";
}

$canAdd = in_array(
    $_SESSION["USERTYPE"],
    ["Admin", "Registrar", "Dean"],
    true
);

$instructors = $conn->query(
    "SELECT ID, SALUTATION, FIRSTNAME, MIDDLENAME,
            LASTNAME, PHOTO
     FROM tbl_instructors
     ORDER BY LASTNAME, FIRSTNAME"
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instructors</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="app">
        <aside class="side">
            <div class="brand script">Blossom</div>

            <div class="profile">
                <div class="avatar">
                    <?= strtoupper(htmlspecialchars(substr($_SESSION["USERNAME"], 0, 1))) ?>
                </div>
                <b><?= htmlspecialchars($_SESSION["USERNAME"]) ?></b>
                <small><?= htmlspecialchars($_SESSION["USERTYPE"]) ?></small>
            </div>

            <nav class="menu">
                <a href="tblcourse.php">📚 Subjects</a>

                <?php if (
                    $_SESSION["USERTYPE"] === "Admin"
                    || $_SESSION["USERTYPE"] === "Registrar"
                ): ?>
                    <a href="term.php">📅 Term</a>
                    <a href="schoolyear.php">🏫 School Year</a>
                <?php endif; ?>

                <?php if (in_array(
                    $_SESSION["USERTYPE"],
                    ["Admin", "Registrar", "Dean"],
                    true
                )): ?>
                    <a class="active" href="Instructor.PHP">👩‍🏫 Instructors</a>
                <?php endif; ?>

                <a class="out" href="logout.php">🚪 Logout</a>
            </nav>
        </aside>

        <main class="main">
            <div class="banner">
                <h1>Instructor Management 🌸</h1>
                <p>View and manage instructor records.</p>
            </div>

            <?php if ($message !== ""): ?>
                <div class="alert">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($canAdd): ?>
                <div class="panel">
                    <h3>➕ Add Instructor</h3>

                    <form method="POST"
                        enctype="multipart/form-data"
                        class="add-form">

                        <div class="field">
                            <label>SALUTATION</label>
                            <div class="input-wrap">
                                <input name="salutation"
                                    placeholder="Mr., Ms., Dr."
                                    required>
                            </div>
                        </div>

                        <div class="field">
                            <label>FIRST NAME</label>
                            <div class="input-wrap">
                                <input name="firstname" required>
                            </div>
                        </div>

                        <div class="field">
                            <label>MIDDLE NAME</label>
                            <div class="input-wrap">
                                <input name="middlename">
                            </div>
                        </div>

                        <div class="field">
                            <label>LAST NAME</label>
                            <div class="input-wrap">
                                <input name="lastname" required>
                            </div>
                        </div>

                        <div class="field">
                            <label>PHOTO (MAX 2 MB)</label>
                            <div class="input-wrap">
                                <input type="file"
                                    name="photo"
                                    accept=".jpg,.jpeg,.png,.webp">
                            </div>
                        </div>

                        <button class="btn"
                            type="submit"
                            name="add-instructor">
                            Save Instructor
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="panel">
                <h3>👩‍🏫 Instructor List</h3>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Photo</th>
                                <th>Salutation</th>
                                <th>First Name</th>
                                <th>Middle Name</th>
                                <th>Last Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $instructors->fetch_assoc()): ?>
                                <tr>
                                    <td><?= (int)$row["ID"] ?></td>
                                    <td>
                                        <?php if ($row["PHOTO"]): ?>
                                            <img
                                                src="<?= htmlspecialchars($row["PHOTO"]) ?>"
                                                alt="Instructor photo"
                                                width="55"
                                                height="55"
                                                style="object-fit:cover;border-radius:50%">
                                        <?php else: ?>
                                            No photo
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row["SALUTATION"]) ?></td>
                                    <td><?= htmlspecialchars($row["FIRSTNAME"]) ?></td>
                                    <td><?= htmlspecialchars($row["MIDDLENAME"] ?? "") ?></td>
                                    <td><?= htmlspecialchars($row["LASTNAME"]) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>