# PDF fonts

Generated font definitions for `tecnickcom/tc-lib-pdf`, used by
`App\Support\PlanPdfRenderer` for the shift plan PDF export.

These files have to be committed: tc-lib-pdf ships no fonts at all and reads
only the `.json` / `.z` / `.ctg.z` triples its converter produces. Without them
it cannot render text at all - it throws `Invalid font index` rather than
falling back to anything. The renderer finds them through the `K_PATH_FONTS`
constant defined in `AppServiceProvider`.

Replacing them with a non-embedded font is not an option: the export is
PDF/UA, which requires embedded fonts with a Unicode mapping, so the PDF
standard 14 "core" fonts cannot be used.

Liberation Sans covers the Latin ranges the `de`, `en` and `es` locales need
and is metrically compatible with Arial. The four faces are regular, bold,
italic and bold-italic; the italic faces are needed because plan and shift
descriptions may contain `<em>`.

## Regenerating

Requires the Liberation TTFs, e.g. from the `liberation-sans-fonts` package
(`/usr/share/fonts/liberation-sans-fonts/` on Fedora,
`/usr/share/fonts/truetype/liberation/` on Debian) or from
<https://github.com/liberationfonts/liberation-fonts/releases>:

```sh
php vendor/tecnickcom/tc-lib-pdf-font/util/convert.php \
    -o resources/fonts \
    -i /usr/share/fonts/liberation-sans-fonts/LiberationSans-Regular.ttf,\
/usr/share/fonts/liberation-sans-fonts/LiberationSans-Bold.ttf,\
/usr/share/fonts/liberation-sans-fonts/LiberationSans-Italic.ttf,\
/usr/share/fonts/liberation-sans-fonts/LiberationSans-BoldItalic.ttf
```

The font keys the renderer asks for (`liberationsans` plus the `B`/`I`/`BI`
styles, see `PlanPdfRenderer::FONT`) are derived from the file names by the
converter, so keep the file names as they are.

Liberation Sans is released under the SIL Open Font License 1.1; see
<https://github.com/liberationfonts/liberation-fonts>.
