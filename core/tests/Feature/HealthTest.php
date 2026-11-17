<?php

it('answers the framework health check', function () {
    $this->get('/up')->assertOk();
});

it('answers the API health check in JSON', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJson(['ok' => true]);
});
