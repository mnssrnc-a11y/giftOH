<?php

/*
|--------------------------------------------------------------------------
| Price reference (₱): the seed for the Firebase price list
|--------------------------------------------------------------------------
| Market prices gathered for the foundation interview. The list lives in Firebase at
| price_list/{group}/items/{key}, grouped into Food, Medical and Cleaning materials; only the
| super admin (and the monthly AI price update) change it. This file only seeds an empty list.
| Each item: [name, size/unit, min price, max price], listed under its type. Keys must stay stable.
*/

return [
    'food' => [
        'label' => 'Food',
        'types' => [
            'Rice (per kilo)' => [
                'rice-regular' => ['Regular-milled rice', 'per kg', 40.00, 52.00],
                'rice-well' => ['Well-milled rice', 'per kg', 45.00, 58.00],
                'rice-premium' => ['Premium/jasmine rice', 'per kg', 50.00, 70.00],
                'rice-nfa' => ['NFA/economy program rice', 'per kg', 20.00, 45.00],
            ],
            'Canned goods' => [
                'sardines' => ['Sardines (555/Ligo/Mega)', '155 g', 19.00, 33.00],
                'corned-beef' => ['Corned Beef (Argentina)', '150 g', 32.00, 55.00],
            ],
            'Instant noodles' => [
                'mami' => ['Lucky Me Beef/Chicken Mami', '55 g', 7.75, 10.50],
                'pancit-canton' => ['Lucky Me Pancit Canton', '60–80 g', 13.85, 18.00],
                'cup-noodles' => ['Nissin Cup Noodles', '40 g', 21.00, 26.00],
            ],
            'Biscuits' => [
                'skyflakes-single' => ['SkyFlakes Crackers', 'single pack', 9.81, 12.00],
                'skyflakes-250' => ['SkyFlakes Crackers', '250 g pack', 60.00, 60.00],
                'fita' => ['Fita Crackers', 'single pack', 9.81, 9.81],
            ],
            'Milk' => [
                'bear-sachet' => ['Bear Brand Powdered Milk', '33–35 g sachet', 15.00, 20.00],
                'bear-135' => ['Bear Brand Powdered Milk', '135 g', 50.00, 50.00],
                'bear-300' => ['Bear Brand Powdered Milk', '300–360 g', 120.00, 270.00],
                'bear-sterilized' => ['Bear Brand Sterilized Milk', '200 ml', 25.25, 25.25],
            ],
            'Coffee' => [
                'nescafe-3in1' => ['Nescafé 3-in-1 Original', 'twin pack', 12.50, 16.00],
                'kopiko' => ['Kopiko Brown/Blanca/Black', 'twin pack', 13.00, 17.00],
                'great-taste' => ['Great Taste White', 'twin pack', 12.00, 16.00],
                'nescafe-jar' => ['Nescafé Classic (jar)', '170–200 g', 207.50, 207.50],
            ],
        ],
    ],
    'medical' => [
        'label' => 'Medical',
        'types' => [
            'Medicine (per piece)' => [
                'paracetamol-500' => ['Paracetamol 500 mg (generic/Watsons/RiteMed)', 'per tablet', 2.00, 2.75],
                'biogesic' => ['Biogesic (branded paracetamol)', 'per tablet', 4.25, 5.50],
                'tempra-forte' => ['Tempra Forte 500 mg', 'per tablet', 7.75, 7.75],
                'amoxicillin-500' => ['Amoxicillin 500 mg capsule', 'per capsule', 5.00, 20.75],
                'co-amoxiclav' => ['Co-amoxiclav 625 mg', 'per tablet', 48.75, 48.75],
                'mefenamic' => ['Mefenamic Acid 250 mg', 'per capsule', 2.00, 5.50],
            ],
            'Medicine (bottles, sachets, injectables)' => [
                'amoxicillin-susp' => ['Amoxicillin 100 mg suspension', 'per bottle', 35.00, 35.00],
                'biogesic-syrup' => ['Biogesic Paracetamol Syrup', '60 ml', 173.75, 173.75],
                'paracetamol-syrup' => ['Paracetamol 250 mg/5 ml syrup', 'per bottle (varies by brand)', 35.00, 175.00],
                'ors-small' => ['Oral Rehydration Salts (ORS)', 'small sachet', 4.61, 11.00],
                'ors-hydrite' => ['ORS (Hydrite)', 'larger pack, each', 20.14, 21.54],
                'gentamicin' => ['Gentamicin 80 mg', 'ampule', 6.72, 6.72],
                'vit-b-complex' => ['Vitamin B Complex (Vitacore/Neurobe)', '3 ml ampule', 47.54, 47.54],
                'vit-b1b6b12' => ['Vitamin B1+B6+B12', 'ampule/vial (by brand)', 9.00, 858.73],
                'amikacin' => ['Amikacin 125–250 mg/ml', '2 ml vial', 15.33, 400.00],
                'sterile-water' => ['Sterile Water for Injection', '10 ml ampule', 10.50, 160.05],
            ],
        ],
    ],
    'cleaning' => [
        'label' => 'Cleaning materials',
        'types' => [
            'Cleaning supplies' => [
                'zonrox-100' => ['Zonrox Bleach (Original/Lemon/Floral)', '100 ml', 9.75, 11.25],
                'zonrox-250' => ['Zonrox Bleach', '250 ml', 17.75, 18.75],
                'zonrox-500' => ['Zonrox Bleach', '500 ml', 28.20, 30.20],
                'zonrox-1l' => ['Zonrox Bleach', '1 L', 47.75, 54.00],
                'zonrox-gal' => ['Zonrox Bleach', '1 gallon (3.78 L)', 163.00, 304.70],
                'champion-bar' => ['Champion Detergent Bar', '800 g', 93.00, 93.00],
                'pride-2kg' => ['Pride Powder Detergent', '2 kg', 182.00, 182.00],
                'surf-breeze' => ['Surf/Breeze Powder Detergent', 'varies', 30.00, 250.00],
                'ariel-liquid' => ['Ariel Liquid Detergent', 'varies', 100.00, 400.00],
                'tide-pods' => ['Tide Pods', 'pack', 800.00, 1500.00],
                'dishwashing' => ['Dishwashing liquid (Joy/Smart/local brands)', '700 ml – 1 L', 150.00, 325.00],
                'dishwashing-refill' => ['Dishwashing liquid refill (DIY/sari-sari)', '3–16 L kit', 90.00, 345.00],
                'toilet-tablets' => ['Toilet bowl cleaner tablets (Clorox-type)', '4-pack (imported)', 750.00, 750.00],
            ],
        ],
    ],
];
