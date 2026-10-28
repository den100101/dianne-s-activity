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
            input,
            select {
                font-family: inherit;
            }

            button,
            a {
                -webkit-tap-highlight-color: transparent;
            }

            button:focus-visible,
            a:focus-visible,
            input:focus-visible,
            select:focus-visible {
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
                justify-content: space-between;
                align-items: center;
                gap: 15px;
                flex-wrap: wrap;
                margin-bottom: 26px;
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

            .form-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 18px;
            }

            .field {
                display: flex;
                flex-direction: column;
                gap: 7px;
                min-width: 0;
            }

            .field.full {
                grid-column: 1 / -1;
            }

            label {
                color: var(--pink);
                font-size: 12px;
                font-weight: 600;
                letter-spacing: 0.02em;
            }

            input,
            select {
                width: 100%;
                min-width: 0;
                padding: 13px 15px;
                border: 2px solid var(--line);
                border-radius: 16px;
                background: #fff9fc;
                color: var(--ink);
                font-size: 14px;
                transition: border-color 0.2s, box-shadow 0.2s;
            }

            input:focus,
            select:focus {
                outline: none;
                border-color: var(--rose);
                box-shadow: 0 0 0 4px rgba(244, 114, 182, 0.16);
            }

            input[type="file"] {
                padding: 10px;
                background: #fff7fb;
            }

            input[type="file"]::file-selector-button {
                padding: 8px 12px;
                margin-right: 12px;
                border: none;
                border-radius: 999px;
                background: #fce7f3;
                color: #9d174d;
                font: inherit;
                font-weight: 600;
                cursor: pointer;
            }

            button,
            .back-link {
                display: inline-flex;
                justify-content: center;
                align-items: center;
                gap: 8px;
                padding: 13px 22px;
                border: none;
                border-radius: 999px;
                background: linear-gradient(135deg, var(--pink), var(--lav));
                color: #fff;
                font-size: 14px;
                font-weight: 600;
                text-decoration: none;
                cursor: pointer;
                box-shadow: 0 8px 18px rgba(236, 72, 153, 0.25);
                transition: transform 0.2s, box-shadow 0.2s;
            }

            button:hover,
            .back-link:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 24px rgba(236, 72, 153, 0.32);
            }

            .back-link {
                background: #fff;
                border: 2px solid var(--line);
                color: #9d174d;
                box-shadow: none;
            }

            .back-link:hover {
                background: #fce7f3;
                box-shadow: none;
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

            .table-wrapper {
                width: 100%;
                overflow-x: auto;
            }

            table {
                width: 100%;
                min-width: 600px;
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

            .profile-photo,
            .no-photo {
                display: inline-flex;
                width: 46px;
                height: 46px;
                align-items: center;
                justify-content: center;
                border: 3px solid #f9a8d4;
                border-radius: 50%;
                object-fit: cover;
                object-position: center;
                background: linear-gradient(135deg, #fbcfe8, #e9d5ff);
            }

            .no-photo {
                color: #9d174d;
                font-size: 11px;
                font-weight: 600;
            }

            .empty {
                padding: 26px;
                color: var(--muted);
                text-align: center;
                border-radius: 18px;
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

                .page-header {
                    align-items: flex-start;
                }

                .card {
                    padding: 22px 18px;
                }
            }

            @media (max-width: 480px) {
                body {
                    padding: 18px 12px;
                }

                h1 {
                    font-size: 24px;
                }

                .page-header {
                    flex-direction: column;
                    align-items: stretch;
                }

                .back-link {
                    align-self: flex-start;
                }

                .form-grid {
                    grid-template-columns: minmax(0, 1fr);
                }

                .field.full {
                    grid-column: auto;
                }

                .card {
                    padding: 18px 14px;
                    border-radius: 22px;
                }

                .card h2 {
                    font-size: 17px;
                }

                .field.full button {
                    width: 100%;
                }

                th,
                td {
                    padding: 12px 10px;
                }
            }
        </style>

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