<?php

return [
    [
        'title' => 'Dashboard',
        'icon' => 'home',
        'route' => 'dashboard',
        'permission' => null, // Bebas akses
    ],
    [
        'title' => 'Users',
        'icon' => 'users',
        'route' => 'users.index', // Route halaman dummy
        'permission' => 'view_users', // Hanya role yang punya permission ini
    ],
];
