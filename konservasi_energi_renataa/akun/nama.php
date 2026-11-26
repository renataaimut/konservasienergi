<?php
require_once __DIR__ . '/../includes/functions.php';
login_wajib(['Admin', 'Guru']);      // nama siswa diatur lewat data siswa
$judul = 'Ganti Nama';

$err = [];
$nama = user()['nama'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nama = trim($_POST['nama'] ?? '');
    if ($nama === '' || panjang($nama) > 100) $err[] = 'Nama wajib diisi (maksimal 100 karakter).';

    if (!$err) {
        $pdo->prepare('UPDATE users SET nama = ? WHERE id_user = ?')->execute([$nama, (int)user()['id']]);
        $_SESSION['user']['nama'] = $nama;      // langsung berlaku, tidak perlu login ulang
        flash('success', 'Nama berhasil diganti. Checklist berikutnya memakai nama ini.');
        redirect('checklist/form.php');
    }
}
require '../includes/header.php';
?>
<div><h3 class="mb-0">Ganti Nama</h3><div class="muted">Nama ini muncul otomatis di checklist yang kamu buat.</div></div>
<?php foreach ($err as $m): ?><div class="alert alert-danger py-2 mb-0"><?= e($m) ?></div><?php endforeach; ?>
<div class="card" style="max-width:480px"><div class="card-body">
<form method="post" autocomplete="off"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Nama lengkap</label>
    <input name="nama" class="form-control" value="<?= e($nama) ?>" required maxlength="100"></div>
  <button class="btn btn-success">Simpan</button>
</form></div></div>
<?php require '../includes/footer.php'; ?>
