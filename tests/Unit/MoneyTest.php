<?php

use App\Support\Money;

it('formata centavos em reais', function (int $cents, string $expected) {
    expect(Money::format($cents))->toBe($expected);
})->with([
    [0, 'R$ 0,00'],
    [5, 'R$ 0,05'],
    [123456, 'R$ 1.234,56'],
    [-9990, '-R$ 99,90'],
    [100000000, 'R$ 1.000.000,00'],
]);

it('formata com sinal', function () {
    expect(Money::format(1500, signed: true))->toBe('+R$ 15,00')
        ->and(Money::format(-1500, signed: true))->toBe('-R$ 15,00');
});

it('converte texto em centavos', function (mixed $input, int $expected) {
    expect(Money::parse($input))->toBe($expected);
})->with([
    ['1.234,56', 123456],
    ['R$ 10,5', 1050],
    ['1234.56', 123456],
    ['0,01', 1],
    [12, 1200],
    [19.99, 1999],
    ['', 0],
    [null, 0],
]);
