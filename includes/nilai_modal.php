<?php
if (!isset($row) || !is_array($row)) {
    return;
}
$sudahDinilai = $row['nilai_id'] !== null;
?>                            <div class="modal fade" id="nilaiModal<?= (int) $row['krs_id'] ?>" tabindex="-1" aria-labelledby="nilaiModalLabel<?= (int) $row['krs_id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="post" action="<?= e(base_url('actions/nilai.php')) ?>" data-grade-form>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="save">
                                            <input type="hidden" name="krs_id" value="<?= (int) $row['krs_id'] ?>">
                                            <div class="modal-header">
                                                <div>
                                                    <h2 class="modal-title fs-5" id="nilaiModalLabel<?= (int) $row['krs_id'] ?>">Input Nilai</h2>
                                                    <p class="small-note mb-0"><?= e($row['nama']) ?> &middot; <?= e($row['nim']) ?></p>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3">
                                                    <div class="col-4">
                                                        <label for="tugas<?= (int) $row['krs_id'] ?>" class="form-label">Tugas</label>
                                                        <input type="number" class="form-control score-input" id="tugas<?= (int) $row['krs_id'] ?>" name="tugas" min="0" max="100" step="0.01" value="<?= $sudahDinilai ? e(number_format((float) $row['tugas'], 2, '.', '')) : '' ?>" placeholder="0-100" required data-grade-input>
                                                    </div>
                                                    <div class="col-4">
                                                        <label for="uts<?= (int) $row['krs_id'] ?>" class="form-label">UTS</label>
                                                        <input type="number" class="form-control score-input" id="uts<?= (int) $row['krs_id'] ?>" name="uts" min="0" max="100" step="0.01" value="<?= $sudahDinilai ? e(number_format((float) $row['uts'], 2, '.', '')) : '' ?>" placeholder="0-100" required data-grade-input>
                                                    </div>
                                                    <div class="col-4">
                                                        <label for="uas<?= (int) $row['krs_id'] ?>" class="form-label">UAS</label>
                                                        <input type="number" class="form-control score-input" id="uas<?= (int) $row['krs_id'] ?>" name="uas" min="0" max="100" step="0.01" value="<?= $sudahDinilai ? e(number_format((float) $row['uas'], 2, '.', '')) : '' ?>" placeholder="0-100" required data-grade-input>
                                                    </div>
                                                </div>
                                                <div class="alert alert-light border mt-3 mb-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
                                                    <span class="small-note">Nilai akhir (30% Tugas + 30% UTS + 40% UAS)</span>
                                                    <span>
                                                        <strong class="text-navy" data-grade-final>-</strong>
                                                        <span class="grade-pill grade-a ms-1" data-grade-letter>-</span>
                                                    </span>
                                                </div>
                                                <p class="small-note mt-3 mb-0">Nilai akhir dan huruf akan dihitung ulang oleh sistem saat disimpan.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-save me-1" aria-hidden="true"></i> Simpan Nilai
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
