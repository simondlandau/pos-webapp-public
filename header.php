<?php
// header.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= isset($pageTitle) ? $pageTitle : 'Company POS System' ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<?= isset($additionalCSS) ? $additionalCSS : '' ?>
</head>
<body class="bg-light">
<div class="container my-4" style="max-width: 2100px;">
<header class="bg-dark text-white p-3 d-flex align-items-center">
    <img src="https://localhost:9443/svp/svplogo.png" alt="SVP Logo" style="height:40px;" class="me-2">
    <h4 class="m-0"><?= isset($headerTitle) ? $headerTitle : 'POS Company Name' ?></h4>
</header>
