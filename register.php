<?php
require "db.php";

$message = "";
$ok = false;

if (isset($_POST['submit-btn'])) {
  $username = trim($_POST['usernamereg'] ?? '');
  $password = $_POST['passwordreg'] ?? '';
  $confirm = $_POST['confirmreg'] ?? '';

  if ($username === "" || $password === "") {
    $message = "All fields are required.";
  } elseif (strlen($password) < 6) {
    $message = "Password must be at least 6 characters.";
  } elseif ($password !== $confirm) {
    $message = "Passwords do not match.";
  } else {
    $stmt = $conn->prepare(
      "SELECT ID FROM tbl_account WHERE USERNAME = ?"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      $message = "Username already exists.";
    } else {
      $hashed = password_hash($password, PASSWORD_DEFAULT);
      $role = "Student";

      $ins = $conn->prepare(
        "INSERT INTO tbl_account (USERNAME, PASSWORD, USERTYPE)
                 VALUES (?, ?, ?)"
      );

      $ins->bind_param("sss", $username, $hashed, $role);

      if ($ins->execute()) {
        $message = "Registration successful! You can now log in.";
        $ok = true;
      } else {
        $message = "Registration failed.";
      }

      $ins->close();
    }

    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register</title>
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
      <div class="crest">🎀</div>
      <h2 class="script">Join Us</h2>
      <p class="sub">Create your account and start your journey ✨</p>
      <?php if ($message): ?><div class="alert <?= $ok ? 'ok' : '' ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <form method="post">
        <div class="field"><label>USERNAME</label>
          <div class="input-wrap"><span class="ico">🌷</span><input type="text" name="usernamereg" placeholder="Pick a cute username" required></div>
        </div>
        <div class="field"><label>PASSWORD</label>
          <div class="input-wrap"><span class="ico">🔒</span><input type="password" name="passwordreg" id="pw" placeholder="At least 6 characters" required>
            <button type="button" class="toggle" onclick="tg('pw',this)">👁</button>
          </div>
          <div class="strength"><span id="bar"></span></div>
        </div>
        <div class="field"><label>CONFIRM PASSWORD</label>
          <div class="input-wrap"><span class="ico">💗</span><input type="password" name="confirmreg" id="cf" placeholder="Type it again" required>
            <button type="button" class="toggle" onclick="tg('cf',this)">👁</button>
          </div>
        </div>
        <button class="btn" type="submit" name="submit-btn">Create Account 💖</button>
      </form>
      <div class="switch">Already have an account? <a href="login.php">Log in</a></div>
    </div>
  </div>
  <script>
    function tg(id, b) {
      var i = document.getElementById(id);
      i.type = i.type === 'password' ? 'text' : 'password';
      b.textContent = i.type === 'password' ? '👁' : '🙈'
    }
    document.getElementById('pw').addEventListener('input', function() {
      var v = this.value,
        s = 0;
      if (v.length >= 6) s++;
      if (v.length >= 10) s++;
      if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
      if (/\d/.test(v) && /\W/.test(v)) s++;
      var b = document.getElementById('bar');
      b.style.width = (s * 25) + '%';
      b.style.background = ['#fda4af', '#fbbf24', '#c084fc', '#ec4899'][Math.max(s - 1, 0)];
    });
  </script>
</body>

</html>