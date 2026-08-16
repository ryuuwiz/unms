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
