<?php

return [
    'file' => 'site_pages.json',

    'items' => [
        'about' => [
            'label' => 'About',
            'description' => 'Our Story page',
            'path' => '/about',
            'default' => true,
        ],
        'contact' => [
            'label' => 'Contact',
            'description' => 'Contact page and form',
            'path' => '/contact',
            'default' => true,
        ],
        'new-arrivals' => [
            'label' => 'New Arrivals',
            'description' => 'Shop page. Uses the collections product list.',
            'path' => '/new-arrivals',
            'default' => false,
        ],
        'bestsellers' => [
            'label' => 'Bestsellers',
            'description' => 'Shop page. Uses the collections product list.',
            'path' => '/bestsellers',
            'default' => false,
        ],
        'limited-edition' => [
            'label' => 'Limited Edition',
            'description' => 'Shop page. Uses the collections product list.',
            'path' => '/limited-edition',
            'default' => false,
        ],
        'sale' => [
            'label' => 'Sale',
            'description' => 'Shop page. Uses the collections product list.',
            'path' => '/sale',
            'default' => false,
        ],
        'blog' => [
            'label' => 'Blog',
            'description' => 'Blog list and every article',
            'path' => '/blog',
            'default' => true,
        ],
        'privacy-policy' => [
            'label' => 'Privacy Policy',
            'description' => '/policies/privacy-policy',
            'path' => '/policies/privacy-policy',
            'default' => true,
        ],
        'terms-and-conditions' => [
            'label' => 'Terms & Conditions',
            'description' => '/policies/terms-and-conditions',
            'path' => '/policies/terms-and-conditions',
            'default' => true,
        ],
        'shipping-policy' => [
            'label' => 'Shipping Policy',
            'description' => '/policies/shipping-policy',
            'path' => '/policies/shipping-policy',
            'default' => true,
        ],
        'refund-policy' => [
            'label' => 'Return & Refund',
            'description' => '/policies/refund-policy',
            'path' => '/policies/refund-policy',
            'default' => true,
        ],
        'cancellation-policy' => [
            'label' => 'Cancellation Policy',
            'description' => '/policies/cancellation-policy',
            'path' => '/policies/cancellation-policy',
            'default' => true,
        ],
        'disclaimer' => [
            'label' => 'Disclaimer',
            'description' => '/policies/disclaimer',
            'path' => '/policies/disclaimer',
            'default' => true,
        ],
        'sustainability' => [
            'label' => 'Sustainability',
            'description' => 'Materials and making. No unpublished eco numbers.',
            'path' => '/sustainability',
            'default' => true,
        ],
        'careers' => [
            'label' => 'Careers',
            'description' => 'Turn on only while you are hiring. Off hides the footer link.',
            'path' => '/careers',
            'default' => false,
        ],
    ],
];
