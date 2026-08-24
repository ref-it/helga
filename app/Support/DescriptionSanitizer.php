<?php

declare(strict_types=1);

namespace App\Support;

use Mews\Purifier\Casts\CleanHtmlInput;

/**
 * Sanitizes the HTML produced by the flux:editor rich text fields used for
 * plan and shift descriptions. The allowed tags are kept in sync with the
 * toolbar buttons enabled on those editors (see the description fields in
 * resources/views/livewire/plan and resources/views/livewire/shift).
 *
 * Cleans on write only - a Livewire component only holds the raw value the
 * browser sent, so sanitizing has to happen here, not in the editor's
 * toolbar/UI, to guard against a crafted request bypassing the editor
 * entirely.
 *
 * The actual purifier config is registered under the "description" preset
 * in AppServiceProvider - Eloquent's casts() only accepts plain cast
 * descriptor strings (ClassName or ClassName:args), not cast objects
 * constructed with an inline config array.
 */
class DescriptionSanitizer
{
    public const ALLOWED_HTML = 'p,br,strong,em,u,s,ul,ol,li,blockquote,a[href|rel]';

    public const CAST = CleanHtmlInput::class.':description';
}
