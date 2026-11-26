<?php
require_once __DIR__ . '/../includes/functions.php';
login_wajib();

// Query sama persis dengan checklist/index.php (termasuk pencarian & pembatasan untuk Siswa)
$q = trim($_GET['q'] ?? '');
$sql = 'SELECT c.id_checklist, c.tanggal, c.jam, s.nis, COALESCE(u.nama, s.nama) AS nama, COALESCE(s.kelas, u.level) AS kelas,
               COUNT(d.id_detail) AS jml_item, SUM(d.status = "Ya") AS jml_ya
        FROM checklist c
        LEFT JOIN siswa s ON s.nis = c.nis
        LEFT JOIN users u ON u.id_user = c.id_user
        LEFT JOIN detail_checklist d ON d.id_checklist = c.id_checklist
        WHERE (COALESCE(u.nama, s.nama) LIKE :a OR COALESCE(s.kelas, u.level) LIKE :b OR c.tanggal LIKE :c)';
$par = [':a' => "%$q%", ':b' => "%$q%", ':c' => "%$q%"];
if (is_siswa()) { $sql .= ' AND c.nis = :nis'; $par[':nis'] = (int)(user()['nis'] ?? 0); }
$sql .= ' GROUP BY c.id_checklist ORDER BY c.tanggal DESC, c.jam DESC';
$st = $pdo->prepare($sql);
$st->execute($par);
$data = $st->fetchAll();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Checklist Konservasi Energi</title>
<style>
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #222; margin: 24px; }
  h2 { margin: 0 0 4px; color: #d4607a; }
  .sub { color: #666; margin-bottom: 14px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; }
  th { background: #e8788c; color: #fff; }
  tr:nth-child(even) td { background: #fff1ef; }
  .bar { margin-bottom: 14px; }
  .bar button, .bar a { padding: 6px 12px; margin-right: 6px; font-size: 13px; cursor: pointer; }
  @media print { .bar { display: none; } body { margin: 0; } }
  @page { size: A4 landscape; margin: 14mm; }
</style>
</head>
<body>
<div class="bar">
  <button onclick="window.print()">Cetak / Simpan PDF</button>
  <a href="index.php?q=<?= urlencode($q) ?>">Kembali</a>
</div>
<h2>Checklist Konservasi Energi</h2>
<div class="sub">Dicetak: <?= e(tgl_id(date('Y-m-d'))) ?> <?= e(date('H:i')) ?><?= $q !== '' ? ' &middot; Pencarian: ' . e($q) : '' ?></div>
<table>
  <thead><tr><th>No</th><th>Tanggal</th><th>Jam</th><th>NIS</th><th>Nama</th><th>Kelas/Peran</th><th>Item (Ya/Total)</th></tr></thead>
  <tbody>
  <?php foreach ($data as $i => $r): ?>
    <tr><td><?= $i + 1 ?></td><td><?= e($r['tanggal']) ?></td><td><?= e(substr($r['jam'], 0, 5)) ?></td>
      <td><?= e($r['nis'] ?? '-') ?></td><td><?= e($r['nama']) ?></td><td><?= e($r['kelas']) ?></td>
      <td><?= (int)$r['jml_ya'] ?>/<?= (int)$r['jml_item'] ?></td></tr>
  <?php endforeach; if (!$data): ?>
    <tr><td colspan="7" style="text-align:center">Belum ada checklist.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
</body>
</html>
