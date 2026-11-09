<?php
/*
 * Creates the FIRST administrator account (registration only makes Students).
 * Run from a terminal, never from the browser:
 *
 *     cd C:\xampp\htdocs\LostFoundTracker
 *     C:\xampp\php\php.exe database\create_admin.php
 *
 * It can also create Staff accounts for testing.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

function ask(string $q): string
{
    echo $q;
    return trim((string) fgets(STDIN));
}

$role = ucfirst(strtolower(ask('Role (Admin/Staff) [Admin]: ') ?: 'Admin'));
if (!in_array($role, ['Admin', 'Staff'], true)) {
    exit("Role must be Admin or Staff.\n");
}
$name  = ask('Full name: ');
$email = strtolower(ask('Email: '));
$pass  = ask('Password (min 8 characters, shown as you type): ');

if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8 || strlen($pass) > 72) {
    exit("Invalid input. Name 2+ characters, valid email, password 8-72 characters.\n");
}

try {
    db()->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (:n, :e, :h, :r)')
        ->execute([':n' => $name, ':e' => $email, ':h' => password_hash($pass, PASSWORD_DEFAULT), ':r' => $role]);
    echo "$role account created for $email\n";
} catch (PDOException $ex) {
    exit($ex->getCode() === '23000' ? "That email is already registered.\n" : 'Error: ' . $ex->getMessage() . "\n");
}
