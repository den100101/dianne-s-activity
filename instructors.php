<?php
require_once "auth.php";
requirePageAccess("Instructor");

header("Location: Instructor.PHP");
exit();
