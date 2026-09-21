<?php
/** @var string $pageTitle */
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'Admin') ?> | AMN SURE</title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --green-dark:#173B2E; --green:#3F7A5C; --green-mid:#6FA989;
    --green-soft:#BFDCCA; --green-mist:#EEF6F1; --white:#FFFFFF;
    --text:#33443C; --text-light:#6B7C74;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Prompt',sans-serif;background:var(--green-mist);color:var(--text);line-height:1.6;}
  a{color:inherit;text-decoration:none;}
  .wrap{max-width:960px;margin:0 auto;padding:40px 24px 80px;}
  .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;flex-wrap:wrap;gap:12px;}
  .topbar h1{font-size:20px;color:var(--green-dark);font-weight:600;}
  .topbar nav{display:flex;gap:16px;font-size:14px;align-items:center;}
  .topbar nav a{color:var(--green);}
  .card{background:var(--white);border-radius:16px;padding:28px 30px;box-shadow:0 10px 30px -18px rgba(23,59,46,.25);margin-bottom:24px;}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border-radius:999px;font-weight:500;font-size:14px;border:1px solid transparent;cursor:pointer;transition:.2s;}
  .btn-primary{background:linear-gradient(120deg,var(--green-dark),var(--green));color:var(--white);}
  .btn-outline{border-color:var(--green-soft);color:var(--green-dark);background:var(--white);}
  .btn-danger{border-color:#e2b4b4;color:#a33;background:#fff;}
  .btn-sm{padding:7px 14px;font-size:13px;}
  .form-field{margin-bottom:18px;}
  .form-field label{display:block;font-size:13.5px;font-weight:500;color:var(--green-dark);margin-bottom:6px;}
  .form-field input,.form-field select,.form-field textarea{
    width:100%;padding:11px 13px;border:1px solid var(--green-soft);border-radius:10px;
    font-family:inherit;font-size:14px;color:var(--text);background:var(--white);
  }
  .form-field textarea{resize:vertical;min-height:100px;}
  .form-field input:focus,.form-field select:focus,.form-field textarea:focus{outline:2px solid var(--green-mid);outline-offset:1px;}
  .alert{padding:12px 16px;border-radius:10px;font-size:13.5px;margin-bottom:18px;}
  .alert-error{background:#fdf0f0;color:#a33;border:1px solid #e2b4b4;}
  .alert-success{background:var(--green-mist);color:var(--green-dark);border:1px solid var(--green-soft);}
  table{width:100%;border-collapse:collapse;font-size:14px;}
  th,td{text-align:left;padding:12px 10px;border-bottom:1px solid rgba(23,59,46,.08);vertical-align:middle;}
  th{color:var(--text-light);font-weight:500;font-size:12.5px;text-transform:uppercase;letter-spacing:.04em;}
  .thumb{width:52px;height:52px;border-radius:8px;object-fit:cover;background:var(--green-mist);}
  .badge{display:inline-block;font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px;}
  .badge-available{background:var(--green-mist);color:var(--green-dark);}
  .badge-sold{background:#fdf0f0;color:#a33;}
  .badge-hidden{background:#eee;color:#777;}
  .row-actions{display:flex;gap:10px;}
</style>
</head>
<body>
<div class="wrap">
