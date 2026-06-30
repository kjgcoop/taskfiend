---
title: "Testing"
---

## Quick start

### Install Playwright browsers

```bash
npx playwright install
```

### Set up the test database

The test suite uses a separate database to avoid touching your development data. Create `.env.testing` if it doesn't exist:

```env
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/test-database.sqlite
```

### Run tests

```bash
npm test                    # All tests
npm run test:e2e            # Playwright E2E tests only
php artisan test            # PHPUnit unit/feature tests only
```

---

## E2E test suites

Tests are in `tests/e2e/` and focus on authorization and privacy — verifying that users cannot access data they shouldn't see.

### Task authorization (`task-authorization.spec.js`)

- Users can only see their own tasks
- Users can see tasks they're assigned to
- Users cannot access other users' tasks
- Search and dashboard views (Today, Inbox) respect task privacy

### Project authorization (`project-authorization.spec.js`)

- Users can only see their own projects
- Users can see projects they're assigned to
- Removing an assignee immediately revokes access
- Task visibility inherits from project visibility

### Tag visibility (`tag-visibility.spec.js`)

- Tags are globally visible to all users
- Any user can create and manage tags
- Tags do **not** grant access to tasks

---

## Running specific tests

```bash
npm run test:e2e:headed           # Watch tests in Firefox
npm run test:e2e:ui               # Playwright interactive UI
npm run test:e2e:debug            # Playwright Inspector (step-by-step)
npm run test:e2e:report           # Open last test report

npx playwright test task-authorization
npx playwright test project-authorization
npx playwright test tag-visibility
```

---

## Test data

Tests create three users automatically before each suite and reset the database between suites:

| Email | Password |
|-------|----------|
| user1@test.com | password123 |
| user2@test.com | password123 |
| user3@test.com | password123 |

---

## Writing new tests

```javascript
import { test, expect } from '@playwright/test';
import { resetDatabase, seedTestData } from './helpers/db.js';
import { login, testUsers } from './helpers/auth.js';

test.describe('My feature', () => {
  test.beforeAll(async () => {
    await resetDatabase();
    await seedTestData();
  });

  test('user cannot access another user\'s resource', async ({ page }) => {
    await login(page, testUsers.user1.email);
    // ... create something as user1

    await login(page, testUsers.user2.email);
    // ... try to access it as user2, expect denial
  });
});
```

**Best practices:**

- Always test the negative case — not just that authorized users can access things, but that unauthorized users cannot
- Test direct URL access — users may guess IDs
- Test after state changes — removing an assignee should immediately revoke access
- Use semantic selectors (`page.click('text=Submit')`) over CSS class selectors

---

## CI example

```yaml
# .github/workflows/playwright.yml
name: E2E Tests
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2' }
      - run: composer install --no-interaction --prefer-dist
      - uses: actions/setup-node@v3
        with: { node-version: '18' }
      - run: npm ci
      - run: npx playwright install --with-deps
      - run: |
          cp .env.example .env.testing
          php artisan key:generate --env=testing
      - run: npm run test:e2e
      - uses: actions/upload-artifact@v3
        if: always()
        with:
          name: playwright-report
          path: playwright-report/
          retention-days: 30
```

---

## Troubleshooting failures

**"Cannot access unauthorized task" fails (user B can see user A's task):**
Check that the controller query filters by creator/assignment and that the route has `auth` middleware.

**"User can see assigned task" fails (user B cannot see a task they're assigned to):**
Check that the assignment was saved, that the query includes `orWhereHas('assignments')`, and that relationships are defined in `Task.php`.

**General debugging:**

```bash
# Watch tests run
npm run test:e2e:headed

# Open the report after a failure
npx playwright show-report
```

See `tests/e2e/README.md` for a more detailed guide.
