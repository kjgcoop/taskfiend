---
title: "Admin Functionality"
---

There is no admin UI. Here are the commands to do admin-like things.

## Create/Disable Users
- Create a user: `php artisan user:create {email} {name} {password}`
- Toggle whether a user is enabled or disabled: `php artisan user:toggle {email}`

A user who is disabled is unable to log in, but their tasks remain present. If they're re-enabled, their tasks are still waiting for them. Tasks they've assigned to other user(s) are still available to those other user(s).

## Reset a Forgotten Password
Because I run this on a server with outbound mail ports blocked, I didn't bother implementing a forgotten password form. A user has to log into the server and run this from the command line:
`php artisan user:password {email}`

It will output the user's new password.

## API Keys
- Create an API key: `php artisan apikey:create {email}` - this will only print the API key the one time, so note the value immediately.
- Disable an API key: `php artisan apikey:invalidate {key}`

An invalidated API key cannot be re-validated.

