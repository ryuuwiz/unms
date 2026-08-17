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
                'route' => 'customers.index',
                'active' => 'customers.*',
                'permission' => 'view_customers',
            ],
            [
                'title' => 'Paket Internet',
                'icon' => 'bolt',
                'route' => 'packages.index',
                'active' => 'packages.*',
                'permission' => 'view_packages',
            ],
            [
                'title' => 'Pengguna',
                'icon' => 'users',
                'route' => 'users.index',
                'active' => 'users.*',
                'permission' => 'manage_users',
            ],
        ],
    ],
    [
        'heading' => 'Administrasi',
        'items' => [
            [
                'title' => 'Peran',
                'icon' => 'shield-check',
                'route' => 'roles.index',
                'active' => 'roles.*',
                'permission' => 'manage_roles',
            ],
        ],
    ],
];
