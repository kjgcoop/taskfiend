---
date: '2026-10-22T16:26:26-07:00'
draft: false
title: 'Release 16'
url: '2026/10/22/release-16'
summary: Release 16 is mostly internal template changes
---

[Release 16](https://github.com/kjgcoop/taskfiend/releases/tag/v16.0.0) has more changes than I cared to cram into the Github release notes. The short story: templates were previously zip files on the hard drive. They're now stored in the database. This spares you a couple steps in editing them. It's not polished, but it's an improvement.

Upgrading requires both `npm run build` and `php artisan migrate`.

