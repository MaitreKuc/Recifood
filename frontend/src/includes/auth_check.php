<?php
/**
 * Vérification d'authentification de session utilisateur
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isLoggedIn() && !empty($_SESSION['is_admin']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: /auth/login.php');
        exit;
    }
}

function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUsername(): string {
    return $_SESSION['username'] ?? 'Utilisateur';
}
