<?php
session_start();
require_once 'config/database.php';
require_once 'controllers/AuthController.php';

$auth = new AuthController($conn);

if(isset($_GET['code'])){
    $code = $_GET['code'];
    $verified = $auth->verifyEmail($code);

    if($verified){
        $_SESSION['message'] = "Email verified successfully! You can now login.";
        header('Location: views/auth/login.php');
        exit();
    } else {
        $_SESSION['error'] = "Invalid or expired verification link.";
        header('Location: views/auth/login.php');
        exit();
    }
} else {
    echo "No verification code found.";
}
?>
