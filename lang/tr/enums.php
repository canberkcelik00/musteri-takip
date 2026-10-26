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

    'quote_status' => [
        'draft' => 'Taslak',
        'sent' => 'Gönderildi',
        'accepted' => 'Kabul edildi',
        'rejected' => 'Reddedildi',
    ],

    'quote_display_status' => [
        'draft' => 'Taslak',
        'sent' => 'Gönderildi',
        'expired' => 'Süresi doldu',
        'accepted' => 'Kabul edildi',
        'rejected' => 'Reddedildi',
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

    'role' => [
        'admin' => 'Yönetici',
        'developer' => 'Yazılımcı',
    ],

    'permission' => [
        'money.view' => 'Para: görüntüleme',
        'money.manage' => 'Para: yönetme',
        'customers.manage' => 'Müşteri işlemleri',
        'services.view' => 'Hizmetleri görme',
        'services.manage' => 'Hizmet işlemleri',
        'quotes.manage' => 'Teklifler',
        'catalog.manage' => 'Katalog',
        'settings.manage' => 'Ayarlar',
        'users.manage' => 'Kullanıcılar ve roller',
    ],

    'permission_hint' => [
        'money.view' => 'Borç, ödeme, bakiye ve fiyatları görür.',
        'money.manage' => 'Ödeme kaydeder, borcu düzenler, böler ve iptal eder.',
        'customers.manage' => 'Müşteri ve ilgili kişi ekler, düzenler, arşivler.',
        'services.view' => 'Müşterilerin hizmetlerini görür.',
        'services.manage' => 'Müşteriye hizmet atar, düzenler, iptal eder.',
        'quotes.manage' => 'Teklif oluşturur, gönderir, kabul eder.',
        'catalog.manage' => 'Hizmet ve paket kataloğunu düzenler.',
        'settings.manage' => 'Genel ayarlar, ödeme yöntemleri ve sunucular.',
        'users.manage' => 'Kullanıcı davet eder, düzenler; rolleri ve yetkileri ayarlar.',
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
