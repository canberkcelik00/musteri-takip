<?php

return [
    'recipient_required' => 'Müşteri seçin ya da alıcının adını ve e-postasını yazın.',
    'recipient_email_invalid' => 'Alıcının e-posta adresi geçerli değil.',
    'not_draft' => 'Yalnız taslak teklif düzenlenebilir ve silinebilir.',
    'price_not_positive' => 'Fiyat sıfırdan büyük olmalıdır; ücret alınmayacaksa ücretsiz işaretleyin.',
    'item_required' => 'Teklifte en az bir kalem olmalıdır.',
    'invalid_item' => 'Teklif kalemi, katalogdaki bir hizmet ya da paketle ilişkili olmalıdır.',
    'customer_archived' => 'Arşivlenmiş müşteriye teklif hazırlanamaz; önce müşteriyi arşivden çıkarın.',
    'not_sendable' => 'Kabul edilmiş veya reddedilmiş teklif gönderilemez.',
    'no_recipient' => 'Teklifin gönderileceği e-posta adresi yok; adresi yazın.',

    'mail' => [
        'subject' => 'Teklif :number',
        'title' => 'Teklif',
        'greeting' => 'Sayın :name,',
        'greeting_plain' => 'Merhaba,',
        'intro' => 'Ekte :number numaralı teklifimizi bulabilirsiniz. Teklif :date tarihine kadar geçerlidir.',
        'column' => [
            'number' => 'Teklif no',
            'valid_until' => 'Geçerlilik',
            'total' => 'Toplam',
        ],
        'reply_note' => 'Sorularınız için bu e-postayı yanıtlayabilirsiniz.',
        'closing' => 'Saygılarımızla,',
    ],

    'pdf' => [
        'title' => 'Teklif',
        'number' => 'Teklif no',
        'date' => 'Tarih',
        'valid_until' => 'Geçerlilik',
        'recipient' => 'Sayın',
        'tax' => 'Vergi dairesi / no',
        'column_name' => 'Hizmet',
        'column_price' => 'Fiyat',
        'free' => 'Ücretsiz',
        'total' => 'Toplam',
        'notes' => 'Notlar',
    ],
];
