<?php

it('sends a guest from the home page to the login page', function () {
    $this->get('/')->assertRedirect(route('app.login'));
});
