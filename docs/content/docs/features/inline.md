---
title: "Inline Values"
---

When creating a task in the quick add, you can assign given properties inline. 

- Assign a project by typing # and selecting the project from the list below. A task can have only one project.
    - On a project's page, a task you add goes into that project by default. Type a different `#project` and it goes there instead. For example, typing `Do a thing #project-b` while viewing Project A puts the task in Project B, while plain `Do a thing` stays in Project A.
- Assign a tag by typing @ and selecting the tag from the list below. A task can have multiple tags. To assign a subsequent tag, type a new @ and select the subsequent tag from the dropdown.
- Add a location with a + symbol, such as +Home. 
    - If you'd like it to be formatted as a link to Google Maps, use ++. For example, ++library. 
    - If you'd like to assign a location with a space in it, surround it with quotes, +"Hollywood Library" or ++"123 Main Street, Town, ST, 12345 USA"
- Assign to one or more users by typing & and selecting a user. You can select one user at a time, but you can type additional & symbols to assign additional users.
- Dates and recurrences - see [Dates](/docs/features/dates)

## Words That Aren't Shortcuts

A `#project`, `@tag` or `&user` word is treated as a shortcut, and removed from the task's name, only if it matches a real project, tag or user. Anything else is left in the name exactly as you typed it, so you can still write a hashtag or an @handle. (A `+location` is the exception: any place you type is accepted as a new location.) For example, if you don't have a project called "laravel", `Tweet about #laravel` is saved with that name unchanged, and `Here is a thing @notatag #work` is saved to the Work project as `Here is a thing @notatag`.
