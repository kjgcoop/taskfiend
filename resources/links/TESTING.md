# Testing Guide for Task Fiend

This document provides a comprehensive guide for testing Task Fiend, with a special focus on authorization and privacy tests.

## Quick Start

### 1. Install Playwright Browsers

```bash
npx playwright install
```

### 2. Set Up Test Database

⚠️ **IMPORTANT**: Configure a separate test database to avoid wiping your development data!

Create `.env.testing`:

```env
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
# Or use a file: DB_DATABASE=/absolute/path/to/test-database.sqlite
```

### 3. Run Tests

```bash
npm run test:e2e
```

## Test Suites

### Authorization & Privacy Tests

The E2E test suite focuses on ensuring users cannot access data they shouldn't see:

#### 📋 Task Authorization (`tests/e2e/task-authorization.spec.js`)

**What it tests:**
- Users can only see their own tasks
- Users can see tasks they're assigned to
- Users CANNOT access other users' tasks
- Task creators can manage assignees
- Search respects task privacy
- Dashboard views (Today, Inbox) respect task privacy

**Why it matters:**
Privacy is critical. These tests ensure that if User A creates a task, User B cannot see it unless explicitly assigned.

#### 📁 Project Authorization (`tests/e2e/project-authorization.spec.js`)

**What it tests:**
- Users can only see their own projects
- Users can see projects they're assigned to
- Users CANNOT access other users' projects
- Project creators can manage assignees
- Tasks inherit project visibility
- Search respects project privacy
- Removing assignees immediately revokes access

**Why it matters:**
Projects organize work. These tests ensure project-level permissions cascade properly to tasks and that access changes take effect immediately.

#### 🏷️ Tag Visibility (`tests/e2e/tag-visibility.spec.js`)

**What it tests:**
- Tags are globally visible to ALL users
- Any user can create, edit, or manage tags
- Users can apply any tag to their tasks
- **BUT** tags do NOT grant access to tasks

**Why it matters:**
Tags are a shared resource. These tests verify the unique security model where tags are global but don't bypass task/project privacy.

## Running Tests

### All Tests

```bash
npm run test:e2e
```

### Interactive UI Mode (Recommended for Development)

```bash
npm run test:e2e:ui
```

This opens Playwright's interactive UI where you can:
- Run individual tests
- See step-by-step execution
- Inspect the DOM at each step
- Time-travel through test execution

### Headed Mode (See the Browser)

```bash
npm run test:e2e:headed
```

Useful for watching tests execute in a real browser.

### Debug Mode

```bash
npm run test:e2e:debug
```

Opens Playwright Inspector for step-by-step debugging.

### Specific Test File

```bash
npx playwright test task-authorization
npx playwright test project-authorization
npx playwright test tag-visibility
```

### View Test Report

```bash
npm run test:e2e:report
```

## Test Data

Tests automatically create these users:

| Email | Name | Password |
|-------|------|----------|
| user1@test.com | User One | password123 |
| user2@test.com | User Two | password123 |
| user3@test.com | User Three | password123 |

Database is reset before each test suite to ensure clean state.

## Continuous Integration

### GitHub Actions Example

Create `.github/workflows/playwright.yml`:

```yaml
name: E2E Tests
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install Composer dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: '18'

      - name: Install npm dependencies
        run: npm ci

      - name: Install Playwright browsers
        run: npx playwright install --with-deps

      - name: Prepare Laravel Application
        run: |
          cp .env.example .env.testing
          php artisan key:generate --env=testing

      - name: Run Playwright tests
        run: npm run test:e2e

      - name: Upload test results
        uses: actions/upload-artifact@v3
        if: always()
        with:
          name: playwright-report
          path: playwright-report/
          retention-days: 30
```

## Understanding Test Failures

### Common Failure Scenarios

#### "Cannot access unauthorized task" test fails

**Symptoms**: User B can see User A's task

**Possible causes**:
1. Authorization middleware not applied to route
2. Query in controller not filtering by user
3. View not checking permissions

**Check**:
- `app/Http/Controllers/TaskController.php` - `index()` method
- Routes in `routes/web.php` - middleware applied?
- Authorization logic in controller actions

#### "User can see assigned task" test fails

**Symptoms**: User B cannot see task they're assigned to

**Possible causes**:
1. Assignment not saved to database
2. Query not including assigned tasks
3. Eager loading issue with relationships

**Check**:
- `app/Models/Task.php` - `assignments` relationship defined?
- Controller query includes `orWhereHas('assignments')`
- Database seeding created the assignment correctly

### Debugging Failed Tests

1. **Run in headed mode** to see what's happening:
   ```bash
   npm run test:e2e:headed
   ```

2. **Add a pause** to the test:
   ```javascript
   await page.pause(); // Test will pause here
   ```

3. **Check the trace** for failed tests:
   - After test fails, run: `npx playwright show-report`
   - Click on failed test
   - View trace to see screenshots and DOM at each step

4. **Add debug logging**:
   ```javascript
   console.log('Current URL:', page.url());
   console.log('Page content:', await page.content());
   ```

## Writing Additional Tests

### Example: Testing a New Feature

```javascript
import { test, expect } from '@playwright/test';
import { resetDatabase, seedTestData } from './helpers/db.js';
import { login, testUsers } from './helpers/auth.js';

test.describe('New Feature Authorization', () => {
  test.beforeAll(async () => {
    await resetDatabase();
    await seedTestData();
  });

  test('user cannot access another users feature', async ({ page }) => {
    // User 1 creates something
    await login(page, testUsers.user1.email);
    await page.goto('/feature/create');
    await page.fill('input[name="title"]', 'Private Feature');
    await page.click('button[type="submit"]');

    const featureId = page.url().match(/\/feature\/(\d+)/)[1];
    await logout(page);

    // User 2 tries to access it
    await login(page, testUsers.user2.email);
    await page.goto(`/feature/${featureId}`);

    // Should be denied
    const isAccessDenied = page.url() !== `/feature/${featureId}`;
    expect(isAccessDenied).toBeTruthy();
  });
});
```

### Best Practices

1. **Always test the negative case**: Don't just test that authorized users CAN access, test that unauthorized users CANNOT

2. **Test direct URL access**: Users might try to access resources by guessing URLs

3. **Test after state changes**: When you remove an assignee, verify they immediately lose access

4. **Use semantic selectors**: `page.click('text=Submit')` is better than `page.click('.btn-primary')`

5. **Clean up between tests**: Use `beforeEach` to ensure each test starts with a clean slate

## Performance

Tests run in parallel by default. On a typical machine:

- Full test suite: ~2-3 minutes
- Single test file: ~30-60 seconds

To run tests serially (slower but easier to debug):

```bash
npx playwright test --workers=1
```

## Resources

- [Playwright Documentation](https://playwright.dev)
- [Playwright Best Practices](https://playwright.dev/docs/best-practices)
- [Writing Effective E2E Tests](https://playwright.dev/docs/writing-tests)
- Full E2E documentation: `tests/e2e/README.md`

## Questions?

Check the detailed documentation in `tests/e2e/README.md` or refer to the Playwright docs.
