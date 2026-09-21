import { test, expect } from '@playwright/test';
import { login, logout, testUsers } from './helpers/auth.js';

/**
 * Daily Digest Seed / Privacy Test
 *
 * Gives user1 and user2 each a task due today (an incomplete, undone,
 * unarchived task with today's date — exactly what `email:task-digest`
 * queries), then verifies each user's Day view still only shows their own
 * task, not the other user's.
 *
 * This app never deletes tasks (only archives), and no other test in this
 * suite deletes or archives tasks it creates, so the two tasks below persist
 * in the test database after the run finishes. That's intentional: it lets
 * `php artisan email:task-digest --env=testing <email>` be tried against
 * user1@test.com / user2@test.com right after `npm run test:e2e`, without a
 * separate seeding step. (See README.md.)
 */

test.describe('Daily digest seed data', () => {
  test('user1 and user2 each get a today task, visible only to themselves', async ({ page }) => {
    const today = new Date().toISOString().split('T')[0];

    // User 1: create a task due today.
    await login(page, testUsers.user1.email);
    await page.goto('/tasks/create');
    await page.fill('#name', 'Digest Seed Task for User One');
    await page.fill('input[placeholder*="tomorrow"]', today);
    await page.fill('input[name="time"]', '09:00');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tasks\/\d+/);
    await logout(page);

    // User 2: create a task due today.
    await login(page, testUsers.user2.email);
    await page.goto('/tasks/create');
    await page.fill('#name', 'Digest Seed Task for User Two');
    await page.fill('input[placeholder*="tomorrow"]', today);
    await page.fill('input[name="time"]', '09:00');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tasks\/\d+/);

    // User 2's Day view shows their own task, not User 1's.
    await page.goto('/day');
    await expect(page.locator('text=Digest Seed Task for User Two')).toBeVisible();
    await expect(page.locator('text=Digest Seed Task for User One')).not.toBeVisible();
    await logout(page);

    // User 1's Day view shows their own task, not User 2's.
    await login(page, testUsers.user1.email);
    await page.goto('/day');
    await expect(page.locator('text=Digest Seed Task for User One')).toBeVisible();
    await expect(page.locator('text=Digest Seed Task for User Two')).not.toBeVisible();
  });
});
