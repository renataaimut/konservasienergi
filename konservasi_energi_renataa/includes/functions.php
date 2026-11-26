<?php
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect($path) { header('Location: ' . BASE_URL . '/' . $path); exit; }

function flash($type = null, $msg = null) {
    if ($type !== null) { $_SESSION['flash'] = [$type, $msg]; return null; }
    if (isset($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

function csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Permintaan tidak valid.');
    }
}

function user() { return $_SESSION['user'] ?? null; }
function is_siswa() { return (user()['level'] ?? '') === 'Siswa'; }

function login_wajib(array $level = []) {
    if (empty($_SESSION['user'])) redirect('login.php');
    if ($level && !in_array($_SESSION['user']['level'], $level, true)) {
        http_response_code(403);
        exit('Anda tidak punya akses ke halaman ini.');
    }
}

// Ambil checklist; siswa hanya boleh membuka miliknya sendiri (username = NIS)
function ambil_checklist(PDO $pdo, $id) {
    $st = $pdo->prepare('SELECT c.*, COALESCE(u.nama, s.nama) AS nama, COALESCE(s.kelas, u.level) AS kelas FROM checklist c LEFT JOIN siswa s ON s.nis = c.nis LEFT JOIN users u ON u.id_user = c.id_user WHERE c.id_checklist = ?');
    $st->execute([(int)$id]);
    $c = $st->fetch();
    if (!$c) { flash('danger', 'Checklist tidak ditemukan.'); redirect('checklist/'); }
    if (is_siswa() && (string)$c['nis'] !== (string)(user()['nis'] ?? '')) {
        http_response_code(403);
        exit('Ini bukan checklist Anda.');
    }
    return $c;
}

// ---------- Tampilan ----------

// Ikon SVG sebaris (stroke mengikuti warna teks). Dipakai di sidebar dan kartu.
function ikon($nama) {
    $p = [
        'menu'   => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'home'   => '<path d="M3 11l9-8 9 8M5 10v10h14V10"/>',
        'siswa'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-5.5 7-5.5s7 2 7 5.5M17 5a3.5 3.5 0 010 7M22 20c0-2.5-1.5-4-4-5"/>',
        'bolt'   => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
        'list'   => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
        'key'    => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3"/>',
        'moon'   => '<path d="M20 14A8 8 0 019.5 3.5 8 8 0 1020 14z"/>',
        'info'   => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
        'out'    => '<path d="M9 4H5v16h4M16 8l4 4-4 4M20 12H9"/>',
        'chev'   => '<path d="M6 9l6 6 6-6"/>',
        'set'    => '<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>',
        'check'  => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'leaf'   => '<path d="M5 19C5 10 10 5 20 5c0 10-5 15-14 15M5 19c3-5 6-8 10-10"/>',
        'tip'    => '<path d="M9 18h6M10 21h4M12 3a6 6 0 00-4 10.5c.8.8 1 1.5 1 2.5h6c0-1 .2-1.7 1-2.5A6 6 0 0012 3z"/>',
        'speaker'=> '<path d="M3 10v4h4l6 4V6L7 10zM17 8a5 5 0 010 8"/>',
    ][$nama] ?? '';
    return '<svg class="ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

// Mencari file logo di assets/img/ dengan nama konservasi.(png|jpg|jpeg|webp|svg); urutan = prioritas.
function logo_file() {
    foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $x) {
        if (is_file(__DIR__ . '/../assets/img/konservasi.' . $x)) return 'konservasi.' . $x;
    }
    return null;
}

// Logo aplikasi: pakai file logo jika ada, kalau tidak pakai ikon petir.
function logo($ukuran = 36) {
    $f = logo_file();
    if ($f) {
        return '<img src="' . BASE_URL . '/assets/img/' . $f . '" alt="Logo Konservasi Energi" width="' . $ukuran . '" height="' . $ukuran . '" class="logo-img">';
    }
    return '<span class="logo-ph" style="width:' . $ukuran . 'px;height:' . $ukuran . 'px">' . ikon('bolt') . '</span>';
}

// Halaman aktif ditentukan dari alamat file yang sedang dibuka (untuk menyorot menu).
function halaman_aktif() {
    $s = $_SERVER['SCRIPT_NAME'] ?? '';
    foreach (['siswa', 'perangkat', 'checklist', 'users', 'akun'] as $k) {
        if (strpos($s, "/$k/") !== false) return $k;
    }
    return strpos($s, 'tentang') !== false ? 'tentang' : 'dashboard';
}

function menu_item($kunci, $url, $ikon, $label, $aktif) {
    $on = $kunci === $aktif ? ' on' : '';
    return '<a class="menu' . $on . '" href="' . BASE_URL . '/' . $url . '">' . ikon($ikon) . '<span>' . e($label) . '</span></a>';
}

// Tanggal berbahasa Indonesia, contoh: 15 Okt 2026
function tgl_id($tanggal) {
    $bln = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($tanggal);
    return date('j', $t) . ' ' . $bln[(int)date('n', $t)] . ' ' . date('Y', $t);
}

// Panjang teks; memakai mbstring bila tersedia (tidak semua server mengaktifkannya).
function panjang($s) { return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s); }

// Membuat akun Siswa (tabel users) dari data di tabel siswa. Dipakai saat siswa pertama kali masuk
// dengan username = NIS dan password = NIS. Mengembalikan baris users, atau null jika NIS tidak terdaftar.
function buat_akun_siswa(PDO $pdo, $nis) {
    $st = $pdo->prepare('SELECT nis, nama FROM siswa WHERE nis = ?');
    $st->execute([(int)$nis]);
    $sw = $st->fetch();
    if (!$sw || (string)$sw['nis'] !== (string)$nis) return null;   // tolak mis. "012" (angka nol di depan)
    try {
        $pdo->prepare('INSERT INTO users (username, password, nama, level, nis) VALUES (?,?,?,?,?)')
            ->execute([(string)$nis, password_hash((string)$nis, PASSWORD_DEFAULT), $sw['nama'], 'Siswa', $sw['nis']]);
    } catch (PDOException $e) {
        return null;                                                  // mis. username sudah dipakai
    }
    $st = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([(string)$nis]);
    return $st->fetch() ?: null;
}

// Maskot bumi (ilustrasi SVG sederhana) dan bohlam untuk hiasan dashboard.
function maskot($px = 120) {
    return '<svg class="mk" width="' . $px . '" height="' . $px . '" viewBox="0 0 120 120" aria-hidden="true">'
        . '<defs><radialGradient id="mkBiru" cx=".35" cy=".3" r=".85"><stop offset="0" stop-color="#a6dcff"/><stop offset="1" stop-color="#4aa6ea"/></radialGradient></defs>'
        . '<path d="M56 22c-12-12-30-8-34 4 14 8 30 4 34-4z" fill="#62c16a" stroke="#2f6b3a" stroke-width="2.5" stroke-linejoin="round"/>'
        . '<path d="M62 22c6-14 24-18 32-8-8 12-24 14-32 8z" fill="#7bd17d" stroke="#2f6b3a" stroke-width="2.5" stroke-linejoin="round"/>'
        . '<circle cx="60" cy="68" r="46" fill="url(#mkBiru)" stroke="#2b3f6b" stroke-width="3"/>'
        . '<path d="M22 58c8-12 20-10 24-3s-2 13-9 15-15 4-15-12z" fill="#7ccf6e"/>'
        . '<path d="M82 40c10-3 19 5 17 13s-11 7-15 3-6-14-2-16z" fill="#7ccf6e"/>'
        . '<path d="M74 96c8-7 19-5 21 2-6 9-17 11-21-2z" fill="#7ccf6e"/>'
        . '<ellipse cx="47" cy="68" rx="4.5" ry="6.5" fill="#2b2140"/><ellipse cx="73" cy="68" rx="4.5" ry="6.5" fill="#2b2140"/>'
        . '<circle cx="48.5" cy="65.5" r="1.8" fill="#fff"/><circle cx="74.5" cy="65.5" r="1.8" fill="#fff"/>'
        . '<circle cx="37" cy="80" r="6" fill="#ff9fbe" opacity=".85"/><circle cx="83" cy="80" r="6" fill="#ff9fbe" opacity=".85"/>'
        . '<path d="M53 82q7 7 14 0" stroke="#2b2140" stroke-width="3" fill="none" stroke-linecap="round"/>'
        . '</svg>';
}
function bohlam($px = 110) {
    return '<svg class="mk" width="' . $px . '" height="' . round($px * 1.25) . '" viewBox="0 0 100 125" aria-hidden="true">'
        . '<g stroke="#ffb703" stroke-width="4" stroke-linecap="round"><path d="M50 4v10M16 18l7 7M84 18l-7 7M6 52h10M84 52h10"/></g>'
        . '<path d="M50 20c-19 0-32 13-32 30 0 11 6 18 12 25 3 4 4 7 4 11h32c0-4 1-7 4-11 6-7 12-14 12-25 0-17-13-30-32-30z" fill="#ffe27a" stroke="#6b4a1f" stroke-width="3" stroke-linejoin="round"/>'
        . '<path d="M50 78V56m0 0c-6-1-10-5-11-10M50 56c6-1 10-5 11-10" stroke="#3fa75a" stroke-width="4" fill="none" stroke-linecap="round"/>'
        . '<rect x="34" y="92" width="32" height="8" rx="4" fill="#9aa0b4" stroke="#5a5f78" stroke-width="2.5"/>'
        . '<rect x="38" y="102" width="24" height="8" rx="4" fill="#9aa0b4" stroke="#5a5f78" stroke-width="2.5"/>'
        . '<path d="M44 112h12l-2 6h-8z" fill="#7b8098"/>'
        . '</svg>';
}
