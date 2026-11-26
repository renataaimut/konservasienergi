-- Jalankan SEKALI di database konservasi_energi_renata (lewati jika sudah pernah dijalankan)
ALTER TABLE checklist
  MODIFY nis INT(10) NULL,
  ADD COLUMN id_user INT(11) NULL AFTER nis,
  ADD KEY idx_checklist_user (id_user),
  ADD CONSTRAINT fk_checklist_user FOREIGN KEY (id_user)
    REFERENCES users (id_user) ON DELETE RESTRICT ON UPDATE CASCADE;
