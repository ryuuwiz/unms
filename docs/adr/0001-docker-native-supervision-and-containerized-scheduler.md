# Docker-Native Process Supervision & Containerized Supercronic Scheduler

Pada arsitektur produksi berbasis Docker (Dokploy/FrankenPHP), kami memutuskan untuk mengandalkan orkestrasi supervisor native Docker (`restart: unless-stopped` dan signal forwarding) untuk mengawasi proses master Laravel Horizon daripada memasang Linux OS `supervisord` di dalam kontainer, serta mengganti perintah pengembangan `schedule:work` dengan runner crontab khusus kontainer `supercronic`.

## Konteks

Dalam instalasi Laravel tradisional pada Virtual Machine (VM) atau Bare Metal, `supervisord` umum digunakan untuk mengawasi `php artisan horizon` dan worker queue agar tetap hidup saat terjadi kegagalan memori atau *crash*. Namun di dalam lingkungan kontainer Docker 12-factor:
1. Memasang `supervisord` di dalam kontainer terisolasi yang hanya menjalankan satu tugas (Horizon) merupakan antipola *double supervision* yang memperlambat propagasi sinyal `SIGTERM`, menutupi metrik *exit status* kontainer, dan menambah *footprint* memori. Master process Horizon sendiri sudah bertindak sebagai supervisor internal yang memantau dan me-restart worker pools (`supervisor-high` dan `supervisor-low`).
2. Perintah `php artisan schedule:work` pada kontainer scheduler hanya ditujukan untuk lingkungan pengembangan lokal (*local development* per dokumentasi resmi Laravel), rentan mengalami *time-drift*, dan tidak menjamin isolasi memori antar eksekusi tugas berkala.

## Opsi yang Dipertimbangkan

1. **Monolithic Container dengan `supervisord`**: Menggabungkan FrankenPHP web server, Horizon master, dan Scheduler ke dalam 1 kontainer tunggal. *Ditolak* karena merusak isolasi log, mempersulit *rolling deploy*, dan menghilangkan kemampuan auto-scaling/restarting independen.
2. **Alpine Built-in `crond`**: Menggunakan cron bawaan Busybox Alpine. *Ditolak* karena sub-proses `crond` tidak mewarisi *environment variables* kontainer (seperti `DB_HOST`, `REDIS_HOST`) secara otomatis dan mengarahkan log ke syslog lokal alih-alih stdout Docker.
3. **Docker-Native + Supercronic** (*Dipilih*): Mempertahankan kontainer *decoupled* (`app`, `horizon`, `scheduler`). Kontainer `horizon` diawasi langsung oleh Docker daemon dengan `stop_grace_period: 60s`. Kontainer `scheduler` menggunakan binary ringan `supercronic` yang mengeksekusi `php artisan schedule:run` setiap menit dengan pewarisan environment variabel lengkap dan log langsung ke stdout.

## Konsekuensi

- **Graceful Termination**: Docker daemon meneruskan `SIGTERM` langsung ke Horizon via Docker `init: true` (tini reaper), memungkinkan Horizon menyelesaikan job MikroTik yang sedang berjalan secara aman sebelum shutdown.
- **Reliabilitas Jadwal**: Tugas berkala Laravel (rekonsiliasi MikroTik, pembuatan invoice harian, isolir, dan snapshot Horizon) dieksekusi secara terisolasi setiap menit tanpa risiko kebocoran memori dari proses daemon jangka panjang.
- **Transparansi Log & Monitoring**: Seluruh output cron dan status antrean dapat dipantau langsung via `docker logs gobilling_scheduler` dan `docker logs gobilling_horizon` tanpa memerlukan agregasi log supervisor internal.
