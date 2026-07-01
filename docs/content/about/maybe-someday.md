---
title: "Features that Sound Nice But that May Never Be Implemented"
---

I'd be interested to know if anybody has any interest in these. Currently, they're implemented in the order in which they tickle my fancy. If you feel like voicing an opinion, let me know at taskfiend@kjcoop.com.

This list is by no means exhaustive. It's sorted by absolutely nothing.

See also [Known Issues](/docs/about/known-issues)

## Useful to All Existing Users (that is, me and my household but mostly me)

### Projects
- Making projects shareable read-only as a link. If this ever gets implemented, this page will go away.
- Projects can have background images; add the option to have a background color instead of image.
- Each task that is linked to in another task has a list of which tasks link to it. It might be nice to have a similar lists within projects - list the tasks that reference it. I don't know where in the UI this would go.
- Recurring projects? Say I put together a newsletter every month. A recurring project would automatially be created from a given template at whatever interval is specified.
- Springing projects? I have projects for each release of a given software I'm working on. Every time I complete a release, I create a new project. It might be fun if this happened automatically.
- Right now, if you want to alter a template, you need to create an instance of the project, edit it, save it as a new template, then chuck the starting template. This is awkward. It would be nice if you could just alter the template inline.

### Tasks
- The quick filter (to filter down a list of tasks to only those matching a search term) supports "not:" to find everything not matching that term. It recognizes project (#project) and tag (@tag) shorthand. Maybe one day I'll add these to the full search page input and/or the nav bar search.
- Import ics files (calendar events)

### Some Combination of Projects/Tags/Tasks
- Tags applied to a whole project? Still working out how I'd like it to behave.

## Solutions in Search of Problems
- Profile preference to dictate which properties appear on a task in a list. Right now it shows almost everything, but there may be users who don't care to see the recurrence or location or whatever, so maybe one day they'll have the ability to not show those. This is kind of a feature for feature's sake - I don't plan to use it, and I don't think this software has any other users. Is that stopping me from writing documentation for imaginary friends? It is not.
- When you type & in quick add, it'll provide a list of users you might want to assign the task to. It might be kind of cool to show their profile images. Again, this is solving a non-problem - I have two users on my system. I can remember which one is me and which one is not. But it sounds cool.
- Tag users in comments - as of v9, there's now an alert for lists of changes that other users made to shared tasks, so it's not really necessary.
- There's syntax to link to a project or tag (for project five and tag five, these would be [project:5] and [tag:5], respectively). Now that URLs are replaced by the task/tag/project name, I doubt I'll ever use this syntax. That said, if I do, it might be nice to have a dropdown from which to select your project/tag/whatever.
- More sophisticated search? I wouldn't use any of these frequently enough to justify the dev time, but they sound cool.
    - Saved searches? 
    - Specify whether the search is and/or. Right now all search terms refine the list - you can't search for Tag A or Tag B. 
    - Search in multiple projects

