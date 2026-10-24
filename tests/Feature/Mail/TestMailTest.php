<?php

use App\Mail\TestMail;

it('has a Turkish subject, text and a link to the panel', function () {
    $mail = new TestMail;

    $mail->assertHasSubject('Test e-postası')
        ->assertSeeInHtml('Bu e-posta, e-posta ayarlarını denemek için gönderildi.')
        ->assertSeeInHtml(route('app.today'), false);
});

it('has a plain text version without HTML', function () {
    $mail = new TestMail;

    $mail->assertSeeInText('Bu e-posta, e-posta ayarlarını denemek için gönderildi.')
        ->assertSeeInText('Panele git: '.route('app.today'));
    expect($mail->render())->toBeString();
});
