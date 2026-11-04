        </div>
    </main>

    <footer class="app-footer px-3 px-lg-4 py-3 text-muted d-flex flex-wrap justify-content-between gap-2" style="font-size: 0.7rem; border-top: 1px solid var(--line);">
        <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; <?= e(APP_FULL_NAME) ?></span>
        <span>Periode <?= e(TAHUN_AJARAN_AKTIF) ?> &middot; <?= e(SEMESTER_AKTIF) ?></span>
    </footer>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-start gap-3">
                    <span class="stat-icon is-amber mb-0" style="flex: 0 0 auto;">
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    </span>
                    <div>
                        <p class="mb-1 fw-semibold text-navy">Hapus <span data-delete-name>data ini</span>?</p>
                        <p class="mb-0 small-note">Tindakan ini tidak dapat dibatalkan. Pastikan data yang dipilih sudah benar.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete" data-delete-action>
                    <input type="hidden" name="id" value="" data-delete-id>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash3 me-1" aria-hidden="true"></i> Ya, Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=1.0"></script>
</body>
</html>
