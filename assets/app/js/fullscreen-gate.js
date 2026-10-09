/*  Gate layar penuh setelah login siswa.
    Halaman login menaruh flag di sessionStorage (garudaCBT.fullscreenAsk);
    halaman pendaratan siswa memuat file ini dan menampilkan overlay sekali.
    Overlay berisi pemberitahuan tentang aplikasi dengan tombol OK saja;
    klik OK itulah yang mengaktifkan layar penuh. Bila setelah OK layar
    belum masuk fullscreen, dialog muncul lagi (maks 2 kali ulang).
    Tanpa dukungan API atau sudah fullscreen = dilewati tanpa ganggu.
    Blokiran ketat ada di halaman ujian. */
(function () {
    'use strict';

    var FLAG = 'garudaCBT.fullscreenAsk';
    var minta = false;
    try { minta = sessionStorage.getItem(FLAG) === '1'; } catch (e) {}
    if (!minta) return;
    try { sessionStorage.removeItem(FLAG); } catch (e) {}

    var el = document.documentElement;
    var req = el.requestFullscreen || el.webkitRequestFullscreen;
    if (!req) return;

    var sisaCoba = 3;

    function sedangAktif() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement);
    }

    function tutup(wrap) {
        if (wrap && wrap.parentNode) wrap.parentNode.removeChild(wrap);
    }

    function pasang() {
        if (sedangAktif() || document.getElementById('cbt-fs-gate')) return;
        if (!document.body) return;

        var wrap = document.createElement('div');
        wrap.id = 'cbt-fs-gate';
        wrap.style.cssText = 'position:fixed;left:0;top:0;right:0;bottom:0;'
            + 'z-index:99999;background:rgba(15,23,42,.82);display:flex;'
            + 'align-items:center;justify-content:center;padding:16px;';
        /* Tampil sebagai pemberitahuan biasa tentang aplikasi, hanya ada
           tombol OK; gestur klik OK itulah yang sekaligus mengaktifkan
           layar penuh (requestFullscreen wajib berawal dari gestur). */
        wrap.innerHTML =
            '<div style="background:#fff;border-radius:12px;max-width:440px;'
            + 'width:100%;padding:24px;text-align:center;'
            + 'box-shadow:0 10px 40px rgba(0,0,0,.35)">'
            + '<i class="fas fa-bullhorn" style="font-size:2.2rem;color:#3f51b5"></i>'
            + '<h5 style="margin:12px 0 8px">Pemberitahuan</h5>'
            + '<p style="color:#555;margin-bottom:18px;text-align:left">'
            + 'Aplikasi ini adalah sistem <b>Computer Based Test (CBT)</b> untuk'
            + ' pelaksanaan ujian sekolah berbasis komputer. Jawabanmu tersimpan'
            + ' otomatis di server dan waktu ujian dihitung mundur oleh sistem.'
            + ' Pastikan perangkat dalam keadaan siap dan jaringan stabil, lalu'
            + ' tekan OK untuk melanjutkan.</p>'
            + '<button type="button" data-aksi="ya" class="btn btn-primary btn-block">'
            + 'OK</button>'
            + '</div>';
        document.body.appendChild(wrap);

        wrap.addEventListener('click', function (e) {
            var target = e.target.closest ? e.target.closest('[data-aksi]') : null;
            if (!target) return;
            if (target.getAttribute('data-aksi') === 'ya') {
                try {
                    var p = req.call(el);
                    if (p && typeof p.catch === 'function') p.catch(function () {});
                } catch (err) {}
                tutup(wrap);
                sisaCoba--;
                if (sisaCoba > 0) {
                    setTimeout(function () {
                        if (!sedangAktif()) pasang();
                    }, 2500);
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', pasang);
    } else {
        pasang();
    }
})();
