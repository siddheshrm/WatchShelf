<?php

return [
    'best-watches-under-2000' => [
        'title' => 'Best Watches Under ₹2,000',
        'slug' => 'best-watches-under-2000',
        'filters' => [
            'max_price' => 2000
        ]
    ],

    'best-watches-for-men-under-5000' => [
        'title' => 'Best Watches for Men Under ₹5,000',
        'slug' => 'best-watches-for-men-under-5000',
        'filters' => [
            'gender' => 'Men',
            'max_price' => 5000
        ]
    ],

    'best-watches-for-women-under-5000' => [
        'title' => 'Best Watches for Women Under ₹5,000',
        'slug' => 'best-watches-for-women-under-5000',
        'filters' => [
            'gender' => 'Women',
            'max_price' => 5000
        ]
    ],

    'best-mechanical-watches-under-15000' => [
        'title' => 'Best Mechanical Watches Under ₹15,000',
        'slug' => 'best-mechanical-watches-under-15000',
        'filters' => [
            'movement_type' => 'Mechanical',
            'max_price' => 15000
        ]
    ],

    'best-casio-watches-under-5000' => [
        'title' => 'Best Casio Watches Under ₹5,000',
        'slug' => 'best-casio-watches-under-5000',
        'filters' => [
            'brand' => 'Casio',
            'max_price' => 5000
        ]
    ],
];