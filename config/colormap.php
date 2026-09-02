<?php

$colors = [
    // Black
    'Black' => '#000000',
    'Jet Black' => '#0A0A0A',
    'Matte Black' => '#1A1A1A',
    'Carbon Black' => '#212121',
    'Black Steel' => '#3B3B3B',
    'Ceramic Black' => '#121212',
    'Sunburst Black' => '#2D2D2D',

    // White
    'White' => '#FFFFFF',
    'Off White' => '#FAF9F6',
    'Pearl White' => '#F8F6F0',
    'Ivory' => '#FFFFF0',
    'Ceramic White' => '#FAFAFA',

    // Grey / Silver
    'Grey' => '#808080',
    'Gray' => '#808080',
    'Light Grey' => '#D3D3D3',
    'Dark Grey' => '#555555',
    'Slate Grey' => '#708090',
    'Smoke' => '#6E6E6E',
    'Anthracite' => '#383E42',
    'Charcoal' => '#36454F',
    'Fumé' => '#4E4E50',
    'Silver' => '#C0C0C0',
    'Sunburst Silver' => '#D7D7D7',

    // Blue
    'Blue' => '#2563EB',
    'Dark Blue' => '#00008B',
    'Royal Blue' => '#4169E1',
    'Cobalt Blue' => '#0047AB',
    'Navy Blue' => '#1E3A8A',
    'Midnight Blue' => '#191970',
    'Sky Blue' => '#38BDF8',
    'Ice Blue' => '#CFEFFF',
    'Tiffany Blue' => '#81D8D0',
    'Petrol Blue' => '#005F6A',
    'Teal' => '#008080',
    'Turquoise' => '#40E0D0',
    'Teal Blue' => '#75a9b9',
    'Aqua' => '#00FFFF',
    'Sunburst Blue' => '#2F5DAA',
    'Grayish Blue' => '#5F9EA0',
    'Light Blue' => '#ADD8E6',

    // Green
    'Green' => '#16A34A',
    'Forest Green' => '#228B22',
    'Emerald Green' => '#50C878',
    'British Racing Green' => '#004225',
    'Olive Green' => '#556B2F',
    'Sage Green' => '#5a6258',
    'Mint Green' => '#98FF98',
    'Lime Green' => '#32CD32',
    'Light Green' => '#90EE90',
    'Fluorescent Green' => '#00FF00',
    'Pastel Green'  => '#77DD77',
    'Sea Green' => '#2E8B57',

    // Red
    'Red' => '#DC2626',
    'Crimson' => '#DC143C',
    'Ruby Red' => '#9B111E',
    'Maroon' => '#800000',
    'Burgundy' => '#800020',
    'Coral' => '#FF7F50',

    // Orange / Yellow
    'Orange' => '#F97316',
    'Yellow' => '#FACC15',

    // Brown
    'Brown' => '#8B4513',
    'Dark Brown' => '#654321',
    'Chocolate Brown' => '#7B3F00',
    'Cognac' => '#9A463D',
    'Mahogany Brown' => '#C04000',
    'Mocha' => '#967969',
    'Tan' => '#D2B48C',
    'Sand' => '#C2B280',
    'Stone' => '#928E85',
    'Khaki' => '#C3B091',
    'Beige' => '#F5F5DC',
    'Cream' => '#FFFDD0',
    'Peach' => '#FFE5B4',
    'Nude' => '#FBE8EB',

    // Purple / Pink
    'Purple' => '#7C3AED',
    'Violet' => '#8F00FF',
    'Lavender' => '#E6E6FA',
    'Pink' => '#EC4899',
    'Salmon Pink' => '#FF91A4',
    'Fuchsia' => '#FF00FF',

    // Gold & Metallics
    'Gold' => '#D4AF37',
    'Yellow Gold' => '#FFD700',
    'Rose Gold' => '#B76E79',
    'Everose Gold' => '#C08081',
    'White Gold' => '#E5E4E2',
    'Champagne' => '#F7E7CE',
    'Bronze' => '#CD7F32',
    'Copper' => '#B87333',
    'Steel' => '#B0B7C0',
    'Stainless Steel' => '#C0C0C0',
    'Titanium' => '#878681',
    'Platinum' => '#E5E4E2',
    'Chrome' => '#D9D9D9',
    'Palladium' => '#CED0DD',
    'Gunmetal' => '#2A3439',

    // Special Materials
    'Carbon' => '#2C2C2C',
    'Meteorite' => '#7D7F80',
    'Mother of Pearl' => '#F5F3FF',
    'MOP' => '#F5F3FF',
    'Skeleton' => '#444444',
    'Transparent' => '#F8F8FF',
    'Clear' => '#F8F8FF',

    'Multicolor' => 'conic-gradient(#FF0000, #FFA500, #FACC15, #16A34A, #2563EB, #7C3AED, #FF0000)',
];

ksort($colors, SORT_NATURAL | SORT_FLAG_CASE);

return $colors;
