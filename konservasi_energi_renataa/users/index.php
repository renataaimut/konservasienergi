<?php
require_once __DIR__ . '/../includes/functions.php';
login_wajib(['Admin']);
$judul = 'Pengguna';
$data = $pdo->query('SELECT u.id_user, u.username, u.nama, u.level, u.nis, s.nama AS nama_siswa
    FROM users u LEFT JOIN siswa s ON s.nis = u.nis ORDER BY u.level, u.nama')->fetchAll();
require '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Pengguna</h3>
  <a class="btn btn-success" href="form.php">Tambah pengguna</a>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th>Username</th><th>Nama</th><th>Level</th><th>Terhubung ke siswa</th><th style="width:170px"></th></tr></thead><tbody>
  <?php foreach ($data as $r): ?>
    <tr><td><?= e($r['username']) ?></td><td><?= e($r['nama']) ?></td><td><?= e($r['level']) ?></td>
      <td><?= $r['nis'] ? e($r['nama_siswa'] . ' (' . $r['nis'] . ')') : '-' ?></td>
      <td class="text-end">
        <a class="btn btn-sm btn-outline-secondary" href="form.php?id=<?= $r['id_user'] ?>">Ubah</a>
        <?php if ((int)$r['id_user'] !== (int)user()['id']): ?>
        <form method="post" action="hapus.php" class="d-inline" onsubmit="return confirm('Hapus pengguna ini?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id_user'] ?>">
          <button class="btn btn-sm btn-outline-danger">Hapus</button></form>
        <?php endif; ?>
      </td></tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<p class="text-muted small mt-2">Akun level Siswa harus dihubungkan ke data siswa agar bisa mengisi checklist.</p>
<?php require '../includes/footer.php'; ?>
