<?php

it('exibe a página inicial', function () {
    $this->get('/')->assertOk()->assertSee('Hello, Financee');
});

it('responde ao health check', function () {
    $this->get('/up')->assertOk();
});
