<?php

return [
    'status_cache_ttl' => env('MIKROTIK_STATUS_CACHE_TTL', 5), // detik
    'status_timeout' => (int) env('MIKROTIK_STATUS_TIMEOUT', 4), // detik; connect + baca harus < $timeout PingRouterJob (10)
    // Batas penghapusan PPP Secret per router per eksekusi (rekonsiliasi / clean-orphans). Melebihi batas menghentikan
    // penghapusan, mencatat kandidat, dan memberi tahu NOC -- pengaman terhadap bug yang menghapus banyak akun sekaligus.
    'max_deletes_per_run' => (int) env('MIKROTIK_MAX_DELETES_PER_RUN', 10),
    // Subnet dan rate-limit profile EXPIRED (isolir) yang dibuat billing di setiap router (ADR-0071).
    'isolir_subnet' => env('MIKROTIK_ISOLIR_SUBNET', '172.30.0.0/16'),
    'isolir_rate_limit' => env('MIKROTIK_ISOLIR_RATE_LIMIT', '256k/256k'),
];
