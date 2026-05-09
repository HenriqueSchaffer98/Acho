<?php

it('returns a successful response on root domain', function () {
    $response = $this->get('http://acho.test/');

    $response->assertStatus(200);
});
