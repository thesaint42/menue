<?php
/**
 * script/auth.php - Authentifizierungsfunktionen
 */

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function setLogin($user_id, $email, $firstname, $lastname, $role_id) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;
    $_SESSION['user_name'] = $firstname . ' ' . $lastname;
    $_SESSION['firstname'] = $firstname;
    $_SESSION['lastname'] = $lastname;
    $_SESSION['role_id'] = $role_id;
    $_SESSION['login_time'] = time();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserRole() {
    return $_SESSION['role_id'] ?? null;
}

function isAdmin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
}
