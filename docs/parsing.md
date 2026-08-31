# Article parsing

How an `editing/pages/*.md` file becomes an `articles/*.html` fragment.

Entry point: `displayItem()` in `publishing/builder-api.php`.

## Source file format

```
CATEGORY
Article title
keyword, keyword, keyword
---
markdown body...
```

- **Lines before `---`** — the header (blank lines among them are ignored):
  - line 1 `CATEGORY` — **mandatory**. Lowercased during generation (so
    `DNS` / `dns` / `Dns` all match); shown uppercase via CSS. Pink badge on
    the homepage list (and `data-subcat` for the badge-click filter).
  - line 2 `Title` — **mandatory**.
  - line 3 `keywords` — optional, comma-separated, rendered as chips under
    the title.
  - further lines are reserved for future use and currently ignored.
- **`---`** on its own line marks the start of the body. The **first** `---`
  wins; `---` later in the body is left alone.
- **Everything after `---`** is the markdown body.
- File name is the article date: `YYYY-MM-DD.md`, with an optional `_N`
  suffix for multiple posts on the same day (`2012-10-22_2.md`).
- Files whose name contains no `-` are treated as non-article pages
  (looked up in `editing/`, not `editing/pages/`).

### Header validation

`parseArticleFile($path)` returns `["category","title","keywords","body"]`
or an error string. It fails when:

- there is no `---` line, or
- there are fewer than 2 non-empty lines before it (CATEGORY + Title).

`validateHeader($id)` wraps it (`true` or the error). Articles that fail are
**skipped entirely**: no `articles/<date>.html` is generated (an existing one
is deleted), and they're left out of the homepage list and the RSS feed.
`builder.php` prints them under **"Headers à corriger"** with the reason.

## Pipeline

1. `parseArticleFile()` — split header (3 lines before `---`) from body.
   Invalid headers never get past this.
3. **Markdown**: `Parsedown` with `setBreaksEnabled(true)` — a single newline
   becomes `<br>` (hard breaks), matching how the posts are written.
   No pre-processing: the raw body goes straight to Parsedown.
4. **Shortcodes**: `solveSpecificPatternsOnLine()` runs on the *rendered HTML*
   string (see below).
5. Wrapped as:
   ```html
   <div id="content-title">TITLE</div>
   <div id="content-keywords"><span class="keyword">…</span>…</div>
   <div id="content">...rendered...</div>
   ```
   (`#content-keywords` is omitted when the keywords line has no entries.)

This same fragment is reused verbatim inside each RSS `<item><description>`
(`recreateXmlFile()` in `publishing/xml-api.php`).

## Shortcodes (post-Parsedown)

Applied by `solveSpecificPatternsOnLine($html, $date)`. They operate on the
HTML text after markdown rendering, so they never interfere with markdown
parsing.

| Syntax | Becomes |
|---|---|
| `:ddd:` | the article's date string (`YYYY-MM-DD`) |
| `{{name.ext}}` | `<img src="articles/medias/name.ext" alt="name.ext" onerror="mediaFail(this)">` |
| `{[name.mp4]}` | `<video controls onerror="mediaFail(this)" data-media="name.mp4"><source src="articles/medias/name.mp4" type="video/mp4">…</video>` |

Media paths:

- `$mediaUrl = "articles/medias/"` — web-relative, used in the `src` above.
- `$mediaPath = ROOT."articles/medias/"` — filesystem, used only for
  thumbnail generation.

### Failed media

If an `<img>` / `<video>` fails to load, `mediaFail(el)` in `js/js-index.js`
replaces it with `<div class="media-failed">` showing the file name
(red box). Note the `.htaccess` SPA fallback returns `index.html` (HTTP 200)
for a missing asset, so the failure surfaces as a decode error, which still
triggers `onerror`.

## Removed legacy syntax

Previously `convertLegacySyntax()` rewrote the body before Parsedown. It was
removed because it corrupted code blocks. If an old article still uses these,
convert the source `.md` to real markdown:

| Old | Use instead |
|---|---|
| `[code]` … `[/code]` | ```` ``` ```` fenced code blocks |
| `\|subtitle` (line starting with `\|`) | `### subtitle` |

Also note: lines starting with `#` are markdown headings (`<h1>`…). Some old
posts used `#` as a list marker — those now render as headings and should be
changed to `-` or `1.`.
