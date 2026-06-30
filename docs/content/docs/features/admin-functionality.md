---
title: "Admin Functionality"
---

There is no admin UI. Here are the commands to do admin-like things.

## User management

```bash
php artisan user:create {email} {name} {password}   # Create a user
php artisan user:toggle {email}                      # Enable or disable a user
php artisan user:password {email}                    # Reset password; outputs new password
```

Disabled users cannot log in, but their tasks remain. An account can be re-enabled. API keys for disabled users are rejected by the API middleware.

### Reset a Forgotten Password
Because I run this on a server with outbound mail ports blocked, I didn't bother implementing a forgotten password form. A user has to log into the server and run this from the command line:
`php artisan user:password {email}`

It will output the user's new password.

## API keys

```bash
php artisan apikey:create {email}    # Generate a key — printed once, note it immediately
php artisan apikey:invalidate {key}  # Permanently invalidate a key (cannot be re-enabled)
```

Keys are stored hashed; there is no way to retrieve a key after creation.

An invalidated API key cannot be re-validated.

## Move Overdue Tasks to Today
I only use this in my test environment, but somebody may want it for prod use. I don't remember offhand why I had the AI build a script for it as opposed to running something like `update tasks set date=NOW() where date < NOW and user_id=[whatever]();`.
```bash
php artisan tasks:move-overdue    # Reschedule all overdue incomplete tasks to today
```

