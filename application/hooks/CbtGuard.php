<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*  Penegakan batas "Reset Izin" untuk endpoint siswa/applyaction.
 *
 *  applyAction() di controller Siswa tidak memeriksa peran maupun kuota dan
 *  route-nya bisa dipanggil langsung dengan huruf apa pun, jadi aturannya
 *  ditegakkan lewat hook di dua titik:
 *   - post_controller_constructor : cek peran + kuota 3x + jeda 15 menit
 *   - post_controller              : catat hitungan setelah aksi jalan
 *
 *  Berlaku per (user, jadwal ujian):
 *   - pengawas : maksimal 3 reset izin, jeda 15 menit antar reset
 *   - admin    : tanpa batas, tetap dicatat hitungannya
 *   - aksi "Ulang" mengembalikan kuota menjadi 3 lagi
 */
class CbtGuard
{
    /* kuota reset izin pengawas per jadwal */
    const KUOTA_PENGAWAS = 3;
    /* jeda antar reset izin dalam detik (15 menit) */
    const JEDA_DETIK = 900;

    /* rencana aksi yang lolos pemeriksaan, dicatat setelah applyAction() */
    private $rencana = null;

    public function jagaSebelumAksi($params = '')
    {
        date_default_timezone_set('Asia/Jakarta');
        $CI =& get_instance();
        if (!$this->URIApplyAction($CI)) {
            return;
        }
        if ($CI->input->method(TRUE) !== 'POST') {
            return;
        }

        $aksi = json_decode($CI->input->post('aksi', true));
        $id_jadwal = (int) $CI->input->post('jadwal', true);
        if ($aksi == null || $id_jadwal <= 0) {
            return;
        }

        $reset = isset($aksi->reset) && is_array($aksi->reset) ? $aksi->reset : array();
        $ulang = isset($aksi->ulang) && is_array($aksi->ulang) ? $aksi->ulang : array();
        $hapus = isset($aksi->hapus) && is_array($aksi->hapus) ? $aksi->hapus : array();
        /* aksi lain (paksa selesai / tanpa pilihan) dibiarkan seperti biasa */
        if (count($reset) == 0 && count($ulang) == 0) {
            return;
        }

        if (!$CI->ion_auth->logged_in()) {
            return;
        }
        $user = $CI->ion_auth->user()->row();
        if ($user == null) {
            return;
        }
        $admin = $CI->ion_auth->is_admin();

        if (!$admin && !self::pengawasJadwal($CI, $user->id, $id_jadwal)) {
            $this->tolak('Hanya admin atau pengawas jadwal ini yang boleh mereset izin / mengulang ujian.');
        }

        $adaReset = count($reset) > 0;
        $ulangSah = count($ulang) > 0 && self::adaTargetUlang($CI, $id_jadwal, $ulang, $hapus);

        if ($adaReset && !$admin) {
            $kuota = self::bacaKuota($CI, $user->id, $id_jadwal);
            if ($kuota['sisa'] <= 0) {
                $this->tolak('Kuota reset izin habis (' . self::KUOTA_PENGAWAS
                    . 'x). Pilih aksi Ulang untuk mengembalikan kuota.', array('sisa' => 0));
            }
            if ($kuota['jeda_detik'] > 0) {
                $this->tolak('Jeda 15 menit antar reset izin belum cukup, tunggu '
                    . ceil($kuota['jeda_detik'] / 60) . ' menit lagi.',
                    array('sisa_detik' => $kuota['jeda_detik']));
            }
        }

        $this->rencana = array(
            'id_user'  => (int) $user->id,
            'id_jadwal' => $id_jadwal,
            'admin'    => (bool) $admin,
            'reset'    => $adaReset,
            'id_logs'  => $reset,
            'ulang'    => $ulangSah,
        );
    }

    public function catatSetelahAksi($params = '')
    {
        date_default_timezone_set('Asia/Jakarta');
        if ($this->rencana == null) {
            return;
        }
        $r = $this->rencana;
        $this->rencana = null;

        $CI =& get_instance();
        $out = json_decode((string) $CI->output->get_output());
        if (!is_object($out)) {
            return;
        }

        /* applyAction() hanya menandai "reset"/"ulangi" kalau aksinya memang
           diproses; kunci "update_*" selalu ada walau tanpa isi. */
        $resetJalan = $r['reset'] && isset($out->reset) && $out->reset === true;
        $ulangJalan = $r['ulang'] && isset($out->ulangi) && isset($out->update_ulangi)
            && $out->update_ulangi !== false;

        if ($resetJalan) {
            /* berapa kali tiap siswa direset, untuk kolom tabel admin */
            $CI->db->where_in('id_log', $r['id_logs']);
            $CI->db->set('jml_reset', 'jml_reset + 1', false);
            $CI->db->update('log_ujian');

            $baris = $CI->db->where('id_user', $r['id_user'])
                ->where('id_jadwal', $r['id_jadwal'])
                ->get('cbt_reset_izin')->row();
            if ($baris == null) {
                $CI->db->insert('cbt_reset_izin', array(
                    'id_user'        => $r['id_user'],
                    'id_jadwal'      => $r['id_jadwal'],
                    'jml_reset'      => 1,
                    'reset_terakhir' => date('Y-m-d H:i:s'),
                ));
            } else {
                $CI->db->where('id', $baris->id);
                $CI->db->set('jml_reset', 'jml_reset + 1', false);
                $CI->db->set('reset_terakhir', date('Y-m-d H:i:s'));
                $CI->db->update('cbt_reset_izin');
            }
        }

        if ($ulangJalan) {
            /* "Ulang" mengembalikan kuota. Admin mengembalikan kuota semua
               pengawas pada jadwal itu, pengawas hanya kuota dirinya sendiri. */
            if ($r['admin']) {
                $CI->db->where('id_jadwal', $r['id_jadwal']);
            } else {
                $CI->db->where('id_user', $r['id_user'])
                    ->where('id_jadwal', $r['id_jadwal']);
            }
            $CI->db->update('cbt_reset_izin', array(
                'jml_reset'      => 0,
                'reset_terakhir' => null,
            ));
        }
    }

    /* cek URI siswa/applyaction tanpa peduli huruf besar/kecil */
    private function URIApplyAction($CI)
    {
        $seg1 = strtolower((string) $CI->uri->rsegment(1));
        $seg2 = strtolower((string) $CI->uri->rsegment(2));
        return $seg1 === 'siswa' && $seg2 === 'applyaction';
    }

    /* user termasuk pengawas pada jadwal tersebut? (cbt_pengawas.id_guru CSV) */
    public static function pengawasJadwal($CI, $id_user, $id_jadwal)
    {
        $guru = $CI->db->where('id_user', $id_user)->get('master_guru')->row();
        if ($guru == null) {
            return false;
        }
        $baris = $CI->db->select('id_guru')->from('cbt_pengawas')
            ->where('id_jadwal', $id_jadwal)->get()->result();
        foreach ($baris as $p) {
            foreach (explode(',', (string) $p->id_guru) as $g) {
                if ((int) trim($g) === (int) $guru->id_guru) {
                    return true;
                }
            }
        }
        return false;
    }

    /* kuota reset izin satu user pada satu jadwal */
    public static function bacaKuota($CI, $id_user, $id_jadwal)
    {
        $baris = $CI->db->where('id_user', $id_user)
            ->where('id_jadwal', $id_jadwal)
            ->get('cbt_reset_izin')->row();
        $jml = $baris == null ? 0 : (int) $baris->jml_reset;
        $jeda = 0;
        if ($baris != null && $baris->reset_terakhir != null) {
            $lewat = strtotime($baris->reset_terakhir);
            if ($lewat !== false) {
                $jeda = max(0, self::JEDA_DETIK - (time() - $lewat));
            }
        }
        return array(
            'jml'            => $jml,
            'sisa'           => max(0, self::KUOTA_PENGAWAS - $jml),
            'maks'           => self::KUOTA_PENGAWAS,
            'jeda_detik'     => $jeda,
            'reset_terakhir' => $baris == null ? null : $baris->reset_terakhir,
        );
    }

    /* ada log ujian yang benar-benar bisa diulang oleh payload ini? */
    public static function adaTargetUlang($CI, $id_jadwal, $ulang, $hapus)
    {
        if (count($ulang) > 0) {
            $n = $CI->db->where('id_jadwal', $id_jadwal)
                ->where_in('id_siswa', $ulang)
                ->count_all_results('log_ujian');
            if ($n > 0) {
                return true;
            }
        }
        if (count($hapus) > 0) {
            $n = $CI->db->where_in('id_durasi', $hapus)
                ->count_all_results('cbt_durasi_siswa');
            if ($n > 0) {
                return true;
            }
        }
        return false;
    }

    private function tolak($pesan, $tambahan = array())
    {
        $CI =& get_instance();
        $CI->output->set_status_header(403);
        $CI->output->set_content_type('application/json');
        $CI->output->set_output(json_encode(array_merge(
            array('status' => 0, 'pesan' => $pesan), $tambahan)));
        $CI->output->_display();
        exit;
    }
}
