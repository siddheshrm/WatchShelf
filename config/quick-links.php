<?php

return [
    'best-watches-under-2000' => [
        'title' => 'Best Watches Under ₹2,000',
        'slug' => 'best-watches-under-2000',
        'description' => 'Discover the best watches under ₹2,000, with options from popular brands and retailers in India.',
        'filters' => [
            'max_price' => 2000
        ],
        'related' => [
            'best-watches-for-men-under-5000',
            'best-watches-for-women-under-5000'
        ]
    ],

    'best-watches-for-men-under-5000' => [
        'title' => 'Best Watches for Men Under ₹5,000',
        'slug' => 'best-watches-for-men-under-5000',
        'description' => 'Explore the best watches for men under ₹5,000 from popular brands and retailers in India.',
        'filters' => [
            'gender' => 'Men',
            'max_price' => 5000
        ],
        'related' => [
            'best-watches-under-2000',
            'best-casio-watches-under-5000'
        ]
    ],

    'best-watches-for-women-under-5000' => [
        'title' => 'Best Watches for Women Under ₹5,000',
        'slug' => 'best-watches-for-women-under-5000',
        'description' => 'Explore the best watches for women under ₹5,000 from popular brands and retailers in India.',
        'filters' => [
            'gender' => 'Women',
            'max_price' => 5000
        ],
        'related' => [
            'best-watches-under-2000',
            'best-casio-watches-under-5000'
        ]
    ],

    'best-mechanical-watches-under-15000' => [
        'title' => 'Best Mechanical Watches Under ₹15,000',
        'slug' => 'best-mechanical-watches-under-15000',
        'description' => 'Explore the best mechanical watches under ₹15,000 from popular brands and retailers in India.',
        'filters' => [
            'movement_type' => 'Mechanical',
            'max_price' => 15000
        ],
        'related' => []
    ],

    'best-casio-watches-under-5000' => [
        'title' => 'Best Casio Watches Under ₹5,000',
        'slug' => 'best-casio-watches-under-5000',
        'description' => 'Explore the best Casio watches under ₹5,000, with current prices and availability from retailers in India.',
        'filters' => [
            'brand' => 'Casio',
            'max_price' => 5000
        ],
        'related' => [
            'best-watches-for-men-under-5000',
            'best-watches-for-women-under-5000'
        ]
    ],
];
