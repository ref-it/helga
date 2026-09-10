# PDF fonts

Generated font definitions for `tecnickcom/tc-lib-pdf`, used by
`App\Support\PlanPdfRenderer` for the shift plan PDF export.

These files have to be committed: tc-lib-pdf ships no fonts at all and reads
only the `.json` / `.z` / `.ctg.z` triples its converter produces. Without them
it cannot render text at all - it throws `Invalid font index` rather than
falling back to anything. The renderer finds them through the `K_PATH_FONTS`
constant defined in `AppServiceProvider`.

Replacing them with a non-embedded font is not an option: the export is
PDF/UA-2, which requires embedded fonts with a Unicode mapping, so the PDF
standard 14 "core" fonts cannot be used.

## The two families

The document is set in two families, see `PlanPdfRenderer::FONT_SANS` and
`FONT_SERIF`:

* **Adwaita Sans** for everything but the headings. Its x-height (0.546 em)
  is a little larger than Liberation Sans', which it replaced, so the 9pt
  contact lines gained rather than lost - and being Inter-derived it keeps
  `l`/`1`/`I` and `0`/`O` apart, which is what the e-mail addresses need.
* **Merriweather** for the plan, category and shift headings. Unusually large
  x-height for a serif (0.555 em), so it does not shrink next to the sans.

Both cover the Latin ranges the `de`, `en` and `es` locales need, and both are
released under the SIL Open Font License 1.1. Each family needs all four faces
- regular, bold, italic and bold-italic: bold for the table head and `<strong>`,
and the italics because descriptions may contain `<em>`.

## Regenerating

Requires the two font packages (`adwaita-sans-fonts` and
`sorkintype-merriweather-fonts` on Fedora, or the upstream releases).

**Adwaita Sans ships only as a variable font** - two files, regular and italic,
carrying `wght` (100-900) and `opsz` (14-32) axes. The converter reads static
TTFs and would embed only the default instance, leaving the document with no
bold at all, so the four faces have to be instanced first:

```sh
python3 - <<'PY'
from fontTools import ttLib
from fontTools.varLib import instancer

for style, path in (
    ("Regular", "/usr/share/fonts/adwaita-sans-fonts/AdwaitaSans-Regular.ttf"),
    ("Italic", "/usr/share/fonts/adwaita-sans-fonts/AdwaitaSans-Italic.ttf"),
):
    for wght, bold in ((400, ""), (700, "Bold")):
        font = instancer.instantiateVariableFont(
            ttLib.TTFont(path), {"wght": wght, "opsz": 14}
        )
        label = bold + ("Italic" if style == "Italic" else "")
        font.save(f"/tmp/fonts/AdwaitaSans-{label or 'Regular'}.ttf")
PY
```

`opsz` is pinned to 14, the optical size meant for text rather than display -
the headings get their weight from Merriweather, not from a display cut.

Merriweather needs no preparation: the package ships static
`Merriweather-{Regular,Bold,Italic,BoldItalic}.ttf` alongside its variable
files, and those four are what to convert.

```sh
php vendor/tecnickcom/tc-lib-pdf-font/util/convert.php \
    -o resources/fonts \
    -i /tmp/fonts/AdwaitaSans-Regular.ttf,\
/tmp/fonts/AdwaitaSans-Bold.ttf,\
/tmp/fonts/AdwaitaSans-Italic.ttf,\
/tmp/fonts/AdwaitaSans-BoldItalic.ttf,\
/usr/share/fonts/sorkintype-merriweather-fonts/Merriweather-Regular.ttf,\
/usr/share/fonts/sorkintype-merriweather-fonts/Merriweather-Bold.ttf,\
/usr/share/fonts/sorkintype-merriweather-fonts/Merriweather-Italic.ttf,\
/usr/share/fonts/sorkintype-merriweather-fonts/Merriweather-BoldItalic.ttf
```

The font keys the renderer asks for (`adwaitasans` and `merriweather` plus the
`B`/`I`/`BI` styles) are derived from the file names by the converter, which
lowercases them, strips everything outside `[a-z0-9_]` and then maps `bold` to
`b`, `italic` to `i` and `regular` to nothing. Keep the file names as they are -
the converter also reads the file name to decide whether a face is italic, so a
differently named file would not be marked as such in the font descriptor.

## After a font change

Two calibrated values in `PlanPdfRenderer` depend on the family:

* `HTML_LINE_HEIGHT` - the rich text fields go through the HTML cell renderer,
  which cannot take the absolute `LINE_SPACING`, so the relative value is
  measured to match it at the description's font size. It is 1.44 for
  Adwaita Sans at 10pt and was 1.34 for Liberation Sans.
* the `HEADING_SIZE_*` ladder - cap heights differ between families, so the
  same pt size does not look the same size.

`capMiddleOffset()`, which centres the rule beside a category heading, reads
the metrics of the selected font and needs nothing.
