<?php
require_once __DIR__ . '/../includes/functions.php';
login_wajib(['Admin']);   // hanya Admin yang boleh menghapus checklist
csrf_check();
$c = ambil_checklist($pdo, $_POST['id'] ?? 0);
$pdo->prepare('DELETE FROM checklist WHERE id_checklist = ?')->execute([$c['id_checklist']]);
flash('success', 'Checklist dihapus.');
redirect('checklist/');
