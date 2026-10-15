<?php

return [
    'subject' => [
        'daily' => 'Günlük özet – :date',
        'monthly' => 'Aylık özet – :date',
    ],
    'title' => [
        'daily' => 'Günlük özet',
        'monthly' => 'Aylık özet',
    ],
    'new_charges' => 'Son özetten bu yana oluşan borçlar',
    'free_decisions' => 'Ücretsiz devam kararı bekleyenler',
    'overdue' => 'Vadesi geçmiş borçlar',
    'missing_reminder_email' => 'Hatırlatma gönderilemedi: birincil kişinin geçerli e-postası yok',
    'count' => [
        'missing_reminder_email' => ':count hatırlatma gönderilemedi',
        'new_charges' => ':count yeni borç',
        'free_decisions' => ':count karar bekliyor',
        'overdue' => ':count gecikmiş',
    ],
    'column' => [
        'customer' => 'Müşteri',
        'service' => 'Hizmet',
        'amount' => 'Tutar',
        'due' => 'Vade',
        'ends' => 'Bitiş',
        'remaining' => 'Kalan',
        'overdue' => 'Gecikme',
    ],
    'total' => 'Toplam',
    'remaining' => 'Kalan :amount',
    'closed_from_credit_badge' => 'Bakiyeden kapatıldı',
    'partly_from_credit' => ':amount alacak bakiyesinden düşüldü',
    'free_question' => 'Yeni döneme ücretsiz devam edecek mi?',
    'open_panel' => 'Panele git',
    'result' => [
        'sent' => 'Özet gönderildi.',
        'empty' => 'Özet boş; gönderilmedi.',
        'not_due' => 'Bu dönemin özeti zaten ele alındı.',
        'no_recipient' => 'Bildirim adresi girilmemiş; özet gönderilmedi.',
        'started' => 'Özet takibi başladı; ilk özet sıradaki planlı anda gönderilir.',
    ],
    'next_scheduled_at' => 'Sıradaki özet: :moment',
];
