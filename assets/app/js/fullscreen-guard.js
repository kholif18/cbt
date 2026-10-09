/*  Pengawas layar penuh untuk halaman ujian (ujian & konfirmasi/token).
 *
 *  Dipakai sebagai pengganti potongan inline di ujian.php supaya logika yang
 *  sama bisa dipakai halaman konfirmasi tanpa menyalin kode:
 *   - CbtFsGuard.pasang() : dengarkan keluar fullscreen => overlay blokir.
 *                            Masuk fullscreen => tutup overlay + kunci orientasi.
 *   - CbtFsGuard.paksa()   : bila belum fullscreen, tampilkan overlay sekarang
 *                            (tombol di overlay = gestur untuk requestFullscreen).
 *  Browser tanpa API fullscreen dianggap tidak didukung (dilewati diam-diam).
 */
(function () {
    'use strict';

    var pernahFs = false;
    var overlayFs = null;
    var terpasang = false;

    function adaFsApi() {
        var e = document.documentElement;
        return !!(e.requestFullscreen || e.webkitRequestFullscreen
            || e.mozRequestFullScreen || e.msRequestFullscreen);
    }

    function sedangFs() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement
            || document.mozFullScreenElement || document.msFullscreenElement);
    }

    function mintaFullscreen() {
        var e = document.documentElement;
        var fn = e.requestFullscreen || e.webkitRequestFullscreen
            || e.mozRequestFullScreen || e.msRequestFullscreen;
        if (fn) fn.call(e);
    }

    /* Kunci orientasi portrait saat masuk fullscreen; kalau tidak didukung
       (mis. desktop) biarkan diam-diam gagal. */
    function kunciOrientasi() {
        try {
            if (screen.orientation && screen.orientation.lock) {
                screen.orientation.lock('portrait').catch(function () {});
            }
        } catch (e) {}
    }

    function tutupBlokirFs() {
        if (overlayFs && overlayFs.parentNode) overlayFs.parentNode.removeChild(overlayFs);
        overlayFs = null;
    }

    function tampilkanBlokirFs() {
        if (overlayFs || !adaFsApi()) return;
        overlayFs = document.createElement('div');
        overlayFs.style.cssText = 'position:fixed;left:0;top:0;right:0;bottom:0;'
            + 'z-index:2147483000;background:#0f172a;color:#fff;display:flex;'
            + 'align-items:center;justify-content:center;padding:20px;text-align:center;';
        var label = pernahFs
            ? '<i class="fas fa-expand-arrows-alt mr-1"></i> Kembali ke Layar Penuh'
            : '<i class="fas fa-expand-arrows-alt mr-1"></i> Aktifkan Layar Penuh';
        overlayFs.innerHTML =
            '<div><i class="fas fa-compress-arrows-alt" style="font-size:2.4rem"></i>'
            + '<h5 style="margin:12px 0 8px">Ujian harus dalam mode layar penuh</h5>'
            + '<p style="max-width:420px;color:#cbd5e1;margin-bottom:16px">'
            + 'Halaman ini (baik saat menunggu maupun mengerjakan soal) wajib '
            + 'berjalan dalam mode layar penuh. Tekan tombol di bawah untuk '
            + 'melanjutkan.</p>'
            + '<button type="button" id="kembali-fs" class="btn btn-primary">'
            + label + '</button>'
            + '</div>';
        document.body.appendChild(overlayFs);
        overlayFs.querySelector('#kembali-fs').addEventListener('click', function () {
            mintaFullscreen();
        });
    }

    function cekBlokirFs() {
        if (sedangFs()) {
            pernahFs = true;
            tutupBlokirFs();
            kunciOrientasi();
        } else if (pernahFs) {
            tampilkanBlokirFs();
        }
    }

    window.CbtFsGuard = {
        pasang: function () {
            if (terpasang || !adaFsApi()) return;
            terpasang = true;
            document.addEventListener('fullscreenchange', cekBlokirFs);
            document.addEventListener('webkitfullscreenchange', cekBlokirFs);
        },
        paksa: function () {
            if (!adaFsApi() || sedangFs()) return;
            tampilkanBlokirFs();
        }
    };
})();
