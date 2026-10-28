<?php
require_once "auth.php";
requirePageAccess("Subjects");

$role = $_SESSION["USERTYPE"];
$name = $_SESSION["USERNAME"];

$canManageCourses = in_array(
  $role,
  ["Admin", "Registrar", "Dean"],
  true
);

$message = "";

// DELETE COURSE
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete-btn"])) {
  requireRole(["Admin", "Registrar", "Dean"]);

  $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

  if ($id) {
    $stmt = $conn->prepare("DELETE FROM tbl_course WHERE ID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
  }

  header("Location: tblcourse.php");
  exit();
}

// ADD COURSE
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add-btn"])) {
  requireRole(["Admin", "Registrar", "Dean"]);

  $code = strtoupper(trim($_POST["code"] ?? ""));
  $cname = trim($_POST["name"] ?? "");
  $courseUnits = filter_input(INPUT_POST, "units", FILTER_VALIDATE_INT);

  if ($code === "" || $cname === "") {
    $message = "Please complete all required fields.";
  } elseif (
    $courseUnits === false || $courseUnits === null ||
    $courseUnits < 1 || $courseUnits > 9
  ) {
    $message = "Units must be between 1 and 9.";
  } else {
    // Check for duplicate course codes
    $check = $conn->prepare(
      "SELECT ID FROM tbl_course WHERE COURSE_CODE = ?"
    );
    $check->bind_param("s", $code);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
      $message = "That course code already exists.";
    } else {
      $stmt = $conn->prepare(
        "INSERT INTO tbl_course (COURSE_CODE, COURSE_NAME, UNITS)
                 VALUES (?, ?, ?)"
      );

      $stmt->bind_param("ssi", $code, $cname, $courseUnits);

      if ($stmt->execute()) {
        $stmt->close();
        $check->close();

        header("Location: tblcourse.php");
        exit();
      } else {
        $message = "Unable to add the course.";
      }

      $stmt->close();
    }

    $check->close();
  }
}

// GET COURSE DATA
$courses = $conn->query(
  "SELECT * FROM tbl_course ORDER BY COURSE_CODE"
);

$total = $courses->num_rows;

$unitsResult = $conn->query(
  "SELECT COALESCE(SUM(UNITS), 0) AS total_units FROM tbl_course"
);
$units = $unitsResult->fetch_assoc()["total_units"];

$usersResult = $conn->query(
  "SELECT COUNT(*) AS total_users FROM tbl_account"
);
$users = $usersResult->fetch_assoc()["total_users"];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>My Courses | Blossom</title>

  <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Poppins:wght@400;500;600&display=swap"
    rel="stylesheet">

  <link rel="stylesheet" href="style.css">
</head>

<body>

  <div class="app">

    <!-- SIDEBAR -->
    <aside class="side">

      <div class="brand script">Blossom</div>

      <div class="profile">
        <?php
        $profileImage = $_SESSION["IMAGE_PATH"] ?? "";

        if (
          $profileImage === "" ||
          !is_file(__DIR__ . "/" . $profileImage)
        ) {
          $profileImage = "images/users/default.JPG";
        }
        ?>

        <img
          src="<?= htmlspecialchars($profileImage) ?>"
          alt="Profile Picture"
          class="avatar">

        <b><?= htmlspecialchars($name) ?></b>

        <small><?= htmlspecialchars($role) ?> ✨</small>
      </div>

      <nav class="menu">

        <a class="active" href="tblcourse.php">
          📚 Subjects
        </a>

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
          <a href="Instructors.php">👩‍🏫 Instructors</a>
        <?php endif; ?>

        <?php if ($canManageCourses): ?>
          <a href="#add">➕ Add Course</a>
        <?php endif; ?>

        <a class="out" href="logout.php">🚪 Logout</a>

      </nav>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">

      <div class="banner">
        <h1>Hello, <?= htmlspecialchars($name) ?>! 💖</h1>
        <p>Here's everything about your subjects today.</p>
      </div>

      <?php if ($message !== ""): ?>
        <div class="panel">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <!-- STATISTICS -->
      <div class="stats">

        <div class="stat">
          <div class="ic">📖</div>
          <b><?= (int)$total ?></b>
          <small>Subjects</small>
        </div>

        <div class="stat">
          <div class="ic">🌟</div>
          <b><?= (int)$units ?></b>
          <small>Total units</small>
        </div>

        <div class="stat">
          <div class="ic">👭</div>
          <b><?= (int)$users ?></b>
          <small>Registered users</small>
        </div>

      </div>

      <!-- ADD COURSE: ADMIN, REGISTRAR, DEAN ONLY -->
      <?php if ($canManageCourses): ?>

        <div class="panel" id="add">

          <h3>➕ Add a new subject</h3>

          <form method="POST" class="add-form">

            <div class="field">
              <label>CODE</label>
              <div class="input-wrap">
                <input
                  type="text"
                  name="code"
                  placeholder="IT107"
                  maxlength="50"
                  required>
              </div>
            </div>

            <div class="field">
              <label>SUBJECT NAME</label>
              <div class="input-wrap">
                <input
                  type="text"
                  name="name"
                  placeholder="Subject title"
                  maxlength="255"
                  required>
              </div>
            </div>

            <div class="field">
              <label>UNITS</label>
              <div class="input-wrap">
                <input
                  type="number"
                  name="units"
                  value="3"
                  min="1"
                  max="9"
                  required>
              </div>
            </div>

            <button class="btn" type="submit" name="add-btn">
              Add 🌸
            </button>

          </form>

        </div>

      <?php endif; ?>

      <!-- SUBJECT LIST -->
      <div class="panel">

        <div class="panel-head">
          <h3>📚 Subject list</h3>

          <input
            class="search"
            id="q"
            type="search"
            placeholder="🔍 Search subjects..."
            aria-label="Search subjects">
        </div>

        <div class="table-wrap">

          <table id="tbl">

            <thead>
              <tr>
                <th>#</th>
                <th>Code</th>
                <th>Subject</th>
                <th>Units</th>

                <?php if ($canManageCourses): ?>
                  <th>Action</th>
                <?php endif; ?>
              </tr>
            </thead>

            <tbody>

              <?php
              $n = 1;

              while ($c = $courses->fetch_assoc()):
              ?>

                <tr>
                  <td><?= $n++ ?></td>

                  <td>
                    <span class="badge">
                      <?= htmlspecialchars($c["COURSE_CODE"]) ?>
                    </span>
                  </td>

                  <td>
                    <?= htmlspecialchars($c["COURSE_NAME"]) ?>
                  </td>

                  <td>
                    <span class="units">
                      <?= (int)$c["UNITS"] ?> units
                    </span>
                  </td>

                  <?php if ($canManageCourses): ?>
                    <td>
                      <form method="POST"
                        onsubmit="return confirm('Delete this subject?');">

                        <input
                          type="hidden"
                          name="id"
                          value="<?= (int)$c["ID"] ?>">

                        <button
                          type="submit"
                          name="delete-btn"
                          class="del">
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
            <div class="empty">
              No subjects yet 🌷
            </div>
          <?php endif; ?>

          <div class="empty" id="noResults" hidden>
            No matching subjects found.
          </div>

        </div>

      </div>

    </main>

  </div>

  <!-- SEARCH SUBJECTS ONLY -->
  <script>
    document.getElementById("q").addEventListener("input", function() {
      const searchValue = this.value.toLowerCase();
      const rows = document.querySelectorAll("#tbl tbody tr");
      let visibleCount = 0;

      rows.forEach(function(row) {
        const matches = row.textContent.toLowerCase().includes(searchValue);

        row.style.display = matches ? "" : "none";

        if (matches) {
          visibleCount++;
        }
      });

      document.getElementById("noResults").hidden =
        visibleCount !== 0 || rows.length === 0;
    });
  </script>

</body>

</html>