# Roadmap MVP Platform Hospitality All-in-One (Referensi: Guirez)

Oct 12, 2026 · @Ardha

## Ringkasan dan asumsi

MVP production-ready dengan lima modul (PMS, Booking Engine, Channel Manager, CRM, Smart Plugins) bisa selesai dalam sekitar 26 minggu oleh tim 4-5 orang di stack Laravel. Estimasinya 22-30 person-month, dibagi dalam 13 sprint dua mingguan, dan berakhir di go-live pilot dengan 1-2 hotel.

Asumsi yang dipakai (ubah bila tidak sesuai):

- Tim inti: 1 tech lead Laravel senior, 2 developer Laravel full-stack, 1 frontend engineer, 1 QA. Pendukung paruh waktu: DevOps, designer, dan product owner berlatar hospitality.
- Biaya Rp 15-25 juta per orang per bulan, sehingga total sekitar Rp 330-750 juta.
- Channel manager lewat aggregator (misalnya Channex), bukan koneksi langsung ke OTA.
- Pembayaran lewat Midtrans atau Xendit dengan halaman pembayaran hosted.
- 1-2 hotel pilot sudah didapat sejak Fase 0.
- Bahasa awal: Indonesia dan Inggris.
- Stack Mindimedia (Laravel, MySQL, Redis) adalah dugaan dari website dan lowongan mereka, bukan data pasti.

## Keputusan arsitektur

Seluruh produk dibangun sebagai satu monolit modular Laravel, dan keputusan di bawah dikunci pada Fase 0 supaya tim tidak berganti arah di tengah jalan.

| Area | Keputusan |
| --- | --- |
| Aplikasi | Monolit modular Laravel, MySQL 8, Redis, antrian dengan Horizon |
| Multi-tenant | Satu database dengan kolom tenant/property di tiap tabel (database per tenant terlalu mahal dioperasikan di MVP) |
| Back office | Filament untuk master data, user, housekeeping, laporan |
| UI interaktif | Vue 3 + Inertia untuk kalender kamar |
| Widget booking | JavaScript ringan yang bisa di-embed, plus halaman hosted sebagai cadangan |
| Channel manager | Lewat aggregator, bukan koneksi langsung ke OTA |
| Pembayaran | Midtrans atau Xendit dengan hosted payment page, supaya beban PCI minimal |
| Infrastruktur | Cloudflare di depan, VPS terkelola (Forge atau Ploi), staging terpisah dari production |

## Timeline per fase

Tujuh fase berjalan paralel dan saling tumpang tindih, dan enam gate mengawal jalan menuju go-live pilot di minggu 26.

&#91;embedded content: roadmap 26 minggu · 7 fase, 6 gate\]

Fase 2 sampai 5 dimulai sebelum fase sebelumnya selesai; hanya M1 yang menahan semua modul lain sampai mesin inventori terbukti aman.

## Isi tiap fase

Mesin rate dan inventori dibangun paling awal karena semua modul lain bergantung padanya; fase lain berjalan paralel dan saling tumpang tindih.

### Fase 0 (minggu 1-2): discovery

- Domain model dan ERD: properti, tipe kamar, unit kamar, rate plan, inventori harian, reservasi, folio, tamu.
- Pilih vendor (aggregator channel manager, payment gateway, email), tulis ADR, siapkan CI/CD awal dan backlog.
- Cari 1-2 hotel pilot sekarang, jangan menunggu produk jadi.
- Ajukan approval Meta API untuk feed Instagram sejak awal, karena prosesnya lama.

### Fase 1 (minggu 3-6): platform core dan mesin inventori

- Auth, role dan permission, audit log, billing langganan, i18n, multi-currency, pajak dan service charge.
- Ketersediaan per tipe kamar per tanggal, restriksi (min stay, closed to arrival, stop sell), dan rate plan dengan aturan musiman.
- Pencegahan overbooking lewat transaksi database dengan row lock, ditambah idempotency key pada semua endpoint yang mengubah stok.
- Test konkurensi (ratusan booking bersamaan ke kamar terakhir) sudah ada sebelum modul lain dibangun di atasnya.

### Fase 2 (minggu 5-12): PMS

- Reservasi (buat, ubah, batal, no-show), tape chart dengan drag-and-drop, penugasan kamar.
- Check-in dan check-out, housekeeping board, folio, deposit, invoice PDF, night audit sederhana.
- Laporan okupansi, ADR, RevPAR, dan pendapatan; role staf dan jejak audit.

### Fase 3 (minggu 9-16): Booking Engine

- Alur pencarian sampai bayar, kode promo, add-on, dan email konfirmasi.
- Bahasa Indonesia dan Inggris dulu; pajak dan mata uang tampil benar.
- Event ke GA4, GTM, dan Meta Pixel; rate limiting dan anti-bot.

### Fase 4 (minggu 13-20): Channel Manager

- Booking.com, Expedia, Agoda, dan Airbnb lewat aggregator, dengan mapping kamar dan rate.
- Webhook reservasi yang idempotent, retry, dan dead-letter queue.
- Job rekonsiliasi harian yang membandingkan stok di sistem dan di OTA, plus dashboard log sinkronisasi.

### Fase 5 (minggu 15-22): CRM dan Smart Plugins

- **CRM:** profil tamu terpadu, segmentasi, email otomatis (pra-kedatangan, pasca-menginap, permintaan ulasan), manajemen consent.
- **Price-match:** untuk MVP, bandingkan dengan harga yang dikirim hotel sendiri ke OTA, tanpa scraping. Batasannya, promo atau diskon member OTA bisa membuat harga yang dilihat tamu berbeda, jadi beri offset yang bisa diatur. Data kompetitor berlisensi menyusul.
- **Live chat:** integrasikan Chatwoot self-hosted dulu, jangan bangun dari nol.
- **Loyalty:** poin dan tier sederhana yang auto-apply di booking engine.
- **Feed Instagram:** butuh approval Meta dan perpanjangan token berkala.
- **Upsell dan promo:** engine kode promo dan add-on.
- **Personalisasi pengunjung:** snippet JS dengan banner consent.

### Fase 6 (minggu 21-26): hardening dan launch

- Load test, pentest eksternal, dan drill restore backup.
- Wizard onboarding, import data dari CSV sistem lama, dokumentasi, dan runbook insiden.
- Go-live pilot dengan 1-2 hotel dan dukungan langsung dari tim; semua item di checklist production-ready harus lolos lebih dulu.

## Checklist production-ready

Semua item ini berjalan lintas fase dan harus tercentang sebelum hotel pertama go-live di minggu 26.

### Keamanan

- [ ] Audit OWASP Top 10 selesai
- [ ] 2FA untuk akun admin
- [ ] Enkripsi data PII dan secret management
- [ ] WAF aktif di Cloudflare
- [ ] Scan dependency otomatis di CI
- [ ] Pentest eksternal sebelum launch

### Kepatuhan

- [ ] Kepatuhan UU PDP (No. 27/2022) untuk data tamu
- [ ] Consent yang bisa diaudit, kebijakan privasi, dan ToS
- [ ] Hak akses dan hapus data untuk tamu Eropa (ala GDPR)
- [ ] Pembayaran lewat hosted page, cukup di level PCI SAQ-A

### Keandalan

- [ ] Backup harian dengan restore yang benar-benar diuji
- [ ] Target RPO dan RTO tertulis
- [ ] Deploy tanpa downtime dan feature flag
- [ ] Antrian dengan retry dan dead-letter queue

### Observabilitas

- [ ] Sentry dan log terstruktur
- [ ] Uptime monitoring dan status page
- [ ] Alert untuk antrian macet dan kegagalan sinkronisasi OTA (metrik bisnis paling kritis)

### Kualitas

- [ ] Feature test untuk inventori dan folio
- [ ] Contract test untuk aggregator
- [ ] E2E (Playwright) untuk alur booking
- [ ] Load test (k6) untuk lonjakan booking

### Operasional

- [ ] Runbook insiden dan prosedur on-call
- [ ] Dokumentasi onboarding dan wizard setup properti
- [ ] Import data dari CSV sistem lama

## Gate keputusan

Setiap gate di timeline punya satu kriteria lulus; M1 paling ketat karena kegagalannya menahan semua modul lain.

- **M1:** mesin inventori lolos test konkurensi. Kalau tidak, semua modul lain ditahan.
- **M2:** PMS alpha dipakai harian oleh tim internal atau hotel pilot.
- **M3:** booking engine menghasilkan booking nyata pertama di website hotel pilot.
- **M4:** sinkronisasi dua arah dengan sandbox OTA stabil minimal 2 minggu tanpa selisih stok.
- **M5:** semua fitur lengkap di staging dan pentest selesai.
- **M6:** go-live pilot dengan 1-2 hotel, dengan dukungan langsung dari tim.

## Ditunda setelah MVP dan risiko terbesar

Tiga risiko paling mungkin menggeser jadwal, dan daftar fitur di bawah sengaja tidak masuk MVP supaya jadwal 26 minggu tetap realistis.

### Risiko terbesar

- **Hotel pilot terlambat didapat,** sehingga PMS dibangun tanpa umpan balik nyata. Karena itu pencarian pilot masuk di Fase 0.
- **Onboarding sebagai partner aggregator atau OTA lebih lama dari perkiraan.** Urus sejak minggu pertama.
- **Scope creep pada plugin.** Kalau jadwal mepet, korbankan loyalty dan feed Instagram sebelum menyentuh PMS, booking engine, atau channel manager.

### Ditunda setelah MVP

- Aplikasi mobile native
- POS restoran
- Integrasi akuntansi
- Grup dan MICE
- Pelaporan multi-properti untuk chain
- Integrasi kunci pintu
- Dynamic pricing berbasis AI
- Koneksi langsung ke OTA
- White-label
