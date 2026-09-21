<?php
/** @var string $pageTitle */
/** @var string $pageDescription */
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'AMN SURE') ?></title>
<meta name="description" content="<?= h($pageDescription ?? '') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&display=swap" rel="stylesheet">
<style>
  :root{
    --green-dark:#173B2E;
    --green:#3F7A5C;
    --green-mid:#6FA989;
    --green-soft:#BFDCCA;
    --green-mist:#EEF6F1;
    --white:#FFFFFF;
    --text:#33443C;
    --text-light:#6B7C74;
    --shadow: 0 20px 45px -25px rgba(23,59,46,0.35);
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  html{scroll-behavior:smooth;}
  body{
    font-family:'Prompt', sans-serif;
    color:var(--text);
    background:var(--white);
    line-height:1.75;
    -webkit-font-smoothing:antialiased;
  }
  a{text-decoration:none;color:inherit;}
  ul{list-style:none;}
  img,svg{display:block;max-width:100%;}
  .container{width:100%;max-width:1180px;margin:0 auto;padding:0 32px;}
  .eyebrow{
    font-family:'Cormorant Garamond', serif;
    font-style:italic;font-weight:600;letter-spacing:.14em;text-transform:uppercase;
    color:var(--green);font-size:15px;display:inline-flex;align-items:center;gap:12px;
  }
  .eyebrow::before{content:"";width:34px;height:1px;background:var(--green-mid);display:inline-block;}
  h1,h2,h3{font-weight:600;color:var(--green-dark);}
  h1{font-size:clamp(30px,4vw,44px);line-height:1.25;margin:14px 0 18px;}
  p{color:var(--text-light);}
  .btn{
    display:inline-flex;align-items:center;gap:10px;padding:14px 28px;border-radius:999px;
    font-weight:500;font-size:14.5px;letter-spacing:.03em;transition:.3s ease;border:1px solid transparent;cursor:pointer;
  }
  .btn-primary{background:linear-gradient(120deg,var(--green-dark),var(--green));color:var(--white);box-shadow:var(--shadow);}
  .btn-primary:hover{transform:translateY(-2px);}
  .btn-outline{border-color:var(--green-soft);color:var(--green-dark);background:var(--white);}
  .btn-outline:hover{background:var(--green-mist);}
  .btn-danger{border-color:#e2b4b4;color:#a33;background:#fff;}
  .btn-danger:hover{background:#fdf0f0;}
  .btn-sm{padding:8px 16px;font-size:13px;}

  .logo{display:flex;align-items:center;gap:14px;min-height:72px;}
  .logo img{height:72px;width:auto;}

  header{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.97);border-bottom:1px solid rgba(23,59,46,.08);}
  .nav-wrap{display:flex;align-items:center;justify-content:space-between;padding:12px 32px;max-width:1180px;margin:0 auto;}
  nav ul{display:flex;gap:32px;align-items:center;flex-wrap:wrap;}
  nav a{font-size:15px;font-weight:400;color:var(--text);position:relative;padding:6px 0;}
  nav a:hover{color:var(--green-dark);}

  main{min-height:60vh;}

  footer{background:linear-gradient(160deg,var(--green-mist) 0%,var(--green-soft) 100%);color:var(--text);padding:50px 0 28px;margin-top:80px;}
  .footer-bottom{
    display:flex;justify-content:space-between;align-items:center;padding-top:20px;
    font-size:13px;color:var(--text-light);flex-wrap:wrap;gap:10px;
  }

  .form-field{margin-bottom:20px;}
  .form-field label{display:block;font-size:14px;font-weight:500;color:var(--green-dark);margin-bottom:6px;}
  .form-field input[type=text],
  .form-field input[type=number],
  .form-field input[type=password],
  .form-field select,
  .form-field textarea{
    width:100%;padding:12px 14px;border:1px solid var(--green-soft);border-radius:10px;
    font-family:inherit;font-size:14.5px;color:var(--text);background:var(--white);
  }
  .form-field textarea{resize:vertical;min-height:100px;}
  .form-field input:focus,.form-field select:focus,.form-field textarea:focus{outline:2px solid var(--green-mid);outline-offset:1px;}
  .alert{padding:14px 18px;border-radius:10px;font-size:14px;margin-bottom:20px;}
  .alert-error{background:#fdf0f0;color:#a33;border:1px solid #e2b4b4;}
  .alert-success{background:var(--green-mist);color:var(--green-dark);border:1px solid var(--green-soft);}

  @media(max-width:720px){
    .nav-wrap{padding:12px 20px;}
    nav ul{gap:16px;}
    .logo img{height:56px;}
    .logo{min-height:56px;}
  }
</style>
</head>
<body>

<header>
  <div class="nav-wrap">
    <a href="index.html" class="logo">
      <img src="logo.png" alt="AMN SURE">
    </a>
    <nav>
      <ul>
        <li><a href="index.html">หน้าแรก</a></li>
        <li><a href="used-equipment.php">เครื่องมือแพทย์มือสอง</a></li>
        <li><a href="index.html#contact">ติดต่อเรา</a></li>
      </ul>
    </nav>
  </div>
</header>

<main>
