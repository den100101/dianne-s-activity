<?php
require_once __DIR__ . "/auth.php";
requirePageAccess("Instructor");

$role = currentRole();

$canAdd = in_array($role, ["Admin", "Registrar", "Dean"], true);

$message = "";
$messageType = "success";

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

/*
|--------------------------------------------------------------------------
| Add instructor
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_instructor"])) {
    if (!$canAdd) {
        http_response_code(403);
        exit("403 Forbidden: You do not have permission to add instructors.");
    }

    $salutation = trim($_POST["salutation"] ?? "");
    $firstname = trim($_POST["firstname"] ?? "");
    $middlename = trim($_POST["middlename"] ?? "");
    $lastname = trim($_POST["lastname"] ?? "");

    if ($salutation === "" || $firstname === "" || $lastname === "") {
        $message = "Please complete the required fields.";
        $messageType = "error";
    } else {
        $photoPath = "";

        if (
            isset($_FILES["photo"])
            && $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            $photo = $_FILES["photo"];

            if ($photo["error"] !== UPLOAD_ERR_OK) {
                $message = "The photo upload failed.";
                $messageType = "error";
            } elseif ($photo["size"] > 2 * 1024 * 1024) {
                $message = "The photo must not exceed 2 MB.";
                $messageType = "error";
            } else {
                $imageInfo = getimagesize($photo["tmp_name"]);
                $mime = $imageInfo["mime"] ?? "";

                $allowedTypes = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp",
                ];

                if (!$imageInfo || !isset($allowedTypes[$mime])) {
                    $message = "Only JPG, PNG, and WEBP images are allowed.";
                    $messageType = "error";
                } else {
                    $uploadDirectory = __DIR__ . "/uploads/instructors/";

                    if (
                        !is_dir($uploadDirectory)
                        && !mkdir($uploadDirectory, 0755, true)
                        && !is_dir($uploadDirectory)
                    ) {
                        $message = "Unable to create the photo upload directory.";
                        $messageType = "error";
                    } else {
                        $filename = bin2hex(random_bytes(16))
                            . "."
                            . $allowedTypes[$mime];

                        $destination = $uploadDirectory . $filename;

                        if (move_uploaded_file($photo["tmp_name"], $destination)) {
                            $photoPath = "uploads/instructors/" . $filename;
                        } else {
                            $message = "Unable to save the instructor photo.";
                            $messageType = "error";
                        }
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

            if (!$stmt) {
                error_log("Instructor insert preparation failed: " . $conn->error);
                $message = "Unable to add the instructor.";
                $messageType = "error";
            } else {
                $stmt->bind_param(
                    "sssss",
                    $salutation,
                    $firstname,
                    $middlename,
                    $lastname,
                    $photoPath
                );

                if ($stmt->execute()) {
                    $stmt->close();

                    header("Location: Instructor.PHP?success=1");
                    exit;
                }

                error_log("Instructor insert failed: " . $stmt->error);
                $message = "Unable to add the instructor. Please check the information.";
                $messageType = "error";

                $stmt->close();

                if ($photoPath !== "") {
                    $savedPhoto = __DIR__ . "/" . $photoPath;

                    if (is_file($savedPhoto)) {
                        unlink($savedPhoto);
                    }
                }
            }
        }
    }
}

if (isset($_GET["success"]) && $_GET["success"] === "1") {
    $message = "Instructor added successfully.";
    $messageType = "success";
}

/*
|--------------------------------------------------------------------------
| Retrieve instructors
|--------------------------------------------------------------------------
*/

$instructors = [];
$query = $conn->query(
    "SELECT ID, SALUTATION, FIRSTNAME, MIDDLENAME, LASTNAME, PHOTO
     FROM tbl_instructors
     ORDER BY ID DESC"
);

if ($query) {
    while ($row = $query->fetch_assoc()) {
        $instructors[] = $row;
    }
} else {
    error_log("Instructor retrieval failed: " . $conn->error);
    $message = "Unable to load instructors.";
    $messageType = "error";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructors | Blossom</title>

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
            max-width: 1200px;
            margin: auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 14px;
            font-weight: bold;
        }

        input,
        select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d5dce7;
            border-radius: 7px;
            font-size: 14px;
            background: white;
        }

        input:focus,
        select:focus {
            outline: 2px solid #dce8ff;
            border-color: #3768c5;
        }

        button,
        .back-link {
            display: inline-block;
            border: none;
            border-radius: 7px;
            padding: 11px 17px;
            background: #315fc4;
            color: white;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }

        .back-link {
            background: #e8edf5;
            color: #26364d;
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

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th,
        td {
            text-align: left;
            padding: 13px 12px;
            border-bottom: 1px solid #e8edf3;
            font-size: 14px;
        }

        th {
            color: #536176;
            background: #f8f9fc;
        }

        .profile-photo {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            background: #edf0f5;
        }

        .no-photo {
            display: inline-flex;
            width: 44px;
            height: 44px;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #edf0f5;
            color: #68758a;
            font-size: 12px;
        }

        .empty {
            padding: 25px;
            text-align: center;
            color: #68758a;
        }

        @media (max-width: 650px) {
            body {
                padding: 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }

            .card {
                padding: 17px;
            }
        }
    </style>
</head>

<body>
    <div class="container">

        <header class="page-header">
            <div>
                <h1>Instructors</h1>
                <p class="subtitle">Manage instructor information and profiles.</p>
            </div>

            <a class="back-link" href="tblcourse.php">Back to Subjects</a>
        </header>

        <?php if ($message !== ""): ?>
            <div class="message <?= e($messageType) ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($canAdd): ?>
            <section class="card">
                <h2>Add Instructor</h2>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-grid">

                        <div class="field">
                            <label for="salutation">Salutation *</label>
                            <select id="salutation" name="salutation" required>
                                <option value="">Select salutation</option>
                                <option value="Mr.">Mr.</option>
                                <option value="Ms.">Ms.</option>
                                <option value="Mrs.">Mrs.</option>
                                <option value="Dr.">Dr.</option>
                                <option value="Prof.">Prof.</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="firstname">First Name *</label>
                            <input
                                id="firstname"
                                name="firstname"
                                type="text"
                                maxlength="100"
                                required>
                        </div>

                        <div class="field">
                            <label for="middlename">Middle Name</label>
                            <input
                                id="middlename"
                                name="middlename"
                                type="text"
                                maxlength="100">
                        </div>

                        <div class="field">
                            <label for="lastname">Last Name *</label>
                            <input
                                id="lastname"
                                name="lastname"
                                type="text"
                                maxlength="100"
                                required>
                        </div>

                        <div class="field full">
                            <label for="photo">Profile Photo (JPG, PNG, WEBP; max 2 MB)</label>
                            <input
                                id="photo"
                                name="photo"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        </div>

                        <div class="field full">
                            <button type="submit" name="add_instructor">
                                Add Instructor
                            </button>
                        </div>

                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2>Instructor List</h2>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Salutation</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($instructors) > 0): ?>
                            <?php foreach ($instructors as $instructor): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $photo = trim($instructor["PHOTO"] ?? "");
                                        $photoFile = $photo !== ""
                                            ? __DIR__ . "/" . $photo
                                            : "";
                                        ?>

                                        <?php if (
                                            $photo !== ""
                                            && is_file($photoFile)
                                            && strpos($photo, "uploads/instructors/") === 0
                                        ): ?>
                                            <img
                                                class="profile-photo"
                                                src="<?= e($photo) ?>"
                                                alt="Instructor photo">
                                        <?php else: ?>
                                            <span class="no-photo">N/A</span>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= e($instructor["ID"]) ?></td>

                                    <td>
                                        <?= e(trim(
                                            ($instructor["FIRSTNAME"] ?? "") . " "
                                                . ($instructor["MIDDLENAME"] ?? "") . " "
                                                . ($instructor["LASTNAME"] ?? "")
                                        )) ?>
                                    </td>

                                    <td><?= e($instructor["SALUTATION"]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="empty">
                                    No instructors have been added yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</body>

</html>