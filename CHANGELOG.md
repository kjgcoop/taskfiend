# Changes Since the Last Release

## Typed #Project Tokens Win Over the Current Page's Project

Typing `#project` in the quick-add bar while on a project page (or any page that pre-fills a project) now files the task in the project you typed, instead of silently keeping the page's project.

A `#word` that isn't a project (a hashtag, say) now always stays in the task's name. Previously it was deleted whenever the quick-add bar was on a project page.

## Load More for Inactive Projects

The Inactive Projects section on the Projects page now shows the first 100 done and archived projects, with a **Load more** button that fetches another 100 each time it's clicked.

## Side Panel Keeps Unmatched @Tokens in Task Names

Editing a task's name in the side panel no longer deletes text like `@notatag` or `#nothing` that doesn't match a real tag or project. Only tokens picked from the autocomplete are removed, matching quick-add.
