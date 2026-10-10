<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Normalisasi versi browser pada string agent.
 *
 * validasiSiswa mengikat sesi siswa ke (address, agent, device).
 * String agent disusun dari browser() . " " . version() — versi lengkap
 * (mis. "Chrome 154.0.0.0") berubah saat Chrome Android auto-update
 * di tengah ujian sehingga ribuan siswa terkunci "Error 002" tanpa
 * berpindah perangkat. Dengan versi dinormalisasi ("Chrome x"),
 * identitas = nama browser + platform; pembaruan versi tidak lagi
 * mengunci siswa, sedangkan pindah HP <-> PC tetap terdeteksi.
 */
class MY_User_agent extends CI_User_agent {

	public function version()
	{
		return 'x';
	}
}
