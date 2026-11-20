<?php

return [
    'file' => 'homepage_sections.json',

    'items' => [
        // Home page (/)
        'media_feature' => [
            'label' => 'Campaign Video',
            'description' => 'CRAFTED FOR THE BOLD — video/image block',
            'default' => true,
            'page' => 'both',
        ],
        'home_products' => [
            'label' => 'Home Products',
            'description' => '12-product grid on the Home page (4 per row)',
            'default' => true,
            'page' => 'home',
        ],
        'home_cta' => [
            'label' => 'Home CTA',
            'description' => 'Wear Confidence. Wear Liwaas. — Home page CTA',
            'default' => true,
            'page' => 'home',
        ],

        // Brand page (/brand)
        'hero' => [
            'label' => 'Hero',
            'description' => 'WEAR CONFIDENCE — Brand page main image and shop button',
            'default' => true,
            'page' => 'brand',
        ],
        'marquee' => [
            'label' => 'Marquee',
            'description' => 'Gold bar with scrolling brand words (Brand page)',
            'default' => true,
            'page' => 'brand',
        ],
        'trending' => [
            'label' => 'Trending Products',
            'description' => 'Trending products slider (Brand page)',
            'default' => true,
            'page' => 'brand',
        ],
        'collections' => [
            'label' => 'Featured Collections',
            'description' => 'EVERY DROP DELIBERATE (Brand page)',
            'default' => true,
            'page' => 'brand',
        ],
        'brand_story' => [
            'label' => 'Brand Story',
            'description' => 'BORN FROM SILENCE (Brand page)',
            'default' => true,
            'page' => 'brand',
        ],
        'features' => [
            'label' => 'Features',
            'description' => 'NOT JUST A TSHIRT. AN EXPERIENCE. (Brand page)',
            'default' => true,
            'page' => 'brand',
        ],
        'video_banner' => [
            'label' => 'Video / Campaign Banner',
            'description' => 'THE UNIFORM of confidence (Brand page)',
            'default' => true,
            'page' => 'brand',
        ],
        'testimonials' => [
            'label' => 'Testimonials',
            'description' => 'Live customer reviews (Brand page)',
            'default' => true,
            'page' => 'brand',
        ],
        'newsletter' => [
            'label' => 'Newsletter',
            'description' => 'JOIN THE INNER CIRCLE (Brand page)',
            'default' => true,
            'page' => 'brand',
        ],
    ],
];
