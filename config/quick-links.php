<?php

return [
    'best-watches-under-2000' => [
        'title' => 'Best Watches Under ₹2,000',
        'slug' => 'best-watches-under-2000',
        'description' => 'Explore watches under ₹2,000 from popular brands in India, with specifications, prices, and retailer availability to help you compare your options.',
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
        'description' => 'Explore men’s and unisex watches under ₹5,000 in India, with specifications, prices, and retailer availability to compare across different brands.',
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
        'description' => 'Explore women’s and unisex watches under ₹5,000 in India, with specifications, prices, and retailer availability to compare across different brands.',
        'filters' => [
            'gender' => 'Women',
            'max_price' => 5000
        ],
        'related' => [
            'best-watches-under-2000',
            'best-casio-watches-under-5000'
        ]
    ],

    'best-casio-watches-under-5000' => [
        'title' => 'Best Casio Watches Under ₹5,000',
        'slug' => 'best-casio-watches-under-5000',
        'description' => 'Explore Casio watches under ₹5,000 in India, with specifications, current prices, and retailer availability to compare models within your budget.',
        'filters' => [
            'brand' => 'Casio',
            'max_price' => 5000
        ],
        'related' => [
            'best-watches-for-men-under-5000',
            'best-watches-for-women-under-5000'
        ]
    ],

    'best-mechanical-watches-under-20000' => [
        'title' => 'Best Mechanical Watches Under ₹20,000',
        'slug' => 'best-mechanical-watches-under-20000',
        'description' => 'Explore mechanical watches under ₹20,000 in India, with specifications, prices, and retailer availability to compare models across different brands.',
        'filters' => [
            'movement_type' => 'Mechanical',
            'max_price' => 20000
        ],
        'related' => [
            'best-automatic-watches-under-20000'
        ]
    ],

    'best-automatic-watches-under-20000' => [
        'title' => 'Best Automatic Watches Under ₹20,000',
        'slug' => 'best-automatic-watches-under-20000',
        'description' => 'Explore automatic watches under ₹20,000 in India, with specifications, prices, and retailer availability to compare models across different brands.',
        'filters' => [
            'movement_type' => 'Automatic',
            'max_price' => 20000
        ],
        'related' => [
            'best-mechanical-watches-under-20000'
        ]
    ],
];
