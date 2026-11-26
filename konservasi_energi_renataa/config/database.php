<?php
// Sesuaikan dengan lingkungan Anda
date_default_timezone_set('Asia/Jakarta');   // WIB; ganti Asia/Makassar (WITA) atau Asia/Jayapura (WIT) jika perlu
define('BASE_URL', '/konservasi_energi_renataa');   // nama folder di htdocs

define('LOGIN_NIS_OTOMATIS', true);

$host = 'localhost';
$db   = 'konservasi_energi_renata';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    exit('Koneksi database gagal. Periksa config/database.php');
}

// ---- Penyesuaian otomatis (aman dijalankan berulang) ----
try {
    // 1) Checklist memakai akun yang login (guru), bukan siswa: tambah kolom id_user bila belum ada
    $ada = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'checklist' AND COLUMN_NAME = 'id_user'");
    $ada->execute();
    if (!(int)$ada->fetchColumn()) {
        $pdo->exec("ALTER TABLE checklist
            MODIFY nis INT(10) NULL,
            ADD COLUMN id_user INT(11) NULL AFTER nis,
            ADD KEY idx_checklist_user (id_user),
            ADD CONSTRAINT fk_checklist_user FOREIGN KEY (id_user)
                REFERENCES users (id_user) ON DELETE RESTRICT ON UPDATE CASCADE");
    }
    // 2) Nama akun guru: Bu Sari (hanya jika masih bernama bawaan "Guru")
    $pdo->exec("UPDATE users SET nama = 'Bu Sari' WHERE username = 'guru' AND nama = 'Guru'");
} catch (PDOException $e) {
    // dilewati; jalankan migrasi_guru_checklist.sql secara manual jika perlu
}
