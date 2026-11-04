(function () {
    'use strict';

    const body = document.body;
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const sidebarToggle = document.getElementById('sidebarToggle');

    function openSidebar() {
        body.classList.add('sidebar-open');
        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', 'true');
        }
    }

    function closeSidebar() {
        body.classList.remove('sidebar-open');
        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', 'false');
        }
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            if (body.classList.contains('sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) {
                    closeSidebar();
                }
            });
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) {
            closeSidebar();
        }
    });

    // Tampilkan / sembunyikan password.
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.querySelector(button.getAttribute('data-password-toggle'));
            if (!target) {
                return;
            }

            const isPassword = target.getAttribute('type') === 'password';
            target.setAttribute('type', isPassword ? 'text' : 'password');
            const icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-eye', !isPassword);
                icon.classList.toggle('bi-eye-slash', isPassword);
            }
            button.setAttribute('aria-label', isPassword ? 'Sembunyikan password' : 'Tampilkan password');
        });
    });

    // Tombol isi akun demo di halaman login.
    document.querySelectorAll('[data-demo-email]').forEach(function (button) {
        button.addEventListener('click', function () {
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            if (email) {
                email.value = button.getAttribute('data-demo-email') || '';
                email.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (password) {
                password.value = button.getAttribute('data-demo-password') || '';
                password.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (email) {
                email.focus();
            }
        });
    });

    // Modal konfirmasi hapus global.
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!trigger) {
                return;
            }

            const form = deleteModal.querySelector('form');
            const nameTarget = deleteModal.querySelector('[data-delete-name]');
            const actionTarget = deleteModal.querySelector('[data-delete-action]');
            const idTarget = deleteModal.querySelector('[data-delete-id]');
            const url = trigger.getAttribute('data-delete-url');

            if (form && url) {
                form.setAttribute('action', url);
            }
            if (nameTarget) {
                nameTarget.textContent = trigger.getAttribute('data-delete-name') || 'data ini';
            }
            if (actionTarget) {
                actionTarget.value = trigger.getAttribute('data-delete-action') || 'delete';
            }
            if (idTarget) {
                idTarget.value = trigger.getAttribute('data-delete-id') || '';
            }
        });
    }

    // Penghitung SKS pada form KRS.
    const krsForm = document.getElementById('krsForm');
    if (krsForm) {
        const checkboxes = Array.from(krsForm.querySelectorAll('.krs-checkbox'));
        const selectedSks = document.getElementById('selectedSks');
        const remainingSks = document.getElementById('remainingSks');
        const submitButton = document.getElementById('krsSubmit');
        const maxSks = parseInt(krsForm.getAttribute('data-max-sks') || '24', 10);
        const existingSks = parseInt(krsForm.getAttribute('data-existing-sks') || '0', 10);

        function updateSks() {
            const total = checkboxes.reduce(function (sum, checkbox) {
                return sum + (checkbox.checked ? parseInt(checkbox.getAttribute('data-sks') || '0', 10) : 0);
            }, existingSks);

            if (selectedSks) {
                selectedSks.textContent = String(total);
            }
            if (remainingSks) {
                remainingSks.textContent = String(Math.max(0, maxSks - total));
            }

            const exceeds = total > maxSks;
            if (submitButton) {
                submitButton.disabled = exceeds;
            }
            krsForm.classList.toggle('is-invalid-sks', exceeds);
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', updateSks);
        });
        updateSks();
    }

    // Pratinjau nilai akhir pada modal input nilai dosen.
    document.querySelectorAll('[data-grade-form]').forEach(function (form) {
        const inputs = Array.from(form.querySelectorAll('[data-grade-input]'));
        const finalTarget = form.querySelector('[data-grade-final]');
        const letterTarget = form.querySelector('[data-grade-letter]');

        function updateGradePreview() {
            if (!finalTarget || !letterTarget) {
                return;
            }

            const values = inputs.map(function (input) {
                return parseFloat(input.value);
            });

            if (values.length < 3 || values.some(function (value) { return Number.isNaN(value); })) {
                finalTarget.textContent = '-';
                letterTarget.textContent = '-';
                letterTarget.className = 'grade-pill grade-a ms-1';
                return;
            }

            const final = Math.round(((values[0] * 0.3) + (values[1] * 0.3) + (values[2] * 0.4)) * 100) / 100;
            let letter = 'E';
            let gradeClass = 'grade-e';

            if (final >= 85) {
                letter = 'A';
                gradeClass = 'grade-a';
            } else if (final >= 80) {
                letter = 'A-';
                gradeClass = 'grade-a';
            } else if (final >= 75) {
                letter = 'B+';
                gradeClass = 'grade-b';
            } else if (final >= 70) {
                letter = 'B';
                gradeClass = 'grade-b';
            } else if (final >= 65) {
                letter = 'B-';
                gradeClass = 'grade-b';
            } else if (final >= 60) {
                letter = 'C+';
                gradeClass = 'grade-c';
            } else if (final >= 55) {
                letter = 'C';
                gradeClass = 'grade-c';
            } else if (final >= 40) {
                letter = 'D';
                gradeClass = 'grade-d';
            }

            finalTarget.textContent = final.toFixed(2).replace('.', ',');
            letterTarget.textContent = letter;
            letterTarget.className = 'grade-pill ' + gradeClass + ' ms-1';
        }

        inputs.forEach(function (input) {
            input.addEventListener('input', updateGradePreview);
        });
        updateGradePreview();
    });

    // Inisialisasi tooltip Bootstrap.
    if (window.bootstrap && typeof window.bootstrap.Tooltip === 'function') {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
            new window.bootstrap.Tooltip(element);
        });
    }

    // Tutup alert otomatis setelah 7 detik.
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (alert) {
        window.setTimeout(function () {
            if (window.bootstrap && typeof window.bootstrap.Alert === 'function') {
                window.bootstrap.Alert.getOrCreateInstance(alert).close();
            }
        }, 7000);
    });
})();
