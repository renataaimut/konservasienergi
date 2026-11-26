<?php
// Kerangka halaman: sidebar (kiri) + navbar (atas) + area isi. Dipakai semua halaman setelah login.
require_once __DIR__ . '/functions.php';
$u     = user();
$f     = flash();
$aktif = halaman_aktif();
$level = $u['level'] ?? '';
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($judul ?? 'Konservasi Energi') ?> | Konservasi Energi</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/app.css?v=<?= @filemtime(__DIR__ . '/../assets/css/app.css') ?>" rel="stylesheet">
  <script>
    // Dijalankan sebelum halaman tampil agar tidak "berkedip": ambil tema & status sidebar yang tersimpan.
    try {
      var t = localStorage.getItem('tema'); if (t) document.documentElement.setAttribute('data-bs-theme', t);
      if (localStorage.getItem('sidebar') === 'tutup') document.documentElement.classList.add('side-closed');
    } catch (e) {}
  </script>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= BASE_URL ?>/index.php"><?= logo(104) ?><b>Konservasi Energi</b><small>Save Energy &middot; For a Better Future</small></a>
    <?= menu_item('dashboard', 'index.php', 'home', 'Beranda', $aktif) ?>
    <?php if (in_array($level, ['Admin', 'Guru'], true)): ?>
      <?= menu_item('siswa', 'siswa/', 'siswa', 'Siswa', $aktif) ?>
      <?= menu_item('perangkat', 'perangkat/', 'bolt', 'Perangkat', $aktif) ?>
      <?= menu_item('checklist', 'checklist/', 'list', 'Checklist', $aktif) ?>
    <?php else: ?>
      <?= menu_item('checklist', 'checklist/', 'list', 'Checklist saya', $aktif) ?>
    <?php endif; ?>
    <?php if ($level === 'Admin'): ?>
      <?= menu_item('users', 'users/', 'shield', 'Pengguna', $aktif) ?>
    <?php endif; ?>
    <?= menu_item('tips', 'index.php#tips', 'tip', 'Tips Hemat Energi', $aktif) ?>

    <!-- Menu Pengaturan bisa dibuka/tutup (Bootstrap collapse); berisi Ganti password dan Mode gelap -->
    <button class="menu menu-btn" type="button" data-bs-toggle="collapse" data-bs-target="#subSetting" aria-expanded="<?= $aktif === 'akun' ? 'true' : 'false' ?>">
      <?= ikon('set') ?><span>Pengaturan</span><span class="chv"><?= ikon('chev') ?></span>
    </button>
    <div class="collapse sub2 <?= $aktif === 'akun' ? 'show' : '' ?>" id="subSetting">
      <?php if (in_array($level, ['Admin', 'Guru'], true)): ?><?= menu_item('akun', 'akun/nama.php', 'user', 'Ganti nama', $aktif) ?><?php endif; ?>
      <?= menu_item('akun', 'akun/password.php', 'key', 'Ganti password', $aktif) ?>
      <button class="menu menu-btn" type="button" id="btnTema" role="switch" aria-checked="false">
        <?= ikon('moon') ?><span>Mode gelap</span><span class="sw"></span>
      </button>
    </div>

    <?= menu_item('tentang', 'tentang.php', 'info', 'Tentang Kami', $aktif) ?>
    <div class="garis"></div>
    <?= str_replace('class="menu"', 'class="menu menu-keluar"', menu_item('keluar', 'logout.php', 'out', 'Logout', $aktif)) ?>
    <svg class="leaf-deco" viewBox="0 0 220 160" aria-hidden="true">
      <path d="M18 160C6 118 28 78 72 58c12 42-8 82-54 102z" fill="#ff9fc2"/>
      <path d="M78 160C66 110 98 70 152 54c9 48-22 88-74 106z" fill="#ffb4d1"/>
      <path d="M132 160c-6-42 14-72 58-88 6 37-15 73-58 88z" fill="#ffc9de"/>
    </svg>
  </aside>
  <div class="backdrop" id="backdrop"></div>

  <div class="app-main">
    <header class="topbar">
      <button class="burger" id="btnSidebar" type="button" aria-label="Buka atau tutup sidebar"><?= ikon('menu') ?></button>
      <strong class="judul-halaman"><?= e($judul ?? 'Beranda') ?></strong>
      <form class="cari" action="<?= BASE_URL ?>/checklist/" method="get" role="search">
        <?= ikon('search') ?>
        <input type="search" name="q" placeholder="Cari checklist (nama, kelas, tanggal)..." aria-label="Cari checklist">
      </form>
      <div class="dropdown topbar-r">
        <button class="user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="user-nama d-none d-md-block"><b><?= e($u['nama'] ?? '') ?></b><small><?= e($level) ?></small></span>
          <span class="avatar"><?= ikon('user') ?></span>
          <?= ikon('chev') ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
          <li class="dropdown-header d-md-none"><?= e($u['nama'] ?? '') ?> (<?= e($level) ?>)</li>
          <?php if (in_array($level, ['Admin', 'Guru'], true)): ?><li><a class="dropdown-item" href="<?= BASE_URL ?>/akun/nama.php"><?= ikon('user') ?> Ganti nama</a></li><?php endif; ?>
          <li><a class="dropdown-item" href="<?= BASE_URL ?>/akun/password.php"><?= ikon('key') ?> Ganti password</a></li>
          <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php"><?= ikon('out') ?> Logout</a></li>
        </ul>
      </div>
    </header>
    <main class="page">
<?php if ($f): ?>
      <div class="alert alert-<?= e($f[0]) ?> alert-dismissible fade show"><?= e($f[1]) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
