# Task Fiend E2E Tests

This directory contains end-to-end tests for Task Fiend using Playwright. These tests focus heavily on authorization and privacy to ensure users can only see and interact with data they have permission to access.

## Test Coverage

### Task Authorization Tests (`task-authorization.spec.js`)
Tests that verify task privacy and authorization rules:

- ✅ Users can only see their own tasks in task lists
- ✅ Users can see tasks they are assigned to
- ✅ Users cannot access task detail pages for unauthorized tasks
- ✅ Users cannot edit tasks they don't own
- ✅ Task creators can add and remove assignees
- ✅ Assignees have proper permissions on assigned tasks
- ✅ Users cannot see unassigned tasks in search results
- ✅ Inbox view only shows user's own tasks
- ✅ Today view only shows user's tasks due today

### Project Authorization Tests (`project-authorization.spec.js`)
Tests that verify project privacy and authorization rules:

- ✅ Users can only see their own projects in project lists
- ✅ Users can see projects they are assigned to
- ✅ Users cannot access project detail pages for unauthorized projects
- ✅ Users cannot edit projects they don't own
- ✅ Project creators can add and remove assignees
- ✅ Assignees can view assigned projects
- ✅ Tasks in projects inherit project visibility
- ✅ Users cannot create tasks in projects they can't access
- ✅ Users cannot see other users' projects in search
- ✅ Removing user from project hides it from their view

### Tag Visibility Tests (`tag-visibility.spec.js`)
Tests that verify tags are globally accessible:

- ✅ Tags created by one user are visible to all users
- ✅ All users can view tag details
- ✅ All users can edit any tag
- ✅ Users can use tags created by other users on their tasks
- ✅ Tag list shows tags from all users
- ✅ Tags appear in search for all users
- ✅ Tag changes are visible to all users
- ✅ **BUT** users still cannot see tasks tagged with tags if not authorized

### Daily Digest Seed Test (`daily-digest-seed.spec.js`)
Gives `user1@` and `user2@` (see Test User Credentials below for the domain) each an
incomplete task due today, while verifying each user's Day view still only shows their own:

- ✅ User 1's today-task is visible only to User 1
- ✅ User 2's today-task is visible only to User 2

Since the app never deletes tasks, both tasks are still in the test database once the full
suite finishes. That's the point — it lets you try the daily digest email
(`php artisan email:task-digest --env=testing <email>`) against a real "has a task due today"
user right after a test run, without a separate manual seeding step. Change that user's email
address in the test database afterwards if you want to receive the emails yourself.

## Setup

### Prerequisites

1. Node.js and npm installed
2. PHP and Composer installed
3. Laravel application set up with database configured

### Install Playwright

Playwright is already added to the project. To install browsers:

```bash
npx playwright install
```

### Database Setup

The tests automatically:
- Reset the database before running (`migrate:fresh`)
- Seed test users (user1@, user2@, user3@ — see TEST_USER_DOMAIN below)
- Clean up after tests

⚠️ **WARNING**: Tests will wipe your database! Use a separate test database.

## Running Tests

### Run all E2E tests

```bash
npm run test:e2e
```

Or using Playwright directly:

```bash
npx playwright test
```

### Run specific test file

```bash
npx playwright test task-authorization
npx playwright test project-authorization
npx playwright test tag-visibility
```

### Run tests in UI mode (interactive)

```bash
npx playwright test --ui
```

### Run tests in headed mode (see browser)

```bash
npx playwright test --headed
```

### Run tests in a specific browser

```bash
npx playwright test --project=chromium
npx playwright test --project=firefox
npx playwright test --project=webkit
```

### Debug tests

```bash
npx playwright test --debug
```

## Test Results

### View HTML Report

After tests run, view the HTML report:

```bash
npx playwright show-report
```

### View Traces

If a test fails, Playwright captures a trace. View it:

```bash
npx playwright show-trace trace.zip
```

## Configuration

### Playwright Config

Main configuration is in `playwright.config.js` at the project root.

Key settings:
- Base URL: `http://localhost:8000`
- Test directory: `./tests/e2e`
- Automatically starts Laravel dev server before tests
- Captures screenshots on failure
- Captures traces on first retry

### Test Database

**Important**: Configure a separate test database in `.env.testing`:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/test-database.sqlite
```

Or use an in-memory SQLite database:

```env
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

## Writing New Tests

### Test Structure

```javascript
import { test, expect } from '@playwright/test';
import { resetDatabase, seedTestData } from './helpers/db.js';
import { login, logout, testUsers } from './helpers/auth.js';

test.describe('Feature Name', () => {
  test.beforeAll(async () => {
    await resetDatabase();
    await seedTestData();
  });

  test('test description', async ({ page }) => {
    await login(page, testUsers.user1.email);
    // Your test code here
  });
});
```

### Available Helpers

#### Database Helpers (`./helpers/db.js`)

- `resetDatabase()` - Wipe and migrate database
- `seedTestData()` - Create test users
- `cleanDatabase()` - Clean up database
- `artisan(command)` - Run artisan commands

#### Auth Helpers (`./helpers/auth.js`)

- `login(page, email, password)` - Log in a user
- `logout(page)` - Log out current user
- `setupAuthenticatedSession(page, email, password)` - Set up authenticated session
- `testUsers` - Object with test user credentials
  - `testUsers.user1` - { email, password, name }
  - `testUsers.user2` - { email, password, name }
  - `testUsers.user3` - { email, password, name }

### Test User Credentials

All test users use password: `password123`, at the domain set by `TEST_USER_DOMAIN` in
`.env.testing` (defaults to `example.com`):

- user1@{domain} - User One
- user2@{domain} - User Two
- user3@{domain} - User Three

## CI/CD Integration

### GitHub Actions

Add to `.github/workflows/playwright.yml`:

```yaml
name: Playwright Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
      - name: Install dependencies
        run: npm ci
      - name: Install Playwright
        run: npx playwright install --with-deps
      - name: Run Playwright tests
        run: npx playwright test
      - uses: actions/upload-artifact@v3
        if: always()
        with:
          name: playwright-report
          path: playwright-report/
```

## Troubleshooting

### Tests fail with "Cannot connect to server"

Ensure Laravel dev server is running:

```bash
php artisan serve
```

Or let Playwright start it automatically (configured in `playwright.config.js`).

### Database permission errors

Ensure test database file is writable:

```bash
chmod 666 database/test-database.sqlite
```

### Test timeout errors

Increase timeout in `playwright.config.js`:

```javascript
timeout: 30000, // 30 seconds
```

### Need to see what's happening

Run tests in headed mode:

```bash
npx playwright test --headed --slowmo=1000
```

## Best Practices

1. **Isolation**: Each test should be independent and not rely on other tests
2. **Cleanup**: Use `beforeAll` and `afterAll` hooks to set up and clean up
3. **Selectors**: Use semantic selectors (text, role) over CSS selectors when possible
4. **Assertions**: Use Playwright's built-in assertions for better error messages
5. **Wait**: Use `waitForURL`, `waitForSelector` instead of arbitrary timeouts
6. **Debug**: Use `page.pause()` to debug tests interactively

## Common Patterns & Pitfalls

### Strict Mode Violations

**Problem**: Locators that match multiple elements cause "strict mode violation" errors.

```javascript
// ❌ BAD: Will fail if "Task Name" appears multiple times
await expect(page.locator('text=Task Name')).toBeVisible();

// ✅ GOOD: Use .first() to select the first match
await expect(page.locator('text=Task Name').first()).toBeVisible();

// ✅ BETTER: Use more specific selectors
await expect(page.locator('h2:has-text("Task Name")')).toBeVisible();
```

**When this happens**: Common with tag names, user names, or any repeated content on a page.

### Confirmation Dialogs

**Problem**: Clicking buttons with `onclick="return confirm(...)"` times out.

```javascript
// ❌ BAD: Click will trigger dialog but test will hang
await page.click('button:has-text("Delete")');

// ✅ GOOD: Handle dialog before clicking
page.once('dialog', dialog => dialog.accept());
await page.click('button:has-text("Delete")');

// For canceling:
page.once('dialog', dialog => dialog.dismiss());
```

### Dropdown Menus

**Problem**: Clicking dropdown items fails with "element not visible".

```javascript
// ❌ BAD: Dropdown may not be open yet
await page.click('button:has-text("User")');
await page.click('text=Log Out');

// ✅ GOOD: Wait for dropdown to open
await page.click('button:has-text("User")');
await page.waitForSelector('text=Log Out', { state: 'visible' });
await page.click('text=Log Out');
```

### Inline Editing with Alpine.js

**Problem**: Clicking wrong element or finding multiple "Save" buttons.

```javascript
// ❌ BAD: Clicks nested child instead of clickable parent
await page.locator('div.space-y-1').click();

// ❌ BAD: Multiple Save buttons exist on page
await page.click('button:has-text("Save")');

// ✅ GOOD: Click the actual clickable element
await page.locator('div.cursor-pointer').click();

// ✅ GOOD: Scope button selector to specific section
const section = page.locator('div').filter({ hasText: 'Assigned To' });
await section.locator('button:has-text("Save")').click();
```

### Form Submissions

**Problem**: Nested forms or multiple submit buttons cause unexpected behavior.

```javascript
// ❌ BAD: May submit wrong form if forms are nested
await page.click('button[type="submit"]');

// ✅ GOOD: Use specific button text or scope to form
await page.click('button:has-text("Update Tag")');

// ✅ GOOD: Ensure forms are separate in HTML
<form method="POST" action="/update">
  <button type="submit">Update</button>
</form>
<form method="POST" action="/delete">
  <button type="submit">Delete</button>
</form>
```

### Filtering Locators

**Problem**: Need to find a specific instance among multiple similar elements.

```javascript
// ❌ BAD: Gets first div with class mt-4 (may not be the right one)
await page.locator('div.mt-4').click();

// ✅ GOOD: Filter by text content
const section = page.locator('div.mt-4').filter({ hasText: 'Assigned To' });
await section.locator('div.cursor-pointer').click();

// ✅ GOOD: Use nth() for specific index
await page.locator('div.task-card').nth(2).click();
```

### Waiting for Dynamic Content

**Problem**: Elements added by JavaScript may not be ready immediately.

```javascript
// ❌ BAD: May fail if content loads slowly
await page.click('#load-more');
await page.click('.new-item');

// ✅ GOOD: Wait for specific selector to appear
await page.click('#load-more');
await page.waitForSelector('.new-item', { state: 'visible' });
await page.click('.new-item');

// ✅ GOOD: Wait for network to be idle
await page.click('#load-more');
await page.waitForLoadState('networkidle');
```

### Test Data Uniqueness

**Problem**: Tests interfere with each other due to shared data.

```javascript
// ❌ BAD: Hard-coded names may conflict
await page.fill('#name', 'Test Task');

// ✅ GOOD: Use timestamps for uniqueness
await page.fill('#name', `Test Task ${Date.now()}`);

// ✅ GOOD: Use test isolation with beforeEach
test.beforeEach(async () => {
  await resetDatabase();
  await seedTestData();
});
```

## Additional Resources

- [Playwright Documentation](https://playwright.dev)
- [Playwright Best Practices](https://playwright.dev/docs/best-practices)
- [Playwright API Reference](https://playwright.dev/docs/api/class-playwright)
