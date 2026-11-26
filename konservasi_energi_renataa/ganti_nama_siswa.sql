-- Jalankan di phpMyAdmin (database konservasi_energi_renata) > tab SQL.
-- Mengganti nama siswa pada database yang sudah ada, tanpa menghapus data checklist.
UPDATE siswa SET nama = 'Renata'  WHERE nis = 12345;
UPDATE siswa SET nama = 'Shafira' WHERE nis = 12346;
UPDATE siswa SET nama = 'Novi'    WHERE nis = 12347;
