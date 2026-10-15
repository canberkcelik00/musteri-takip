<?php

return [
    'subject' => 'Yenileme hatırlatması – :service',
    'title' => 'Yenileme hatırlatması',
    'greeting' => 'Sayın :name,',
    'intro' => ':service hizmetiniz :date tarihinde sona eriyor. Yenileme bilgileri aşağıdadır.',
    'column' => [
        'service' => 'Hizmet',
        'period' => 'Yeni dönem',
        'amount' => 'Tutar',
        'due' => 'Son ödeme',
    ],
    'reply_note' => 'Sorularınız için bu e-postayı yanıtlayabilirsiniz.',
    'closing' => 'Saygılarımızla,',
    'result' => [
        'disabled' => 'Müşteri hatırlatmaları kapalı; hiçbir şey gönderilmedi.',
        'summary' => ':sent hatırlatma gönderildi, :skipped atlandı (geçerli e-posta yok), :failed gönderilemedi.',
    ],
    'command_failed' => ':count hatırlatma gönderilemedi; sonraki çalışmada yeniden denenecek.',
];
