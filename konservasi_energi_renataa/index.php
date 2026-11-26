<?php
require_once __DIR__ . '/includes/functions.php';
login_wajib();
$judul = 'Beranda';

$level  = user()['level'];
$siswa  = is_siswa();
$nis    = $siswa ? (int)(user()['nis'] ?? 0) : 0;
$awalBulan = date('Y-m-01');
$mulai7    = date('Y-m-d', strtotime('-6 days'));
$mulai30   = date('Y-m-d', strtotime('-29 days'));

// Siswa hanya melihat data miliknya: tambahkan syarat nis ke setiap query.
$fc = $siswa ? ' AND c.nis = :nis' : '';
$ambil = function ($sql, array $p = []) use ($pdo, $siswa, $nis) {
    if ($siswa && strpos($sql, ':nis') !== false) $p[':nis'] = $nis;
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st;
};

$jmlChecklist = (int)$ambil("SELECT COUNT(*) FROM checklist c WHERE c.tanggal >= :m $fc", [':m' => $awalBulan])->fetchColumn();
$r = $ambil("SELECT COUNT(*) total, SUM(d.status = 'Ya') ya FROM detail_checklist d
             JOIN checklist c ON c.id_checklist = d.id_checklist WHERE c.tanggal >= :m $fc", [':m' => $awalBulan])->fetch();
$persen = $r['total'] ? round($r['ya'] / $r['total'] * 100) : 0;

$terbaru = $ambil("SELECT c.id_checklist, c.tanggal, c.jam, COALESCE(u.nama, s.nama) AS nama, COALESCE(s.kelas, u.level) AS kelas,
        COUNT(d.id_detail) jml, SUM(d.status = 'Ya') ya
    FROM checklist c LEFT JOIN siswa s ON s.nis = c.nis LEFT JOIN users u ON u.id_user = c.id_user
    LEFT JOIN detail_checklist d ON d.id_checklist = c.id_checklist
    WHERE 1 = 1 $fc GROUP BY c.id_checklist ORDER BY c.tanggal DESC, c.jam DESC LIMIT 5")->fetchAll();

// Grafik: persentase "Ya" per hari selama 7 hari terakhir (siswa: hanya miliknya)
$per = [];
foreach ($ambil("SELECT c.tanggal, ROUND(100 * SUM(d.status = 'Ya') / COUNT(*)) p
    FROM checklist c JOIN detail_checklist d ON d.id_checklist = c.id_checklist
    WHERE c.tanggal >= :m $fc GROUP BY c.tanggal", [':m' => $mulai7])->fetchAll() as $x) $per[$x['tanggal']] = (int)$x['p'];
$hari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
$grafik = [];
for ($i = 6; $i >= 0; $i--) {
    $t = date('Y-m-d', strtotime("-$i days"));
    $grafik[] = [$hari[(int)date('w', strtotime($t))], $per[$t] ?? 0];
}
$adaGrafik = array_sum(array_column($grafik, 1)) > 0;

if (!$siswa) {
    $jmlSiswa     = $pdo->query('SELECT COUNT(*) FROM siswa')->fetchColumn();
    $jmlPerangkat = $pdo->query('SELECT COUNT(*) FROM perangkat')->fetchColumn();

    // Unit yang paling sering berstatus "Tidak" dalam 30 hari terakhir
    $perhatian = $ambil("SELECT p.nama_perangkat, COUNT(*) n FROM detail_checklist d
        JOIN perangkat p ON p.id_perangkat = d.id_perangkat
        JOIN checklist c ON c.id_checklist = d.id_checklist
        WHERE d.status = 'Tidak' AND c.tanggal >= :m GROUP BY p.id_perangkat ORDER BY n DESC LIMIT 5", [':m' => $mulai30])->fetchAll();

    if ($level === 'Guru') {
        $kelas = $ambil("SELECT COALESCE(u.nama, s.nama) AS kelas, ROUND(100 * SUM(d.status = 'Ya') / COUNT(*)) p FROM detail_checklist d
            JOIN checklist c ON c.id_checklist = d.id_checklist LEFT JOIN siswa s ON s.nis = c.nis LEFT JOIN users u ON u.id_user = c.id_user
            WHERE c.tanggal >= :m GROUP BY COALESCE(u.nama, s.nama) ORDER BY 1", [':m' => $mulai30])->fetchAll();
    } else {
        $akun = $pdo->query('SELECT level, COUNT(*) n FROM users GROUP BY level')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}

function kartu($ikon, $label, $nilai, $warna = 'p', $kolom = 'col-6 col-xl-3') {
    return '<div class="' . $kolom . '"><div class="card h-100"><div class="stat"><div class="stat-ic ' . $warna . '">' . ikon($ikon) . '</div>'
         . '<div><div class="stat-k">' . e($label) . '</div><div class="stat-v">' . e($nilai) . '</div></div></div></div></div>';
}

// Grafik garis (SVG sebaris, tanpa library) untuk 7 hari terakhir.
function grafik_svg(array $g) {
    $W = 640; $H = 270; $kiri = 46; $kanan = 18; $atas = 16; $bawah = 40;
    $pw = $W - $kiri - $kanan; $ph = $H - $atas - $bawah;
    $n = count($g); $langkah = $pw / max(1, $n - 1);
    $o = '<svg class="grafik-svg" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Grafik kepatuhan 7 hari terakhir">';
    $o .= '<defs><linearGradient id="areaPink" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ee5f93" stop-opacity=".35"/><stop offset="1" stop-color="#ee5f93" stop-opacity=".03"/></linearGradient></defs>';
    foreach ([0, 25, 50, 75, 100] as $t) {
        $y = $atas + $ph * (1 - $t / 100);
        $o .= '<line x1="' . $kiri . '" x2="' . ($W - $kanan) . '" y1="' . $y . '" y2="' . $y . '" class="g-grid"/>';
        $o .= '<text x="' . ($kiri - 10) . '" y="' . ($y + 4) . '" text-anchor="end" class="g-teks">' . $t . '</text>';
    }
    $pts = [];
    foreach ($g as $i => $q) {
        $pts[] = [$kiri + $i * $langkah, $atas + $ph * (1 - $q[1] / 100), $q[0], (int)$q[1]];
    }
    $garis = [];
    foreach ($pts as $q) $garis[] = round($q[0], 1) . ',' . round($q[1], 1);
    $line = implode(' ', $garis);
    $area = $kiri . ',' . ($atas + $ph) . ' ' . $line . ' ' . round($kiri + ($n - 1) * $langkah, 1) . ',' . ($atas + $ph);
    $o .= '<polygon points="' . $area . '" fill="url(#areaPink)"/>';
    $o .= '<polyline points="' . $line . '" class="g-garis"/>';
    foreach ($pts as $q) {
        $o .= '<circle cx="' . round($q[0], 1) . '" cy="' . round($q[1], 1) . '" r="5.5" class="g-titik"><title>' . e($q[2]) . ': ' . $q[3] . '%</title></circle>';
        $o .= '<text x="' . round($q[0], 1) . '" y="' . ($H - 12) . '" text-anchor="middle" class="g-teks">' . e($q[2]) . '</text>';
    }
    return $o . '</svg>';
}

require __DIR__ . '/includes/header.php';
?>
<?php // ---------- Banner ---------- ?>
<div class="hero2">
  <?= maskot(150) ?>
  <div class="hero-txt">
    <div class="hero-hi">Halo, <?= e(user()['nama']) ?> &#128075;</div>
    <div class="hero-judul">Konservasi Energi</div>
    <div class="hero-sub">Mulai dari Kita!</div>
    <div class="hero-quote">&ldquo;Gunakan energi dengan bijak, untuk masa depan yang lebih baik&rdquo; &#128150;</div>
  </div>
  <?= bohlam(110) ?>
</div>

<?php // ---------- Kartu angka ---------- ?>
<div class="row g-3">
  <?php if ($siswa): ?>
    <?= kartu('list', 'Checklist bulan ini', $jmlChecklist, 'p', 'col-12 col-md-6 col-xl-4') ?>
    <?= kartu('leaf', 'Item dijalankan (Ya)', $persen . '%', 'g', 'col-12 col-md-6 col-xl-4') ?>
    <?= kartu('check', 'Total item "Ya" bulan ini', (int)$r['ya'], 'v', 'col-12 col-md-6 col-xl-4') ?>
  <?php else: ?>
    <?= kartu('list', 'Checklist bulan ini', $jmlChecklist, 'p') ?>
    <?= kartu('leaf', 'Item dijalankan (Ya)', $persen . '%', 'g') ?>
    <?= kartu('siswa', 'Siswa', $jmlSiswa, 'v') ?>
    <?= kartu('bolt', 'Perangkat', $jmlPerangkat, 'o') ?>
  <?php endif; ?>
</div>

<?php // ---------- Grafik + Tips ---------- ?>
<div class="row g-3">
  <div class="col-lg-7"><div class="card h-100"><div class="card-body p-4">
    <div class="judul-kartu"><h6><span class="ikon-p"><?= ikon('bolt') ?></span>Grafik Kepatuhan Checklist</h6><span class="pil">7 Hari Terakhir</span></div>
    <?= grafik_svg($grafik) ?>
    <?php if (!$adaGrafik): ?><div class="muted text-center small mt-1">Belum ada data 7 hari terakhir. Buat checklist untuk mulai mengisi grafik.</div><?php endif; ?>
  </div></div></div>
  <div class="col-lg-5"><div class="card h-100 tips" id="tips"><div class="card-body p-4">
    <div class="judul-kartu"><h6><span class="ikon-p"><?= ikon('tip') ?></span>Tips Hemat Energi</h6></div>
    <ul>
      <li><span class="cek"><?= ikon('check') ?></span>Matikan lampu saat tidak digunakan</li>
      <li><span class="cek"><?= ikon('check') ?></span>Matikan komputer dan proyektor setelah dipakai</li>
      <li><span class="cek"><?= ikon('check') ?></span>Cabut charger setelah baterai penuh</li>
      <li><span class="cek"><?= ikon('check') ?></span>Manfaatkan cahaya alami di siang hari</li>
    </ul>
    <?php if (!$siswa): ?><a class="btn btn-success" href="<?= BASE_URL ?>/checklist/form.php">Buat Checklist <?= ikon('arrow') ?></a><?php endif; ?>
  </div><span class="mk-tips"><?= maskot(130) ?></span></div></div>
</div>

<?php if (!$siswa): ?>
<?php // ---------- Perlu perhatian + Pengguna / Kelas ---------- ?>
<div class="row g-3">
  <div class="col-lg-6"><div class="card h-100"><div class="card-body p-4">
    <div class="judul-kartu"><h6><span class="ikon-p"><?= ikon('bolt') ?></span>Unit perlu perhatian</h6></div>
    <?php foreach ($perhatian as $x): ?>
      <div class="py-2 border-top"><?= e($x['nama_perangkat']) ?><div class="muted small">Status Tidak <?= (int)$x['n'] ?> kali dalam 30 hari</div></div>
    <?php endforeach; if (!$perhatian): ?><div class="muted">Belum ada unit bermasalah.</div><?php endif; ?>
  </div></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-body p-4">
    <?php if ($level === 'Admin'): ?>
      <div class="judul-kartu"><h6><span class="ikon-p"><?= ikon('shield') ?></span>Pengguna</h6></div>
      <div class="muted mb-3">Admin <?= (int)($akun['Admin'] ?? 0) ?> &middot; Guru <?= (int)($akun['Guru'] ?? 0) ?> &middot; Siswa <?= (int)($akun['Siswa'] ?? 0) ?></div>
      <a class="btn btn-outline-success btn-sm" href="<?= BASE_URL ?>/users/">Kelola pengguna</a>
    <?php else: ?>
      <div class="judul-kartu"><h6><span class="ikon-p"><?= ikon('siswa') ?></span>Kepatuhan per guru (30 hari)</h6></div>
      <?php foreach ($kelas as $k): ?>
        <div class="py-2"><?= e($k['kelas']) ?> <span class="muted">&middot; <?= (int)$k['p'] ?>%</span><div class="bar-bg"><i style="width:<?= (int)$k['p'] ?>%"></i></div></div>
      <?php endforeach; if (!$kelas): ?><div class="muted">Belum ada data.</div><?php endif; ?>
    <?php endif; ?>
  </div></div></div>
</div>
<?php endif; ?>

<?php // ---------- Tabel terbaru ---------- ?>
<div class="card"><div class="card-body pb-2 px-4 pt-4"><div class="judul-kartu"><h6><span class="ikon-p"><?= ikon('list') ?></span><?= $siswa ? 'Riwayat checklist saya' : 'Checklist terbaru' ?></h6></div></div>
  <div class="table-responsive px-3 pb-3"><table class="table align-middle">
    <thead><tr><th>Tanggal</th><th>Jam</th><?php if (!$siswa): ?><th>Nama</th><th>Kelas/Peran</th><?php endif; ?><th>Ya/Total</th><th></th></tr></thead><tbody>
    <?php foreach ($terbaru as $x): ?>
      <tr><td><?= e(tgl_id($x['tanggal'])) ?></td><td><?= e(substr($x['jam'], 0, 5)) ?></td>
        <?php if (!$siswa): ?><td><?= e($x['nama']) ?></td><td><?= e($x['kelas']) ?></td><?php endif; ?>
        <td><?= (int)$x['ya'] ?>/<?= (int)$x['jml'] ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-success" href="<?= BASE_URL ?>/checklist/detail.php?id=<?= $x['id_checklist'] ?>">Buka</a></td></tr>
    <?php endforeach; if (!$terbaru): ?>
      <tr><td colspan="6" class="text-center muted py-4">Belum ada checklist. Klik "Buat Checklist" untuk memulai.</td></tr>
    <?php endif; ?>
    </tbody></table></div></div>

<?php // ---------- Ajakan ---------- ?>
<div class="cta">
  <span class="cta-ic"><?= ikon('speaker') ?></span>
  <div class="cta-txt"><b>Ayo dukung gerakan konservasi energi!</b><span class="muted">Energi yang kita hemat hari ini adalah kehidupan yang lebih baik esok hari. &#128150;</span></div>
  <?php if (!$siswa): ?><a class="btn btn-success" href="<?= BASE_URL ?>/checklist/form.php">Gabung Sekarang <?= ikon('arrow') ?></a><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
