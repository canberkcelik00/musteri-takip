<?php

return [
    'currency' => [
        'TRY' => 'TL',
        'USD' => 'USD',
        'EUR' => 'EUR',
    ],

    'customer_service_status' => [
        'active' => 'Aktif',
        'cancelled' => 'İptal edildi',
    ],

    'charge_status' => [
        'open' => 'Açık',
        'paid' => 'Ödendi',
        'cancelled' => 'İptal edildi',
    ],

    'usage_level' => [
        'nearly_full' => 'Dolmak üzere',
        'full' => 'Dolu',
    ],

    'refresh_interval_unit' => [
        'minute' => 'Dakika',
        'hour' => 'Saat',
        'day' => 'Gün',
    ],

    'digest_frequency' => [
        'daily' => 'Günlük',
        'monthly' => 'Aylık',
    ],

    'allocation_source' => [
        'payment' => 'Ödeme',
        'credit_balance' => 'Alacak bakiyesi',
    ],

    'server_type' => [
        'whm' => 'WHM (reseller)',
        'cpanel' => 'cPanel',
    ],

    'service_type' => [
        'hosting' => 'Hosting',
        'domain' => 'Domain',
        'maintenance' => 'Bakım',
        'ssl' => 'SSL',
        'email' => 'Kurumsal e-posta',
        'license' => 'Lisans',
        'other' => 'Diğer',
    ],

    'service_type_details' => [
        'hosting' => [
            'catalog' => [
                'provider' => 'Sağlayıcı',
                'disk_space' => 'Disk alanı',
                'monthly_traffic' => 'Aylık trafik',
                'email_accounts' => 'E-posta hesabı sayısı',
                'databases' => 'Veritabanı sayısı',
                'sites' => 'Alan adı/site sayısı',
            ],
            'customer' => [
                'domain' => 'Bağlı alan adı',
                'server' => 'Sunucu',
                'cpanel_username' => 'cPanel kullanıcı adı',
            ],
        ],
        'domain' => [
            'catalog' => [
                'registrar' => 'Alan adı sağlayıcı',
                'extension' => 'Uzantı',
            ],
            'customer' => [
                'domain' => 'Alan adı',
            ],
        ],
        'maintenance' => [
            'catalog' => [
                'scope' => 'Kapsam açıklaması',
            ],
            'customer' => [
                'domain' => 'Bağlı alan adı / site',
            ],
        ],
        'ssl' => [
            'catalog' => [
                'provider' => 'Sağlayıcı',
                'certificate_type' => 'Sertifika tipi',
            ],
            'customer' => [
                'domain' => 'Bağlı alan adı',
            ],
        ],
        'email' => [
            'catalog' => [
                'provider' => 'Sağlayıcı',
                'quota' => 'Kota',
            ],
            'customer' => [
                'domain' => 'Bağlı alan adı',
                'account_count' => 'Hesap sayısı',
            ],
        ],
        'license' => [
            'catalog' => [
                'product_name' => 'Ürün adı',
                'provider' => 'Sağlayıcı',
            ],
            'customer' => [
                'quantity' => 'Adet',
            ],
        ],
        'other' => [
            'catalog' => [
                'description' => 'Açıklama',
            ],
            'customer' => [
                'description' => 'Açıklama',
            ],
        ],
    ],
];
