# Todo — Mode APK Android (Minimalisasi Kecurangan)

## Konteks
- [ ] **Tunda semua eksekusi sampai ujian besok (10 Okt 2026) selesai** — besok masih ada 1 ujian, jangan ubah alur ujian / halaman siswa sebelum selesai.
- Setelah ujian besok selesai → eksekusi plan di bawah.

## Keputusan arsitektur (dari diskusi 9 Okt 2026)
- [ ] Fase 1 (direkomendasikan): **WebView wrapper + kiosk** — pakai alur ujian web yang sudah ada (sudah mobile-friendly), tanpa gandakan logika ujian.
- [ ] Fase 2 (menyusul): **API REST + app native** — hanya bila butuh mode offline / UI soal khusus.

## Fase 1 — Wrapper WebView + Kiosk
- [ ] Buat project Android (Kotlin/Java) minimal: `MainActivity` berisi WebView → load halaman ujian web (HTTPS).
- [ ] Manifest: `android:resizeableActivity="false"`, `android:supportsPictureInPicture="false"`.
- [ ] Kiosk mode: **Lock Task / Device Owner (MDM)** — home, recents, notifikasi, status bar dimatikan (`lockTaskFeatures`), split & floating tak tersedia.
- [ ] Deteksi runtime fallback: `isInMultiWindowMode()` / `onMultiWindowModeChanged` → saat masuk split/floating: paksa keluar / pause ujian + kirim flag ke server (konsep sama dengan `CbtFsGuard` di web).
- [ ] Immersive fullscreen (sembunyikan status/nav bar) + `FLAG_SECURE` (blokir screenshot & preview recents).
- [ ] `onPause`/app-switch → catat ke server, teruskan ke guard reset-izin 3x per 15 menit yang sudah ada.

## Fase 2 — API REST `api/v1` (setelah ujian besok)
- [ ] Fondasi: tabel `api_setting` & `api_token` sudah ada di skema, belum dipakai kode mana pun → pakai untuk token per device.
- [ ] Endpoint:
  - [ ] `api/v1/auth/login` (siswa, token device)
  - [ ] `api/v1/jadwal/list`
  - [ ] `api/v1/ujian/mulai` (validasi sesi + token → daftar soal)
  - [ ] `api/v1/ujian/jawab` (simpan jawaban per soal)
  - [ ] `api/v1/ujian/selesai`
  - [ ] `api/v1/ujian/status` (sisa batas waktu, polling)
- [ ] **Semua validasi tetap di server**: durasi, jendela sesi, token, force-finish, logging, guard reset-izin — app hanya render timer + polling. Jangan hitung countdown hanya lokal.
- [ ] Keamanan: HTTPS wajib, rate limit, token per device + device fingerprint, invalidasi token saat sesi berakhir.

## Anti-cheat pendukung (paralel)
- [ ] Polling `ujian/status` untuk batas absolut `min(mulai + durasi, akhir sesi)` — jangan percaya timer lokal.
- [ ] Tandai event pelanggaran (app switch, masuk split/floating) di `log_ujian` / kolom reset yang sudah ada.
- [ ] Evaluasi: blokir copy-paste & dev-tools di WebView (`onKeyDown`, `WebSettings`).

## Verifikasi (setelah eksekusi)
- [ ] Uji split-screen & floating window di minimal 3 merek (Samsung/MIUI/realme) → app menolak/mendeteksi.
- [ ] Uji kiosk: siswa tak bisa keluar app, tak bisa buka recents/notifikasi.
- [ ] Uji guard web tetap jalan (fullscreen, reset izin, force selesai).
- [ ] Uji siklus ujian penuh via app: mulai → jawab → selesai → nilai masuk.
- [ ] `docker compose logs cbt-app` tanpa error baru.
