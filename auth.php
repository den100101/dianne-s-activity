<?php
require_once __DIR__ . "/db.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    !isset($_SESSION["USERNAME"], $_SESSION["USERTYPE"])
    || $_SESSION["USERNAME"] === ""
    || $_SESSION["USERTYPE"] === ""
) {
    header("Location: login.php");
    exit;
}

function currentRole(): string
{
    return $_SESSION["USERTYPE"] ?? "";
}

function requireRole(array $allowedRoles): void
{
    $role = currentRole();

    if ($role === "Admin") {
        return;
    }

    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        exit("403 Forbidden: You do not have permission to access this page.");
    }
}

function requirePageAccess(string $page): void
{
    $permissions = [
        "Subjects" => ["Admin", "Registrar", "Dean", "Instructor", "Student"],
        "Term" => ["Admin", "Registrar"],
        "Instructor" => ["Admin", "Registrar", "Dean"],
        "School Year" => ["Admin", "Registrar"],
        "Section" => ["Admin", "Dean", "Instructor"],
        "YearLevel" => ["Admin", "Dean"],
    ];

    if (!isset($permissions[$page])) {
        http_response_code(403);
        exit("403 Forbidden: Unknown page.");
    }

    requireRole($permissions[$page]);
}
