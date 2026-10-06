---
paths:
  - "app/Http/Controllers/OtherLinksController.php"
  - "app/View/Composers/NavigationComposer.php"
  - "resources/views/other/**"
  - "resources/links/**"
  - "storage/app/site/**"
---

# Other Links (Instance-Owned Pages)

Lets whoever runs an instance add their own pages (privacy policy, terms, docs) as Markdown files. No database. The code never says "pages" or "privacy policy", only "other links", so search for that.

- **Not a nav dropdown**: links sit at the bottom of the **More** menu (desktop) and the mobile menu, under a divider, only when at least one file exists.
- **Sources** (disks in `config/filesystems.php`): `site` is `storage/app/site/` (instance-owned; gitignored except its own `.gitignore`) and `bundled-links` is `resources/links/` (ships with the app, grouped as "Documentation"; currently absent).
- **Code**: `OtherLinksController` (`/other-links`, `/other-links/{source}/{path}`); views in `resources/views/other/links/`; the nav list is built by `NavigationComposer` (`$otherLinksFiles`). The nav shows only top-level files per source, while `/other-links` also lists subdirectories, grouped. The title is the filename with `-`/`_` turned into spaces. Symlinks are allowed, and `show()` verifies the resolved path stays inside the root.
- **Docs**: "Adding pages to Other Links" in `docs/content/docs/developers/_index.md`.
