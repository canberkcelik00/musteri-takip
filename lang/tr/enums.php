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
        'money_view' => 'Para: görüntüleme',
        'money_manage' => 'Para: yönetme',
        'customers_manage' => 'Müşteri işlemleri',
        'services_view' => 'Hizmetleri görme',
        'services_manage' => 'Hizmet işlemleri',
        'tasks_manage' => 'İşler',
        'calendar_manage' => 'Takvim',
        'quotes_manage' => 'Teklifler',
        'catalog_manage' => 'Katalog',
        'settings_manage' => 'Ayarlar',
        'users_manage' => 'Kullanıcılar ve roller',
    ],

    'permission_hint' => [
        'money_view' => 'Borç, ödeme, bakiye ve fiyatları görür.',
        'money_manage' => 'Ödeme kaydeder, borcu düzenler, böler ve iptal eder.',
        'customers_manage' => 'Müşteri ve ilgili kişi ekler, düzenler, arşivler.',
        'services_view' => 'Müşterilerin hizmetlerini görür.',
        'services_manage' => 'Müşteriye hizmet atar, düzenler, iptal eder.',
        'tasks_manage' => 'İş oluşturur, atar, bitirir; kendine atanan işleri Bugün sayfasında görür.',
        'calendar_manage' => 'Takvimde etkinlik ekler, düzenler, siler; katılanlara davet e-postası gider.',
        'quotes_manage' => 'Teklif oluşturur, gönderir, kabul eder.',
        'catalog_manage' => 'Hizmet ve paket kataloğunu düzenler.',
        'settings_manage' => 'Genel ayarlar, ödeme yöntemleri ve sunucular.',
        'users_manage' => 'Kullanıcı davet eder, düzenler; rolleri ve yetkileri ayarlar.',
    ],

    'event_repeat' => [
        'none' => 'Tekrarlamaz',
        'weekly' => 'Her hafta',
        'monthly' => 'Her ay',
    ],

    'calendar_category' => [
        'event' => 'Etkinlikler',
        'task' => 'İşler',
        'service_end' => 'Hizmet bitişleri',
        'charge_due' => 'Borç vadeleri',
        'payment' => 'Ödemeler',
        'quote' => 'Teklifler',
        'alert_down' => 'Site kapandı',
        'alert_soon' => 'Süre yaklaşıyor',
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
