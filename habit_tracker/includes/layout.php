<?php
if (!isset($pageTitle)) {
    $pageTitle = "Habit Tracker";
}
?>
<!DOCTYPE html>
<html lang="en" <?= $_SESSION['dark_mode'] ? 'data-bs-theme="dark"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
</head>
<body>

<div class="container-fluid">
<div class="row">

<?php include 'sidebar.php'; ?>

<div class="col-lg-10 col-md-9 ms-auto">



<div class="main-content rounded-3">
