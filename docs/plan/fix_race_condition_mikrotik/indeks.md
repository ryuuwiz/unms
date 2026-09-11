Indeks — Fix Plan Mikrotik Race Condition & Realtime PPP Status
Urutan pengerjaan yang disarankan, dengan link ke masing-masing file prompt siap-pakai untuk Claude Code.
Track A — Race Condition Auto-Provisioning
#	Sprint	File	Dependensi	Risiko
1	ShouldBeUnique pada job per-secret	sprint-a1-shouldbeunique-ppp-jobs.md	—	Kecil
2	WithoutOverlapping pada job router-sync	sprint-a2-withoutoverlapping-router-sync.md	Setelah A1	Kecil-menengah
3	UI debounce tombol provision	sprint-a3-ui-debounce-tombol-provision.md	— (paralel)	Sangat kecil
4	Idempotent create/update di MikrotikService	sprint-a4-idempotent-create-mikrotik-service.md	Setelah A1	Menengah
5	Audit & fix drift detection	sprint-a5-audit-drift-detection.md	Setelah A1-A2, butuh data produksi	Tinggi — perlu review manual
Track B — Realtime PPP Status
#	Sprint	File	Dependensi	Risiko
1	Lazy load + isolasi exception	sprint-b1-lazy-load-isolasi-exception.md	— (paralel dengan Track A)	Kecil-menengah
2	Cache layer + configurable timeout	sprint-b2-cache-layer-ppp-status.md	Setelah B1	Kecil-menengah
3	Batch query (kondisional)	sprint-b3-cek-kelayakan-batch-query.md	Setelah B2, cek data dulu	Kondisional
4	Monitoring & logging Mikrotik untuk superadmin	sprint-b5-monitoring-mikrotik-superadmin.md	Setelah B1 — butuh sesi grilling sendiri sebelum coding (lihat isi file)	Menengah — perlu keputusan skema & volume data dulu
5 Sprint B4 — Configurable timeout (quick win, gabung dengan B2)
Tujuan: Timeout 3 detik hardcoded jadi configurable.
Bisa digabung ke Sprint B2 kalau mau (kecil).
File yang disentuh:

app/Services/Mikrotik/MikrotikService.php

config/mikrotik.php
Tugas: Ganti getClient($router, 3) jadi getClient($router, config('mikrotik.status_timeout', 3)).
Estimasi: sangat kecil, ~15 menit — sarankan digabung ke B2 daripada jadi sprint terpisah agar tidak overhead.

Urutan pengerjaan disarankan
Minggu 1: A1 → A3 (paralel)  +  B1 (paralel, tim/sesi berbeda)
Minggu 2: A2  +  B2
Minggu 3: A4  →  A5 (kumpulkan data produksi dulu sebelum mulai A5)
Minggu 4: Cek kelayakan B3, kerjakan hanya kalau data mendukung

Cara pakai file-file ini
Buka satu file sprint sesuai urutan di atas.
Copy seluruh isi file ke Claude Code sebagai instruksi awal sesi.
Setiap file sudah membatasi scope file yang boleh disentuh, jadi agent tidak perlu (dan sebaiknya tidak diberi) akses ke seluruh codebase sekaligus.
Untuk sprint dengan langkah "Investigasi" (A2, A4, A5) — biarkan agent melapor dulu hasil investigasinya sebelum lanjut edit, jangan skip langkah ini.
Review manual tetap wajib sebelum merge, terutama untuk A2, A4, A5 yang menyentuh perilaku terhadap perangkat produksi.
