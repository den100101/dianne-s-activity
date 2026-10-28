<?php
require_once __DIR__ . "/includes/auth.php";
requirePageAccess("Instructor");

$role = currentRole();
