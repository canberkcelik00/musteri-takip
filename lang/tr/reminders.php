<?php

return [
    'subject' => 'Yenileme hatırlatması – :service',
    'title' => 'Yenileme hatırlatması',
    'greeting' => 'Sayın :name,',
    'intro' => ':service hizmetiniz :date tarihinde sona eriyor. Yenileme bilgileri aşağıdadır.',
    'intro_past' => ':service hizmetiniz :date tarihinde sona erdi. Yenileme bilgileri aşağıdadır.',
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
    'not_eligible' => 'Bu borç için hatırlatma gönderilemez.',
    'no_recipient' => 'Birincil kişinin geçerli e-postası yok.',
    'sent_on' => 'Hatırlatma gönderildi: :date',
    'send' => 'Hatırlatma gönder',
    'send_heading' => 'Yenileme hatırlatması gönder',
    'send_confirm' => ':email adresine yenileme hatırlatması gönderilsin mi?',
    'last_sent' => 'Son gönderim: :date',
    'sent_notification' => 'Hatırlatma gönderildi',
    'send_failed' => 'Hatırlatma gönderilemedi',
    'command_failed' => ':count hatırlatma gönderilemedi; sonraki çalışmada yeniden denenecek.',
];
