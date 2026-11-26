<?php
require_once __DIR__ . '/../includes/functions.php';
login_wajib(['Admin', 'Guru']);   // siswa tidak boleh menceklist

$edit = isset($_GET['id']);
$daftarTahapan = ['SEBELUM', 'SELAMA', 'SESUDAH', '5R'];
$defKondisi    = ['SEBELUM' => 'Siap', 'SELAMA' => 'Digunakan', 'SESUDAH' => 'Mati', '5R' => 'Rapi dan bersih'];
$daftarStatus  = ['Ya', 'Tidak'];

$row = ['tanggal' => date('Y-m-d'), 'jam' => date('H:i')];
if ($edit) {
    $st = $pdo->prepare('SELECT * FROM checklist WHERE id_checklist = ?');
    $st->execute([(int)$_GET['id']]);
    $c = $st->fetch();
    if (!$c) { flash('danger', 'Checklist tidak ditemukan.'); redirect('checklist/'); }
    $row = ['tanggal' => $c['tanggal'], 'jam' => substr($c['jam'], 0, 5)];
}

$perangkat = $pdo->query('SELECT id_perangkat, nama_perangkat, jenis FROM perangkat ORDER BY jenis, nama_perangkat')->fetchAll();
$idPerangkatOk = array_map('intval', array_column($perangkat, 'id_perangkat'));

// Baris detail dari POST (untuk mengisi ulang form saat ada error)
$rows = [];
foreach (($_POST['id_perangkat'] ?? []) as $i => $pid) {
    $rows[] = [
        'id_perangkat' => $pid,
        'tahapan'      => $_POST['tahapan'][$i] ?? 'SEBELUM',
        'kondisi'      => $_POST['kondisi'][$i] ?? '',
        'status'       => $_POST['status'][$i] ?? 'Ya',
        'keterangan'   => $_POST['keterangan'][$i] ?? '',
    ];
}

$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $row['tanggal'] = $_POST['tanggal'] ?? '';
    $row['jam']     = $_POST['jam'] ?? '';

    $d = DateTime::createFromFormat('Y-m-d', $row['tanggal']);
    if (!$d || $d->format('Y-m-d') !== $row['tanggal']) $err[] = 'Tanggal tidak valid.';
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $row['jam'])) $err[] = 'Jam tidak valid.';

    $valid = [];
    if (!$edit) {
        foreach ($rows as $r) {
            if ((int)$r['id_perangkat'] > 0 && in_array((int)$r['id_perangkat'], $idPerangkatOk, true)) $valid[] = $r;
        }
        if (!$valid) $err[] = 'Tambahkan minimal satu perangkat.';
        foreach ($valid as $r) {
            if (trim($r['kondisi']) === '' || panjang(trim($r['kondisi'])) > 100) { $err[] = 'Kondisi wajib diisi (maksimal 100 karakter).'; break; }
        }
    }

    if (!$err) {
        if ($edit) {
            $pdo->prepare('UPDATE checklist SET tanggal=?, jam=? WHERE id_checklist=?')
                ->execute([$row['tanggal'], $row['jam'], (int)$_GET['id']]);
            flash('success', 'Checklist diperbarui.');
            redirect('checklist/detail.php?id=' . (int)$_GET['id']);
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO checklist (nis, id_user, tanggal, jam) VALUES (NULL,?,?,?)')
                ->execute([(int)user()['id'], $row['tanggal'], $row['jam']]);
            $idBaru = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO detail_checklist (id_checklist, id_perangkat, tahapan, kondisi, status, keterangan) VALUES (?,?,?,?,?,?)');
            foreach ($valid as $r) {
                $ins->execute([
                    $idBaru,
                    (int)$r['id_perangkat'],
                    in_array($r['tahapan'], $daftarTahapan, true) ? $r['tahapan'] : 'SEBELUM',
                    trim($r['kondisi']),
                    in_array($r['status'], $daftarStatus, true) ? $r['status'] : 'Tidak',
                    trim($r['keterangan']),
                ]);
            }
            $pdo->commit();
            flash('success', 'Checklist berhasil disimpan.');
            redirect('checklist/detail.php?id=' . $idBaru);
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err[] = 'Gagal menyimpan checklist. Pastikan database sudah diubah (migrasi_guru_checklist.sql).';
        }
    }
}
if (!$rows) $rows[] = ['id_perangkat' => '', 'tahapan' => 'SEBELUM', 'kondisi' => $defKondisi['SEBELUM'], 'status' => 'Ya', 'keterangan' => ''];

function baris_html($r, $perangkat, $daftarTahapan) {
    ob_start(); ?>
<div class="detail-row">
  <div><span class="lbl">Perangkat</span>
    <select name="id_perangkat[]" class="form-select" required>
      <option value="">-- Pilih perangkat --</option>
      <?php foreach ($perangkat as $p): ?>
        <option value="<?= (int)$p['id_perangkat'] ?>" <?= (string)$r['id_perangkat'] === (string)$p['id_perangkat'] ? 'selected' : '' ?>><?= e($p['nama_perangkat'] . ' (' . $p['jenis'] . ')') ?></option>
      <?php endforeach; ?>
    </select></div>
  <div><span class="lbl">Tahapan</span>
    <select name="tahapan[]" class="form-select js-tahapan">
      <?php foreach ($daftarTahapan as $t): ?><option <?= $r['tahapan'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
    </select></div>
  <div><span class="lbl">Kondisi</span>
    <input name="kondisi[]" class="form-control js-kondisi" value="<?= e($r['kondisi']) ?>" placeholder="Contoh: menyala, mati" maxlength="100" required></div>
  <div><span class="lbl">Dilakukan?</span>
    <select name="status[]" class="form-select">
      <option value="Ya" <?= $r['status'] === 'Ya' ? 'selected' : '' ?>>Ya</option>
      <option value="Tidak" <?= $r['status'] === 'Tidak' ? 'selected' : '' ?>>Tidak</option>
    </select></div>
  <div><span class="lbl">Keterangan</span>
    <input name="keterangan[]" class="form-control" value="<?= e($r['keterangan']) ?>" placeholder="Catatan (opsional)"></div>
  <div><button type="button" class="btn btn-outline-danger btn-hapus-baris">Hapus</button></div>
</div>
<?php return ob_get_clean(); }

$judul = $edit ? 'Ubah Checklist' : 'Isi Checklist';
require '../includes/header.php';
?>
<style>
  .detail-head, .detail-row { display: grid; grid-template-columns: 2fr 1.2fr 1.5fr 1fr 2fr auto; gap: 10px; align-items: center; }
  .detail-head { font-size: 13px; font-weight: 700; color: #6b6b80; padding: 0 2px; }
  .detail-row { margin: 8px 0; }
  .detail-row .lbl { display: none; }
  @media (max-width: 900px) {
    .detail-head { display: none; }
    .detail-row { grid-template-columns: 1fr; padding: 12px; border: 1px solid #ddd; border-radius: 10px; }
    .detail-row .lbl { display: block; font-size: 12px; font-weight: 700; margin-bottom: 2px; }
  }
</style>
<h3 class="mb-3"><?= $judul ?></h3>
<?php foreach ($err as $m): ?><div class="alert alert-danger py-2"><?= e($m) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
<form method="post"><?= csrf_field() ?>
  <div class="row g-3 mb-3">
    <div class="col-md-6"><label class="form-label">Pengisi</label>
      <input class="form-control" value="<?= e((user()['nama'] ?? user()['username']) . ' (' . user()['level'] . ')') ?>" disabled>
      <div class="form-text">Terisi otomatis dari akun yang login. Salah nama? <a href="<?= BASE_URL ?>/akun/nama.php">Ganti nama</a></div></div>
    <div class="col-md-3"><label class="form-label">Tanggal</label>
      <input type="date" name="tanggal" class="form-control" value="<?= e($row['tanggal']) ?>" required></div>
    <div class="col-md-3"><label class="form-label">Jam</label>
      <input type="time" name="jam" class="form-control" value="<?= e($row['jam']) ?>" required></div>
  </div>

<?php if (!$edit): ?>
  <h5 class="mt-4">Detail perangkat</h5>
  <div class="detail-head"><span>Perangkat</span><span>Tahapan</span><span>Kondisi</span><span>Dilakukan?</span><span>Keterangan</span><span></span></div>
  <div id="baris"><?php foreach ($rows as $r) echo baris_html($r, $perangkat, $daftarTahapan); ?></div>
  <template id="tpl"><?= baris_html(['id_perangkat' => '', 'tahapan' => 'SEBELUM', 'kondisi' => $defKondisi['SEBELUM'], 'status' => 'Ya', 'keterangan' => ''], $perangkat, $daftarTahapan) ?></template>
  <?php if (!$perangkat): ?><p class="text-muted">Belum ada data perangkat. <a href="<?= BASE_URL ?>/perangkat/form.php">Tambah perangkat</a> terlebih dahulu.</p><?php endif; ?>
  <button type="button" id="tambah" class="btn btn-outline-success mt-2">Tambah baris</button>
<?php endif; ?>

  <div class="mt-4">
    <button class="btn btn-success"><?= $edit ? 'Simpan' : 'Simpan checklist' ?></button>
    <a class="btn btn-link" href="index.php">Batal</a>
  </div>
</form></div></div>

<?php if (!$edit): ?>
<script>
const defKondisi = <?= json_encode($defKondisi) ?>;
const baris = document.getElementById('baris');
document.getElementById('tambah').addEventListener('click', () => {
  baris.appendChild(document.getElementById('tpl').content.cloneNode(true));
});
baris.addEventListener('click', e => {
  if (!e.target.classList.contains('btn-hapus-baris')) return;
  if (baris.querySelectorAll('.detail-row').length > 1) e.target.closest('.detail-row').remove();
  else alert('Minimal satu baris perangkat.');
});
// Ganti tahapan -> kondisi ikut berubah, selama belum diubah manual
baris.addEventListener('change', e => {
  if (!e.target.classList.contains('js-tahapan')) return;
  const k = e.target.closest('.detail-row').querySelector('.js-kondisi');
  if (k.value === '' || Object.values(defKondisi).includes(k.value)) k.value = defKondisi[e.target.value] || '';
});
</script>
<?php endif; ?>
<?php require '../includes/footer.php'; ?>
