import { test, expect } from '@playwright/test';
import { login, logout, testUsers } from './helpers/auth.js';

/**
 * Task Side Panel Tests
 *
 * Covers the slide-out panel that appears when a user clicks a task row.
 * The panel fetches /tasks/{id}/panel and injects the HTML dynamically.
 */

test.describe('Task Side Panel', () => {
  let taskName;

  test.beforeEach(async ({ page }) => {
    taskName = `Panel Test Task ${Date.now()}`;
    await login(page, testUsers.user1.email);

    // Create a task with a known name and description
    await page.goto('/tasks/create');
    await page.fill('#name', taskName);
    await page.fill('textarea[name="description"]', 'Panel test description text');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tasks\/\d+/);

    await page.goto('/all-tasks');
  });

  test.afterEach(async ({ page }) => {
    // The panel overlay intercepts pointer events when open, blocking the
    // user-menu button that logout() needs to click. Dismiss it first.
    if (await page.locator('#task-panel-overlay').isVisible()) {
      await page.keyboard.press('Escape');
      await page.waitForSelector('#task-panel-overlay', { state: 'hidden' });
    }
    await logout(page);
  });

  test('clicking a task opens the side panel with the task name', async ({ page }) => {
    await page.locator(`[data-task-name-display]:has-text("${taskName}")`).click();

    await page.waitForSelector('#task-panel-overlay', { state: 'visible' });
    await page.waitForLoadState('networkidle');

    await expect(page.locator('#task-panel-content .task-title')).toContainText(taskName);
  });

  test('side panel shows the task description', async ({ page }) => {
    await page.locator(`[data-task-name-display]:has-text("${taskName}")`).click();

    await page.waitForSelector('#task-panel-overlay', { state: 'visible' });
    await page.waitForLoadState('networkidle');

    await expect(page.locator('#task-panel-content')).toContainText('Panel test description text');
  });

  test('close button dismisses the panel', async ({ page }) => {
    await page.locator(`[data-task-name-display]:has-text("${taskName}")`).click();
    await page.waitForSelector('#task-panel-overlay', { state: 'visible' });
    await page.waitForLoadState('networkidle');

    await page.click('button[title="Close panel"]');

    await expect(page.locator('#task-panel-overlay')).not.toBeVisible();
  });

  test('clicking the backdrop dismisses the panel', async ({ page }) => {
    await page.locator(`[data-task-name-display]:has-text("${taskName}")`).click();
    await page.waitForSelector('#task-panel-overlay', { state: 'visible' });
    await page.waitForLoadState('networkidle');

    // The panel drawer is 90vw wide and right-aligned, leaving a ~10% strip
    // on the left where the backdrop is not obscured. Click there.
    await page.locator('#task-panel-overlay > div.absolute').click({ position: { x: 10, y: 300 } });

    await expect(page.locator('#task-panel-overlay')).not.toBeVisible();
  });

  test('panel inline name edit persists after save', async ({ page }) => {
    const updatedName = `${taskName} (edited)`;

    await page.locator(`[data-task-name-display]:has-text("${taskName}")`).click();
    await page.waitForSelector('#task-panel-overlay', { state: 'visible' });
    await page.waitForLoadState('networkidle');

    // Click the name field to start editing
    await page.locator('#task-panel-content .task-title').click();

    // Wait for the input to appear and type the new name
    const nameInput = page.locator('#task-panel-content input[x-ref="nameInput"]');
    await nameInput.waitFor({ state: 'visible' });
    await nameInput.fill(updatedName);
    await nameInput.press('Enter');

    await page.waitForLoadState('networkidle');

    // The panel should now show the updated name
    await expect(page.locator('#task-panel-content .task-title')).toContainText(updatedName);

    // The task row in the list behind the panel should also reflect the update
    await page.click('button[title="Close panel"]');
    await expect(page.locator(`[data-task-name-display]:has-text("${updatedName}")`)).toBeVisible();
  });

  test('"Open full page" link navigates to the task detail page', async ({ page }) => {
    await page.locator(`[data-task-name-display]:has-text("${taskName}")`).click();
    await page.waitForSelector('#task-panel-overlay', { state: 'visible' });
    await page.waitForLoadState('networkidle');

    await page.click('text=Open full page');
    await page.waitForURL(/\/tasks\/\d+/);

    // The task name lives in the page header <h2>, not inside <main>
    await expect(page.locator('h2.task-title')).toContainText(taskName);
  });
});
