<?php
require "db.php";

$message = "";

if (isset($_POST["submit-btn"])) {
  $username = trim($_POST["usernamelog"] ?? "");
  $password = $_POST["passwordlog"] ?? "";

  $stmt = $conn->prepare(
    "SELECT ID, USERNAME, PASSWORD, USERTYPE, IMAGE_PATH
         FROM tbl_account
         WHERE USERNAME = ?"
  );

  $stmt->bind_param("s", $username);
  $stmt->execute();

  $result = $stmt->get_result();

  if ($row = $result->fetch_assoc()) {
    if (password_verify($password, $row["PASSWORD"])) {
      session_regenerate_id(true);

      $_SESSION["USER_ID"] = $row["ID"];
      $_SESSION["USERNAME"] = $row["USERNAME"];
      $_SESSION["USERTYPE"] = $row["USERTYPE"];
      $_SESSION["IMAGE_PATH"] = $row["IMAGE_PATH"] ?? "";

      $stmt->close();

      header("Location: tblcourse.php");
      exit();
    }

    $message = "Password is incorrect.";
  } else {
    $message = "Username is invalid.";
  }

  $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>

  <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>

<body>
  <div class="auth">
    <div class="blob b1"></div>
    <div class="blob b2"></div>
    <div class="blob b3"></div>
    <div class="blob b4"></div>

    <div class="auth-card">
      <div class="crest">🌸</div>
      <h2 class="script">Welcome Back</h2>
      <p class="sub">Log in to see your courses 💕</p>

      <?php if ($message): ?>
        <div class="alert">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <div class="field">
          <label>USERNAME</label>
          <div class="input-wrap">
            <span class="ico">🌷</span>
            <input
              type="text"
              name="usernamelog"
              placeholder="Your username"
              required>
          </div>
        </div>

        <div class="field">
          <label>PASSWORD</label>
          <div class="input-wrap">
            <span class="ico">🔒</span>
            <input
              type="password"
              name="passwordlog"
              id="pw"
              placeholder="Your password"
              required>
            <button
              type="button"
              class="toggle"
              onclick="var i=document.getElementById('pw');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁':'🙈'">👁</button>
          </div>
        </div>

        <button class="btn" type="submit" name="submit-btn">
          Login 💖
        </button>
      </form>

      <div class="switch">
        New here? <a href="register.php">Create an account</a>
      </div>
    </div>
  </div>
</body>

</html>