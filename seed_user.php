<?php
require "db.php";

$users = [
    ["admin01", "Admin"],
    ["admin02", "Admin"],
    ["admin03", "Admin"],

    ["registrar01", "Registrar"],
    ["registrar02", "Registrar"],
    ["registrar03", "Registrar"],

    ["dean01", "Dean"],
    ["dean02", "Dean"],
    ["dean03", "Dean"],

    ["teacher01", "Instructor"],
    ["teacher02", "Instructor"],
    ["teacher03", "Instructor"],

    ["student01", "Student"],
    ["student02", "Student"],
    ["student03", "Student"],
];

$defaultPassword = "School@123";

$stmt = $conn->prepare(
    "INSERT INTO tbl_account (USERNAME, PASSWORD, USERTYPE)
     VALUES (?, ?, ?)"
);

foreach ($users as [$username, $role]) {
    $check = $conn->prepare(
        "SELECT ID FROM tbl_account WHERE USERNAME = ?"
    );
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo htmlspecialchars($username) . " already exists.<br>";
        $check->close();
        continue;
    }

    $hash = password_hash($defaultPassword, PASSWORD_DEFAULT);

    $stmt->bind_param("sss", $username, $hash, $role);

    if ($stmt->execute()) {
        echo htmlspecialchars($username) . " created: "
            . htmlspecialchars($role) . "<br>";
    } else {
        echo "Failed to create "
            . htmlspecialchars($username) . "<br>";
    }

    $check->close();
}

$stmt->close();

echo "<br>Finished creating accounts.";
echo "<br>Default password for new accounts: "
    . htmlspecialchars($defaultPassword);
echo "<br>Delete seed_users.php after running it.";
