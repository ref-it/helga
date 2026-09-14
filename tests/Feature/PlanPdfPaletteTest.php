<?php

use App\Support\PlanPdfRenderer;

/**
 * The PDF cannot carry oklch, so it holds sRGB values of the Tailwind entries
 * the interface uses. That makes them a copy, and a copy can drift: this
 * recomputes them from the theme Tailwind ships and compares.
 *
 * Skipped where the package is not installed - the export does not depend on
 * node_modules, only this check does.
 */
function tailwindTheme(): ?string
{
    $path = base_path('node_modules/tailwindcss/theme.css');

    return is_file($path) ? (string) file_get_contents($path) : null;
}

/**
 * The sRGB hex of one Tailwind palette entry, read from its oklch definition.
 */
function tailwindColor(string $theme, string $name): ?string
{
    if (preg_match('#--color-'.preg_quote($name, '#').': oklch\(([\d.]+)% ([\d.]+) ([\d.]+)\)#', $theme, $m) !== 1) {
        return null;
    }

    [$lightness, $chroma, $hue] = [(float) $m[1] / 100, (float) $m[2], (float) $m[3]];

    $a = $chroma * cos(deg2rad($hue));
    $b = $chroma * sin(deg2rad($hue));

    // oklab to linear sRGB (Björn Ottosson's matrices)
    $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
    $m2 = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
    $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

    $linear = [
        4.0767416621 * $l - 3.3077115913 * $m2 + 0.2309699292 * $s,
        -1.2684380046 * $l + 2.6097574011 * $m2 - 0.3413193965 * $s,
        -0.0041960863 * $l - 0.7034186147 * $m2 + 1.7076147010 * $s,
    ];

    $hex = '#';
    foreach ($linear as $channel) {
        $channel = max(0.0, min(1.0, $channel));
        $encoded = $channel <= 0.0031308
            ? 12.92 * $channel
            : 1.055 * $channel ** (1 / 2.4) - 0.055;
        $hex .= str_pad(dechex((int) round($encoded * 255)), 2, '0', STR_PAD_LEFT);
    }

    return $hex;
}

test('the pdf palette matches the tailwind entries the interface uses', function (): void {
    $theme = tailwindTheme();
    if ($theme === null) {
        $this->markTestSkipped('tailwindcss is not installed');
    }

    $reflection = new ReflectionClass(PlanPdfRenderer::class);
    $constants = $reflection->getConstants();

    foreach (['SKY_700' => 'sky-700', 'YELLOW_800' => 'yellow-800', 'ZINC_500' => 'zinc-500', 'ZINC_600' => 'zinc-600', 'ZINC_700' => 'zinc-700'] as $constant => $entry) {
        $expected = tailwindColor($theme, $entry);

        expect($expected)->not->toBeNull("tailwind has no {$entry}");
        expect($constants['COLOR_'.$constant])->toBe($expected, "COLOR_{$constant} has drifted from {$entry}");
    }
});

test('the link colour is the accent the interface is themed with', function (): void {
    $theme = tailwindTheme();
    if ($theme === null) {
        $this->markTestSkipped('tailwindcss is not installed');
    }

    // resources/css/theme.css sets --color-accent to a palette entry; the
    // export picks up the same one, so a plan on screen and on paper agree
    expect(preg_match(
        '#--color-accent:\s*var\(--color-([a-z]+-\d+)\)#',
        (string) file_get_contents(resource_path('css/theme.css')),
        $accent,
    ))->toBe(1);

    $reflection = new ReflectionClass(PlanPdfRenderer::class);

    expect($reflection->getConstants()['LINK_COLOR'])->toBe(tailwindColor($theme, $accent[1]));
});
