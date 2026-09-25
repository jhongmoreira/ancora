<?php

use App\Models\Patient;

test('initials use first and last name, ignoring middle names', function () {
    expect((new Patient(['full_name' => 'Paciente Souza Dev']))->initials)->toBe('P. D.');
});

test('initials work with just two names', function () {
    expect((new Patient(['full_name' => 'Maria Silva']))->initials)->toBe('M. S.');
});

test('initials work with a single name', function () {
    expect((new Patient(['full_name' => 'Madonna']))->initials)->toBe('M.');
});

test('initials handle extra whitespace between names', function () {
    expect((new Patient(['full_name' => '  Ana   Paula   Costa  ']))->initials)->toBe('A. C.');
});
