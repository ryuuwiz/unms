<?php

return [
    'status_cache_ttl' => env('MIKROTIK_STATUS_CACHE_TTL', 5), // detik
    'status_timeout' => env('MIKROTIK_STATUS_TIMEOUT', 3), // detik
    // Batas penghapusan PPP Secret per router per eksekusi (rekonsiliasi / clean-orphans). Melebihi batas menghentikan
    // penghapusan, mencatat kandidat, dan memberi tahu NOC -- pengaman terhadap bug yang menghapus banyak akun sekaligus.
    'max_deletes_per_run' => (int) env('MIKROTIK_MAX_DELETES_PER_RUN', 10),
];
