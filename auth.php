<?php
require_once "db.php";

if (!isset($_SESSION['USERNAME'], $_SESSION['USERTYPE'])) {
    header("Location: login.php");
    exit();
}

function requireRole(array $allowedRoles): void
{
    $role = $_SESSION['USERTYPE'] ?? '';

    if ($role === 'Admin') {
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
        'Subjects' => ['Admin', 'Registrar', 'Dean', 'Instructor', 'Student'],
        'Term' => ['Admin', 'Registrar'],
        'Instructor' => ['Admin', 'Registrar', 'Dean'],
        'School Year' => ['Admin', 'Registrar'],
        'Section' => ['Admin', 'Dean', 'Instructor'],
        'YearLevel' => ['Admin', 'Dean'],
    ];

    if (!isset($permissions[$page])) {
        http_response_code(403);
        exit("403 Forbidden: Unknown page.");
    }

    requireRole($permissions[$page]);
}
