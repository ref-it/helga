<?php

use Illuminate\Support\Facades\File;

/**
 * Points public_path() at a scratch directory, so the test neither depends on
 * nor touches the icons an instance keeps in the real public/ folder.
 */
function withPublicIcons(array $files): string
{
    $dir = sys_get_temp_dir().'/helga-public-'.uniqid();
    File::makeDirectory($dir);

    foreach ($files as $file) {
        File::put($dir.'/'.$file, 'x');
    }

    app()->usePublicPath($dir);

    return $dir;
}

test('the layout links the favicon and the apple touch icon when they exist in public', function (): void {
    $dir = withPublicIcons(['favicon.png', 'favicon.svg', 'apple-touch-icon.png']);
    $this->withoutVite();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<link rel="icon" type="image/png" href="'.asset('favicon.png').'">', false)
        ->assertSee('<link rel="icon" type="image/svg+xml" href="'.asset('favicon.svg').'">', false)
        ->assertSee('<link rel="apple-touch-icon" href="'.asset('apple-touch-icon.png').'">', false);

    File::deleteDirectory($dir);
});

test('the layout links no icons when public has none', function (): void {
    $dir = withPublicIcons([]);
    $this->withoutVite();

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('rel="icon"', false)
        ->assertDontSee('rel="apple-touch-icon"', false);

    File::deleteDirectory($dir);
});
