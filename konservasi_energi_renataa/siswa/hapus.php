<?php
require_once __DIR__ . '/../includes/functions.php';
login_wajib(['Admin']);   // hanya Admin yang boleh menghapus
csrf_check();
try {
    $pdo->prepare('DELETE FROM siswa WHERE nis = ?')->execute([(int)($_POST['nis'] ?? 0)]);
    flash('success', 'Siswa dihapus.');
} catch (PDOException $ex) {
    flash('danger', 'Siswa tidak bisa dihapus karena masih memiliki data checklist atau akun login. Hapus akun/checklist-nya dulu.');
}
redirect('siswa/');
