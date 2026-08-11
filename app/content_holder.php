<?php
$page = $_GET['page'] ?? 'main';
$allowed = ['main', 'products', 'profile', 'users'];
if (!in_array($page, $allowed, true)) {
    $page = 'main';
}
$path = __DIR__ . '/../pages/' . $page . '.php';
if (file_exists($path)) {
    include $path;
} else {
    echo '<div class="panel"><p>Page not found.</p></div>';
}
