<?php
/**
 * Admin Panel Logout Handler
 * - Default: Log out current browser session
 * - ?all=1: Log out ALL devices & clear all trusted tokens
 * - ?others=1: Log out all other devices, keeping current browser logged in
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$all    = !empty($_GET['all']);
$others = !empty($_GET['others']);

if ($all) {
    logoutAllDevices();
    redirect('login.php?all_logged_out=1');
} elseif ($others) {
    logoutOtherDevices();
    flashSet('success', 'Logged out all other devices successfully.');
    redirect('security.php');
} else {
    doLogout();
    redirect('login.php');
}
