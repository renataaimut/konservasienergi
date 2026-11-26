<?php
require_once __DIR__ . '/../includes/functions.php';
login_wajib();
$judul = 'Checklist';

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

require '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Checklist Konservasi Energi</h3>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-success" href="ekspor_excel.php?q=<?= urlencode($q) ?>">Ekspor Excel</a>
    <a class="btn btn-outline-success" href="ekspor_pdf.php?q=<?= urlencode($q) ?>" target="_blank">Ekspor PDF</a>
    <?php if (!is_siswa()): ?><a class="btn btn-success" href="form.php">Isi checklist</a><?php endif; ?>
  </div>
</div>
<form class="mb-3" method="get"><div class="input-group">
  <input name="q" value="<?= e($q) ?>" class="form-control" placeholder="Cari nama, kelas/peran, atau tanggal (2026-10-15)">
  <button class="btn btn-outline-success">Cari</button></div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th>Tanggal</th><th>Jam</th><th>Nama</th><th>Kelas/Peran</th><th>Item (Ya/Total)</th><th style="width:220px"></th></tr></thead><tbody>
  <?php foreach ($data as $r): ?>
    <tr><td><?= e($r['tanggal']) ?></td><td><?= e(substr($r['jam'], 0, 5)) ?></td><td><?= e($r['nama']) ?></td><td><?= e($r['kelas']) ?></td>
      <td><?= (int)$r['jml_ya'] ?>/<?= (int)$r['jml_item'] ?></td>
      <td class="text-end">
        <a class="btn btn-sm btn-success" href="detail.php?id=<?= $r['id_checklist'] ?>">Isi/Lihat</a>
        <?php if (!is_siswa()): ?><a class="btn btn-sm btn-outline-secondary" href="form.php?id=<?= $r['id_checklist'] ?>">Ubah</a><?php endif; ?>
        <?php if (user()['level'] === 'Admin'): ?>
        <form method="post" action="hapus.php" class="d-inline" onsubmit="return confirm('Hapus checklist beserta semua detailnya?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id_checklist'] ?>">
          <button class="btn btn-sm btn-outline-danger">Hapus</button></form>
        <?php endif; ?>
      </td></tr>
  <?php endforeach; if (!$data): ?>
    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada checklist.</td></tr>
  <?php endif; ?>
  </tbody></table></div></div>
<?php require '../includes/footer.php'; ?>
