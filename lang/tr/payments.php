<?php

return [
    'amount_not_positive' => 'Tutar sıfırdan büyük olmalıdır.',
    'charge_of_another_customer' => 'Ödeme yalnızca aynı müşterinin borçlarına dağıtılabilir.',
    'currency_mismatch' => 'Ödeme yalnızca aynı para birimindeki borçlara dağıtılabilir.',
    'charge_not_open' => 'Ödeme yalnızca açık borçlara dağıtılabilir.',
    'exceeds_charge_remaining' => 'Bir borca kalan tutarından fazla dağıtılamaz.',
    'exceeds_payment_amount' => 'Dağıtımların toplamı ödeme tutarını aşamaz.',

    'singular' => 'Ödeme',
    'plural' => 'Ödemeler',
    'customer' => 'Müşteri',
    'paid_on' => 'Tarih',
    'amount' => 'Tutar',
    'payment_method' => 'Yöntem',
    'note' => 'Açıklama',

    'record' => 'Ödeme kaydet',
    'record_heading' => 'Ödeme kaydı',
    'show_allocations' => 'Dağıtımı göster ve değiştir',
    'allocations_helper' => 'Tutarlar önce belirttiğiniz borçlara düşer; kalan kısım en eski vadeli borçlardan devam eder.',
    'no_open_charges' => 'Bu para biriminde açık borç yok; ödemenin tamamı alacak bakiyesine eklenir.',
    'allocation_label' => ':description · vade :due_date · kalan :remaining',
    'allocation_fallback_description' => 'Borç',

    'result_closed' => ':amount borç kapatıldı',
    'result_credit' => ':amount alacak bakiyesine eklendi',
    'result_both' => ':closed, :credit.',
    'result_single' => ':part.',

    'delete' => 'Sil',
    'delete_heading' => 'Ödemeyi sil',
    'delete_description' => 'Ödemenin dağıtımları geri alınır; kapattığı borçlar tekrar açık hale gelir.',
    'deleted' => 'Ödeme silindi.',
];
