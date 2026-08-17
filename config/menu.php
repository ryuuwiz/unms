<?php

return [
    [
        'heading' => 'Menu Utama',
        'items' => [
            [
                'title' => 'Dashboard',
                'icon' => 'home',
                'route' => 'dashboard',
                'active' => 'dashboard',
                'permission' => null,
            ],
            [
                'title' => 'Pelanggan',
                'icon' => 'user-group',
                'route' => 'pelanggan.index',
                'active' => 'pelanggan.*',
                'permission' => 'pelanggan.lihat',
            ],
            [
                'title' => 'Layanan Pelanggan',
                'icon' => 'signal',
                'route' => 'layanan-pelanggan.index',
                'active' => 'layanan-pelanggan.*',
                'permission' => 'layanan_pelanggan.lihat',
            ],
            [
                'title' => 'Paket Layanan',
                'icon' => 'queue-list',
                'route' => 'paket-layanan.index',
                'active' => 'paket-layanan.*',
                'permission' => 'paket_layanan.lihat',
            ],
            [
                'title' => 'Profil Bandwidth',
                'icon' => 'bolt',
                'route' => 'profil-bandwidth.index',
                'active' => 'profil-bandwidth.*',
                'permission' => 'profil_bandwidth.lihat',
            ],
        ],
    ],
    [
        'heading' => 'Jaringan & Infrastruktur',
        'items' => [
            [
                'title' => 'Router',
                'icon' => 'server',
                'route' => 'router.index',
                'active' => 'router.*',
                'permission' => 'router.lihat',
            ],
            [
                'title' => 'IP Pool',
                'icon' => 'circle-stack',
                'route' => 'ip-pool.index',
                'active' => 'ip-pool.*',
                'permission' => 'ip_pool.lihat',
            ],
        ],
    ],
    [
        'heading' => 'Area & Wilayah',
        'items' => [
            [
                'title' => 'Kota',
                'icon' => 'building-office-2',
                'route' => 'wilayah.kota.index',
                'active' => 'wilayah.kota.*',
                'permission' => 'wilayah.lihat',
            ],
            [
                'title' => 'Kecamatan',
                'icon' => 'map',
                'route' => 'wilayah.kecamatan.index',
                'active' => 'wilayah.kecamatan.*',
                'permission' => 'wilayah.lihat',
            ],
            [
                'title' => 'Kelurahan',
                'icon' => 'map-pin',
                'route' => 'wilayah.kelurahan.index',
                'active' => 'wilayah.kelurahan.*',
                'permission' => 'wilayah.lihat',
            ],
            [
                'title' => 'Perumahan',
                'icon' => 'home-modern',
                'route' => 'wilayah.perumahan.index',
                'active' => 'wilayah.perumahan.*',
                'permission' => 'wilayah.lihat',
            ],
        ],
    ],
    [
        'heading' => 'Administrasi',
        'items' => [
            [
                'title' => 'Pengguna',
                'icon' => 'users',
                'route' => 'users.index',
                'active' => 'users.*',
                'permission' => 'pengguna.lihat',
            ],
            [
                'title' => 'Peran',
                'icon' => 'shield-check',
                'route' => 'roles.index',
                'active' => 'roles.*',
                'permission' => 'peran.lihat',
            ],
        ],
    ],
];
