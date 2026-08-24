<?php

test('the locale is derived from the Accept-Language header', function (): void {
    $this->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])
        ->get(route('home'));

    expect(app()->getLocale())->toBe('de');
});

test('an unsupported browser language falls back to the configured fallback locale', function (): void {
    $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9'])
        ->get(route('home'));

    expect(app()->getLocale())->toBe(config('app.fallback_locale'));
});

test('no Accept-Language header falls back to the configured fallback locale', function (): void {
    $this->get(route('home'));

    expect(app()->getLocale())->toBe(config('app.fallback_locale'));
});

test('the language switcher route no longer exists', function (): void {
    $this->get('/language/de')->assertNotFound();
});
