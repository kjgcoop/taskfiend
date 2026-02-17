## About Task Fiend
This is a vibe-coded to-do list software that is a lot like many of the other open source task lists out there. None of them were *perfect* and watching machines program is fun, so I asked Claude to code one for me.

Because I designed this for my own devious purposes, I assumed there would only be two or three users. It would probably fine for more than that, but it's not designed to scale. It assumes at least one user is technical enough to run this on a server and run scripts at the command line.

### Get Docker Running:
1. Create environment file: `cp .env.example .env`
2. Edit .env and set APP_KEY, APP_ENV=production, APP_DEBUG=false
3. Build and start containers: `docker-compose up -d --build`
4. Run migrations and create first user:
   - `docker-compose exec app php artisan migrate --force`
   - `docker-compose exec app php artisan user:create admin@example.com "Admin User" password123`


### Admin-Like Functions
There is no admin UI. Here are the commands to do admin-like things.

#### Create/Disable users
- Create a user: `php artisan user:create {email} {name} {password}`
- Toggle whether a user is enabled or disabled: `php artisan user:toggle {email}`

A user who is disabled is unable to log in, but their tasks remain present. If they're re-enabled, their tasks are still waiting for them. Tasks they've assigned to other user(s) are still available to those other user(s).

#### API Keys
- Create an API key: `php artisan apikey:create {email}` - this will only print the API key the one time, so note the value immediately.
- Disable an API key: `php artisan apikey:invalidate {key}`

An invalidated API key cannot be re-validated.

### Adding Other Pages
I created this functionality so somebody can put up their own Privacy Policy, Terms of Service, documentation or whatever.

To add a new page:
1. Put a Markdown file in storage/app/other-links
2. A link pointing to it will appear as a link on the page /other-links. 
   - You can access it at /other-links/[filename]. 
   - The title will be the filename minus the extension with - and _ replaced with spaces. The example file in there, Read-Me.md, will show up with the title Read Me.
3. The code will turn the Markdown into HTML and spit out the contents. 

If you add documentation, please consider contributing it back to this project. If it's something you/your users need documented, there's probably somebody else out there who could also use it.

### Purging User Data
The idea is to keep data even after users go away, but I needed this for testing the Todoist import:
`php artisan user:purge [email address] --force`

### Move Overdue Tasks to Today
I only want to do this in dev, so this is run at the command line:
`php artisan tasks:move-overdue`
