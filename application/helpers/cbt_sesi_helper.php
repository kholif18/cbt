<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Batas waktu ujian mengikuti jam akhir sesi yang dipakai siswa.
 *
 * Aturan menu "Paksa Selesai" di halaman status pengawas:
 * hanya boleh muncul bila sisa waktu ke jam akhir sesi kurang dari
 * AMBANG_PAKSA_SELESAI_DETIK (15 menit). Src = Cbt_model::getBatasUjian()
 * supaya angka di halaman status sama dengan batas yang dipakai server.
 */
define('AMBANG_PAKSA_SELESAI_DETIK', 15 * 60);

/**
 * Ubah jam sesi (HH:MM:SS) menjadi timestamp di tanggal $tgl.
 * Sesi yang melewati tengah malam digeser +1 hari.
 */
function cbt_sesi_akhir_ts($waktu_mulai, $waktu_akhir, $tgl)
{
    if ($waktu_akhir == null || $waktu_akhir === '' || $tgl == null || $tgl === '') {
        return null;
    }

    $akhir = strtotime($tgl . ' ' . $waktu_akhir);
    if ($akhir === false) {
        return null;
    }

    if ($waktu_mulai != null && $waktu_mulai !== '') {
        $mulai = strtotime($tgl . ' ' . $waktu_mulai);
        // mis. sesi 23:00 - 01:00
        if ($mulai !== false && $akhir <= $mulai) {
            $akhir += 86400;
        }
    }

    return $akhir;
}

/**
 * Peta batas sesi aktif, dikunci kode_sesi.
 * Setiap isi: nama, mulai, akhir, akhir_ts (unix), sisa_detik, buka_paksa.
 */
function cbt_peta_sesi_batas($tgl = null, $now = null)
{
    $CI =& get_instance();
    $tgl = ($tgl == null || $tgl === '') ? date('Y-m-d') : $tgl;
    $now = $now == null ? time() : $now;

    $sesi = $CI->db->select('kode_sesi, nama_sesi, waktu_mulai, waktu_akhir')
        ->from('cbt_sesi')
        ->where('aktif', 1)
        ->get()
        ->result();

    $peta = [];
    foreach ($sesi as $s) {
        $akhirTs = cbt_sesi_akhir_ts($s->waktu_mulai, $s->waktu_akhir, $tgl);
        // Tanpa data jam akhir, jangan pernah buka paksa selesai otomatis.
        $sisa = $akhirTs == null ? PHP_INT_MAX : $akhirTs - $now;

        $peta[$s->kode_sesi] = [
            'nama' => $s->nama_sesi,
            'mulai' => $s->waktu_mulai,
            'akhir' => $s->waktu_akhir,
            'akhir_ts' => $akhirTs,
            'sisa_detik' => $sisa > 0 ? $sisa : 0,
            'buka_paksa' => $sisa < AMBANG_PAKSA_SELESAI_DETIK,
        ];
    }

    return $peta;
}

/**
 * true bila menu paksa selesai boleh tampil untuk kode sesi tersebut.
 * Sesi yang tidak ditemukan dianggap belum boleh tampil (aman).
 */
function cbt_buka_paksa_sesi($petaSesi, $kodeSesi)
{
    return isset($petaSesi[$kodeSesi]) && $petaSesi[$kodeSesi]['buka_paksa'] === true;
}

/**
 * true bila admin menimpa aturan 15 menit dengan override "tampil selalu"
 * untuk aksi Paksa Selesai.
 *
 * false (bawaan) = kolom Paksa Selesai di halaman pengawas mengikuti aturan
 * muncul 15 menit sebelum jam akhir sesi; true = tampil kapan pun, menimpa
 * aturan itu. Flag disimpan di tabel cbt_setting_flag (bukan di config file)
 * supaya langsung berlaku tanpa restart, dan tetap berlaku lintas server.
 * Nilai di-cache per-request karena dipakai banyak kali saat render.
 */
function cbt_paksa_tampil_dipaksa($refresh = false)
{
    static $cache = null;

    if ($cache !== null && !$refresh) {
        return $cache;
    }

    $CI =& get_instance();
    $row = $CI->db->select('nilai')
        ->from('cbt_setting_flag')
        ->where('kode', 'tampil_paksa_selesai')
        ->limit(1)
        ->get()
        ->row();

    $cache = ($row != null && (string) $row->nilai === '1');

    return $cache;
}

/**
 * Simpan override tampil / aturan 15 menit untuk pengawas.
 * $oleh = username admin pemicu, disimpan untuk jejak audit.
 */
function cbt_simpan_paksa_tampil_dipaksa($tampil, $oleh = null)
{
    $CI =& get_instance();
    $nilai = $tampil ? '1' : '0';

    $ada = $CI->db->select('kode')
        ->from('cbt_setting_flag')
        ->where('kode', 'tampil_paksa_selesai')
        ->limit(1)
        ->get()
        ->row() != null;

    $isi = [
        'nilai' => $nilai,
        'diubah' => date('Y-m-d H:i:s'),
        'diubah_oleh' => $oleh,
    ];

    if ($ada) {
        $CI->db->where('kode', 'tampil_paksa_selesai')->update('cbt_setting_flag', $isi);
    } else {
        $isi['kode'] = 'tampil_paksa_selesai';
        $CI->db->insert('cbt_setting_flag', $isi);
    }

    cbt_paksa_tampil_dipaksa(true);

    return $tampil;
}

/**
 * true bila ada sesi pada jadwal tersebut yang sisa waktunya sampai jam akhir
 * sesi tinggal < AMBANG_PAKSA_SELESAI_DETIK (jendela "muncul 15 menit sebelum
 * selesai"). Dipakai server untuk menegakkan aturan yang sama dengan yang
 * tampil di halaman pengawas, supaya request manual tidak menembusnya.
 * Basis: cbt_pengawas.id_sesi (boleh CSV, angka id_sesi) dipetakan ke
 * cbt_sesi, lalu ke peta batas hari ini.
 */
function cbt_jendela_paksa_terbuka($id_jadwal)
{
    if ($id_jadwal == null || $id_jadwal <= 0) {
        return false;
    }

    $CI =& get_instance();
    $baris = $CI->db->select('id_sesi')
        ->from('cbt_pengawas')
        ->where('id_jadwal', $id_jadwal)
        ->get()
        ->result();
    if (!$baris) {
        return false;
    }

    /* petakan id_sesi maupun kode_sesi apa adanya */
    $petaKode = [];
    foreach ($CI->db->select('id_sesi, kode_sesi')
        ->from('cbt_sesi')
        ->where('aktif', 1)
        ->get()
        ->result() as $s) {
        $petaKode[(string) $s->id_sesi] = $s->kode_sesi;
        $petaKode[(string) $s->kode_sesi] = $s->kode_sesi;
    }

    $peta = cbt_peta_sesi_batas(date('Y-m-d'));
    foreach ($baris as $b) {
        foreach (explode(',', (string) $b->id_sesi) as $v) {
            $v = trim($v);
            if ($v === '') {
                continue;
            }
            $kode = isset($petaKode[$v]) ? $petaKode[$v] : $v;
            if (cbt_buka_paksa_sesi($peta, $kode)) {
                return true;
            }
        }
    }

    return false;
}