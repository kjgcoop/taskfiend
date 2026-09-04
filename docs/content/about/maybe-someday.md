---
title: "Features that Sound Nice But that May Never Be Implemented"
---

Too many features eventually devolve into navel-gazing - spending more time managing the list(s) of tasks than actually doing the tasks. Finding a balance seems to be the eternal struggle for those creating their own to-do list software. I know this is a universal experience because I have consulted one (1) person about it (myself). 

I'm curious to know if anybody has any interest in these. Currently, they're implemented in the order in which they tickle my fancy. If you feel like voicing an opinion, let me know at taskfiend@kjcoop.com.

This list is by no means exhaustive. It's sorted by absolutely nothing.

See also [Known Issues](/docs/about/known-issues)

## Useful to All Existing Users (that is, me and my household but mostly me)
### Tasks
- Import ics files (calendar events) as tasks

### Projects
- Making projects shareable read-only as a link. If this ever gets implemented, this page will go away. The ugly part about replacing this page with a publicly-visible Task Fiend project is that then I have to install a task fiend instance on my public web server and sync changes between the two. There are a variety of ways one might automate the process, but I'm not convinced the juice is worth the squeeze.
- Projects can have background images; add the option to have a background color instead of image.
- Each task that is linked to in another task has a list of which tasks link to it. It might be nice to have a similar lists within projects - list the tasks that reference it/its tasks. I don't know where in the UI this would go.
- Recurring projects? Say I put together a newsletter every month. A recurring project would automatically be created from a given template at whatever interval is specified.
- Springing projects? I have projects for each release of a given software I'm working on. Every time I complete a release, I create a new project. It might be fun if this happened automatically.
- Right now, if you want to alter a template, you need to create an instance of the project, edit it, save it as a new template, then chuck the starting template. This is awkward. It would be nice if you could just alter the template inline.

### Quick Filter
- The quick filter (to filter down a list of tasks to only those matching a search term) supports "not:" to find everything not matching that term. It recognizes project (#project) and tag (@tag) shorthand. Maybe one day I'll add these to the full search page input and/or the nav bar search.
- It would be nice if the quick filter pulled all the results in the database, not just hiding the non-matching on tasks already displayed on the current page. It only filters down what's currently on the screen. It won't check the server to see if there are other tasks that match.

### Tags
Tags applied to a whole project? Still working out how I'd like it to behave:
- Even for software projects, where by nature nearly everything will be done on the @big-computer, every once in a while there's a task that doesn't need to be. How do we handle these exceptions? 
- Is there a `projects_tags` table? Or are the project-level tasks automatically applied to each task individually? Then we could not-tag individual tasks. If we do it that way, when we remove a task to another project, do the project-level tags get removed? 

## Solutions in Search of Problems
- Tag users in comments - as of v9, there's now an alert for lists of changes that other users made to shared tasks, so it's not really necessary.

### More Behaviors as Profile Preferences for Users
- Profile preference to dictate which properties appear on a task in a list. Right now it shows almost everything, but there may be users who don't care to see the recurrence or location or whatever, so maybe one day they'll have the ability to not show those. This is kind of a feature for feature's sake - I don't plan to use it, and I don't think this software has any other users. Is that stopping me from writing documentation for imaginary friends? It is not.
- There are several (many?) values set in `.env` that could be moved to user-preferences, such as how many things are visible in a given page, up to a maximum of `PAGINATION_PER_PAGE` in `.env`.

### Auto-Completion
- There's syntax to link to a project or tag (for project five and tag five, these would be [project:5] and [tag:5], respectively). Now that URLs are replaced by the task/tag/project name, I doubt I'll ever use this syntax. That said, if I do, it might be nice to have a dropdown from which to select your project/tag/whatever.
- When you type & in quick add, it'll provide a list of users you might want to assign the task to. It might be kind of cool to show their profile images. Again, this is solving a non-problem - I have two users on my system. I can remember which one is me and which one is not. But it sounds cool.

### More Sophisticated Search
I wouldn't use any of these frequently enough to justify the dev time, but they sound cool.
- Saved searches? 
- Specify whether the search is and/or. Right now all search terms refine the list - you can't search for Tag A or Tag B. 
- Search in multiple projects

### Task Lists Drawing from Multiple Projects/Tags
As described in the Export PNG heading of [Day Export](/docs/features/day-export/), I export my day's tasks to PNG to print on a receipt printer. This prevents me from keeping my nose in my phone all day.

#### Use Cases 
Sometimes I wish I could export just a subtask of a list. I don't really need to print the things like "brush my teeth" and "feed the cat" on the printed list I consult during the middle of the day. I do want to see those on my full list. 

This could be accomplished with the use of a tag like `ignore-for-export` - apply it to the tasks you don't want exported, then in the on-page filter, put `not:@ignore-for-export` and watch those tasks disappear. I don't love it, in no small part because it makes choices for other people assigned to the task: I want to see the things I expect to do today, but I don't want to see the ones the other user is also assigned to. He, however, wants to see those, but he can't because I tagged them with `ignore-for-export`. Tags aren't scoped per-user and I don't think there's a strong case for implementing such a thing. 

Also, sometimes I think it would be nice to print the things I plan to do today, and a separate list of bonus things. These would probably draw from many projects/tags. 

This too could be accomplished with a tag like `bonus-tag`, but, in addition to the previously mentioned problem of applying these tags for all users, I think I would spend a lot of time managing the addition/removal of this tag. 

Any way it's implemented, this feature would definitely result in me spending more time managing lists than doing the things on the lists.

