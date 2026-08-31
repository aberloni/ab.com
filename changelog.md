# Changelog

## 2026-08-31

- Phanes: media catalog moved to a right-side overlay panel (book button next to "+"), thumbnails sorted by last edit.
- Phanes: click a media to inject {{file}} / {[file]} at the cursor; per-media delete button.
- Phanes: article list buffered to .pages-cache.json (auto-rebuild after 1h, "Rafraîchir" forces it), cleared on save/delete.
- Phanes: Publiés / À corriger tabs; issue tab lists invalid-header .md, newest first, labelled by first line.
- Phanes: active tab deduced from the open article and kept when selecting a page.
- Phanes: after fixing an issue article, auto-select the next issue.
- Phanes: title search box; sort toggle (default "last edited", or date); live check/circle showing if the html exists.
- Phanes: one-line article rows, small faded category, date under the title.
- Phanes: Tab inserts a real tab in the editor; preview renders indentation; Source/Aperçu aligned; navbar Builder link.
- "about" is a normal page (editing/pages/about.md), reachable at /about, built like an article.
- New hiddenCategory ("page") in config.json: those pages are built and editable in Phanes but excluded from the homepage list, RSS and stats. Replaces the unused "fixed" key.
- Homepage (localhost only): added phanes / online / OVH quick links next to build.
- builder.php: dropped the header-format hint line.
- Bumped version to 2.14.
