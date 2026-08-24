@if($html)
    <div class="space-y-2 [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:pl-5 [&_ol]:pl-5 [&_blockquote]:border-l-2 [&_blockquote]:border-zinc-300 dark:[&_blockquote]:border-zinc-600 [&_blockquote]:pl-3 [&_blockquote]:italic [&_a]:text-(--color-accent-content) [&_a]:underline">
        {!! $html !!}
    </div>
@endif
