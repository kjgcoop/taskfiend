# Changes Since the Last Release

## Light Mode

The profile menu now has a **Switch to Light Mode** / **Switch to Dark Mode** option between "Profile" and "Log Out". Light mode gives pages a white background and black text; tag, status and other meaningful colors are unchanged. The choice is remembered in your browser only (not your account), and dark mode remains the default.

## Search Templates

The Search page's "Search in" options now include a **Templates** checkbox. It's unchecked by default; check it to include tasks that live in project templates. The search bar in the top navigation is unchanged and never searches templates.

## Project Backgrounds Fill Tall Screens

A project's background image now extends to the bottom of the window, even when the task list is short (for example, on a very large monitor).

## Multiple Attachments on Comments

A comment can now carry several files instead of just one: pick as many as you like with **Choose files**, on both the task page and the side panel. Existing comment attachments are carried over automatically when you run `php artisan migrate`.

## Typed #Project Tokens Win Over the Current Page's Project

Typing `#project` in the quick-add bar while on a project page (or any page that pre-fills a project) now files the task in the project you typed, instead of silently keeping the page's project.

A `#word` that isn't a project (a hashtag, say) now always stays in the task's name. Previously it was deleted whenever the quick-add bar was on a project page.

## Load More for Inactive Projects

The Inactive Projects section on the Projects page now shows the first 100 done and archived projects, with a **Load more** button that fetches another 100 each time it's clicked.

## Side Panel Keeps Unmatched @Tokens in Task Names

Editing a task's name in the side panel no longer deletes text like `@notatag` or `#nothing` that doesn't match a real tag or project. Only tokens picked from the autocomplete are removed, matching quick-add.
