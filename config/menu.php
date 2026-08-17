<?php

return [
    'standalone' => [
        [
            'title' => 'Dashboard',
            'icon' => 'home',
            'route' => 'dashboard',
            'active' => 'dashboard',
            'permission' => null,
        ],
    ],
    'groups' => [
        [
            'heading' => 'Pelanggan & Layanan',
            'icon' => 'user-group',
            'expandable' => true,
            'expanded' => true,
            'items' => [
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
            'heading' => 'Keuangan & Billing',
            'icon' => 'banknotes',
            'expandable' => true,
            'expanded' => false,
            'items' => [
                [
                    'title' => 'Tagihan (Invoice)',
                    'icon' => 'document-text',
                    'route' => 'invoice.index',
                    'active' => 'invoice.*',
                    'permission' => 'invoice.lihat',
                ],
                [
                    'title' => 'Riwayat Pembayaran',
                    'icon' => 'banknotes',
                    'route' => 'pembayaran.index',
                    'active' => 'pembayaran.*',
                    'permission' => 'pembayaran.lihat',
                ],
                [
                    'title' => 'Promo & Diskon',
                    'icon' => 'tag',
                    'route' => 'promo.index',
                    'active' => 'promo.*',
                    'permission' => 'promo.lihat',
                ],
                [
                    'title' => 'Laporan Keuangan',
                    'icon' => 'chart-bar',
                    'route' => 'laporan.billing',
                    'active' => 'laporan.*',
                    'permission' => 'laporan.lihat',
                ],
            ],
        ],
        [
            'heading' => 'Jaringan & Infrastruktur',
            'icon' => 'server-stack',
            'expandable' => true,
            'expanded' => false,
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
            'icon' => 'map',
            'expandable' => true,
            'expanded' => false,
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
            'icon' => 'shield-check',
            'expandable' => true,
            'expanded' => false,
            'items' => [
                [
                    'title' => 'Pengguna',
                    'icon' => 'users',
                    'route' => 'users.index',
                    'active' => 'users.*',
                    'permission' => 'pengguna.lihat',
                ],
                [
                    'title' => 'Peran (Role)',
                    'icon' => 'shield-check',
                    'route' => 'roles.index',
                    'active' => 'roles.*',
                    'permission' => 'peran.lihat',
                ],
            ],
        ],
    ],
];
