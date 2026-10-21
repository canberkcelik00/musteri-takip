<?php

return [
    'resource' => [
        'disk_usage' => 'Disk',
        'bandwidth' => 'Aylık trafik',
        'email_accounts' => 'E-posta hesabı',
        'mysql_databases' => 'Veritabanı',
        'addon_domains' => 'Ek alan adı',
        'subdomains' => 'Alt alan adı',
        'parked_domains' => 'Park alan adı',
        'ftp_accounts' => 'FTP hesabı',
        'email_forwarders' => 'E-posta yönlendirici',
        'autoresponders' => 'Otomatik yanıtlayıcı',
        'email_lists' => 'E-posta listesi',
    ],
    'column' => [
        'item' => 'Kalem',
        'usage' => 'Kullanım',
        'status' => 'Durum',
    ],
    'mail' => [
        'subject' => 'Hosting kotası uyarısı (:count) – :date',
        'title' => 'Hosting kotası uyarısı',
        'summary' => ':count kalem sınırına yaklaştı veya doldu',
        'intro' => 'Aşağıdaki kalemler sınırlarının %:percent veya fazlasına ulaştı. Aynı kalem için kullanım azalıp yeniden artmadıkça tekrar e-posta gelmez; kullanım "Dolu" olursa bir kez daha haber verilir.',
    ],
    'warnings_sent' => ':count kota uyarısı gönderildi.',
];
