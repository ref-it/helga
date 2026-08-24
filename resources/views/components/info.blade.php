<flux:modal.trigger name="info">
    <flux:navmenu.item icon="info" class="-mr-4!">{{ __('common.about') }} {{ config('app.name') }} &hellip;</flux:navmenu.item>
</flux:modal.trigger>

<flux:modal name="info" class="max-w-lg">
    <flux:heading size="lg" class="modal-header">{{ config('app.name') }}</flux:heading>
    <div class="text-center">
        <h3 class="text-xl font-bold text-zinc-800 dark:text-white">{{ config('app.name') }} <span class="font-normal ml-1"> 1.1.0</span></h3>
        <div class="mt-2">
            <p class="text-zinc-800 dark:text-white hyphens-none"><b>Hel</b>fer<b>g</b>ewinnungs<b>a</b>pplikation</p>
        </div>
        <div class="mt-6">
            <p class="text-zinc-800 dark:text-white mb-2">&copy; 2011 &ndash; 2026 <flux:link href="https://www.immerda.ch/" target="_blank" rel="noopener noreferrer">www.immerda.ch</flux:link></p>
            <p class="text-zinc-800 dark:text-white mb-2">&copy; 2026 Marc Schlagenhauf</p>
            <p class="text-zinc-800 dark:text-white">{{ __('common.licensedUnder') }} <flux:link href="https://www.gnu.org/licenses/agpl-3.0.txt" target="_blank" rel="noopener noreferrer">AGPLv3</flux:link>.</p>
        </div>
        <div class="space-y-2 bg-zinc-100 dark:bg-zinc-800 border-t border-zinc-200 dark:border-zinc-700 -mx-6 -mb-6 px-6 py-4 mt-8">
            <div class="flex flex-wrap gap-2 justify-center">
                <flux:button size="sm" icon="external-link" href="{{ route('documentation') }}" target="_blank">{{ __('common.documentation') }}</flux:button>
                <flux:button size="sm" icon="external-link" href="{{ route('source-code') }}" target="_blank">{{ __('common.sourceCode') }}</flux:button>
                <flux:button size="sm" icon="external-link" href="{{ route('translate') }}" target="_blank">{{ __('common.translate') }}</flux:button>
            </div>
        </div>
    </div>
</flux:modal>
