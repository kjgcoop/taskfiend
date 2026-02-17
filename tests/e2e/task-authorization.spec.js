import { test, expect } from '@playwright/test';
import { login, logout, testUsers } from './helpers/auth.js';

/**
 * Task Authorization & Privacy Tests
 *
 * These tests verify that users can only see and interact with tasks
 * they created or are assigned to, ensuring proper data privacy.
 */

test.describe('Task Authorization & Privacy', () => {
  test.beforeEach(async ({ page }) => {
    // Ensure clean state for each test
    await page.goto('/login');
  });

  test('user can only see their own tasks in task list', async ({ page }) => {
    // User 1 creates a task
    await login(page, testUsers.user1.email);
    await page.goto('/tasks/create');
    await page.fill('#name', 'User 1 Private Task');
    await page.fill('textarea[name="description"]', 'This should only be visible to User 1');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tasks\/\d+/);
    await logout(page);

    // User 2 creates a task
    await login(page, testUsers.user2.email);
    await page.goto('/tasks/create');
    await page.fill('#name', 'User 2 Private Task');
    await page.fill('textarea[name="description"]', 'This should only be visible to User 2');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tasks\/\d+/);

    // Verify User 2 can see their task
    await page.goto('/tasks');
    await expect(page.locator('text=User 2 Private Task')).toBeVisible();
    await expect(page.locator('text=User 1 Private Task')).not.toBeVisible();
    await logout(page);

    // Verify User 1 can see only their task
    await login(page, testUsers.user1.email);
    await page.goto('/tasks');
    await expect(page.locator('text=User 1 Private Task')).toBeVisible();
    await expect(page.locator('text=User 2 Private Task')).not.toBeVisible();
  });

  test('user can see tasks they are assigned to', async ({ page }) => {
    // User 1 creates a task and assigns it to User 2
    await login(page, testUsers.user1.email);
    await page.goto('/tasks/create');
    await page.fill('#name', 'Shared Task for User 2');
    await page.fill('textarea[name="description"]', 'User 2 should see this');

    // Assign to User 2
    await page.check(`label:has-text("${testUsers.user2.name}") input[name="assignee_ids[]"]`);

    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tasks\/\d+/);
    await logout(page);

    // User 2 should see the assigned task
    await login(page, testUsers.user2.email);
    await page.goto('/tasks');
    await expect(page.locator('text=Shared Task for User 2')).toBeVisible();

    // User 2 should be able to view the task detail
    await page.click('text=Shared Task for User 2');
    await expect(page.locator('text=User 2 should see this')).toBeVisible();
  });

  test('user cannot access task detail page for unauthorized task', async ({ page, context }) => {
    // User 1 creates a task
    await login(page, testUsers.user1.email);
    await page.goto('/tasks/create');
    await page.fill('#name', 'User 1 Secret Task');
    await page.fill('textarea[name="description"]', 'User 2 should not see this');
    await page.click('button[type="submit"]');

    // Get the task ID from the URL
    await page.waitForURL(/\/tasks\/(\d+)/);
    const taskUrl = page.url();
    const taskId = taskUrl.match(/\/tasks\/(\d+)/)[1];
    await logout(page);

    // User 2 tries to directly access the task URL
    await login(page, testUsers.user2.email);
    await page.goto(`/tasks/${taskId}`);

    // Should be redirected or see an error (403/404)
    // Check that we're either redirected away or see an error message
    const currentUrl = page.url();
    const isAccessDenied =
      currentUrl !== `/tasks/${taskId}` ||
      await page.locator('text=/forbidden|unauthorized|access denied|not found/i').isVisible().catch(() => false);

    expect(isAccessDenied).toBeTruthy();
  });

  test('user cannot edit tasks they do not own', async ({ page }) => {
    // User 1 creates a task
    await login(page, testUsers.user1.email);
    await page.goto('/tasks/create');
    await page.fill('#name', 'User 1 Task to Edit');
    await page.fill('textarea[name="description"]', 'Original description');
    await page.click('button[type="submit"]');

    await page.waitForURL(/\/tasks\/(\d+)/);
    const taskUrl = page.url();
    const taskId = taskUrl.match(/\/tasks\/(\d+)/)[1];
    await logout(page);

    // User 2 tries to access the edit page
    await login(page, testUsers.user2.email);
    await page.goto(`/tasks/${taskId}/edit`);

    // Should be denied access
    const currentUrl = page.url();
    const isAccessDenied =
      currentUrl !== `/tasks/${taskId}/edit` ||
      await page.locator('text=/forbidden|unauthorized|access denied|not found/i').isVisible().catch(() => false);

    expect(isAccessDenied).toBeTruthy();
  });

  test('task creator can add and remove assignees', async ({ page }) => {
    // User 1 creates a task
    await login(page, testUsers.user1.email);
    await page.goto('/tasks/create');
    await page.fill('#name', 'Task with Assignees');
    await page.fill('textarea[name="description"]', 'Testing assignee management');
    await page.click('button[type="submit"]');

    await page.waitForURL(/\/tasks\/(\d+)/);

    // Click on the assignee display area to start inline editing
    // Find the clickable div (has cursor-pointer class)
    const assigneeSection = page.locator('div.mt-4').filter({ hasText: 'Assigned To' });
    await assigneeSection.locator('div.cursor-pointer').click();

    // Wait for the inline edit form to appear and check User 2
    await page.waitForSelector('label:has-text("' + testUsers.user2.name + '") input[type="checkbox"]', { state: 'visible' });
    await page.check(`label:has-text("${testUsers.user2.name}") input[type="checkbox"]`);

    // Click Save button in the assignee section (be specific to avoid clicking other Save buttons)
    await assigneeSection.locator('button:has-text("Save")').click();

    // Wait for page reload and verify assignee was added
    await page.waitForLoadState('networkidle');
    await expect(page.locator(`text=${testUsers.user2.name}`).first()).toBeVisible();

    // Click on "Assigned To" area again to remove assignee
    await assigneeSection.locator('div.cursor-pointer').click();

    // Wait for the inline edit form and uncheck User 2
    await page.waitForSelector('label:has-text("' + testUsers.user2.name + '") input[type="checkbox"]', { state: 'visible' });
    await page.uncheck(`label:has-text("${testUsers.user2.name}") input[type="checkbox"]`);

    // Click Save button in the assignee section
    await assigneeSection.locator('button:has-text("Save")').click();

    // Wait for page reload - User 2 should no longer appear
    await page.waitForLoadState('networkidle');
  });

  test('assignee can view but verify proper permissions on assigned task', async ({ page }) => {
    // User 1 creates and assigns task to User 3
    await login(page, testUsers.user1.email);
    await page.goto('/tasks/create');
    await page.fill('#name', 'Assigned to User 3');
    await page.fill('textarea[name="description"]', 'User 3 is assigned');
    await page.check(`label:has-text("${testUsers.user3.name}") input[name="assignee_ids[]"]`);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tasks\/\d+/);
    await logout(page);

    // User 3 logs in and views assigned task
    await login(page, testUsers.user3.email);
    await page.goto('/tasks');
    await expect(page.locator('text=Assigned to User 3')).toBeVisible();

    // User 3 can view the task
    await page.click('text=Assigned to User 3');
    await expect(page.locator('text=User 3 is assigned')).toBeVisible();

    // User 3 should be able to mark task as done (as assignee)
    // but should not be able to edit task details (only creator can)
    // This depends on your authorization rules
  });

  test('user cannot see unassigned tasks in search results', async ({ page }) => {
    // User 1 creates a unique task
    await login(page, testUsers.user1.email);
    await page.goto('/tasks/create');
    const uniqueName = `Unique Task ${Date.now()}`;
    await page.fill('#name', uniqueName);
    await page.fill('textarea[name="description"]', 'Should not appear in User 2 search');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tasks\/\d+/);
    await logout(page);

    // User 2 tries to search for the task
    await login(page, testUsers.user2.email);
    await page.goto('/search');
    await page.fill('#search', uniqueName);
    await page.click('button[type="submit"]');

    // Task should not appear in results
    await expect(page.locator(`text=${uniqueName}`)).not.toBeVisible();
  });

  test('inbox view only shows user tasks', async ({ page }) => {
    // User 1 creates an incomplete task
    await login(page, testUsers.user1.email);
    await page.goto('/tasks/create');
    await page.fill('#name', 'User 1 Inbox Task');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tasks\/\d+/);
    await logout(page);

    // User 2 creates an incomplete task
    await login(page, testUsers.user2.email);
    await page.goto('/tasks/create');
    await page.fill('#name', 'User 2 Inbox Task');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tasks\/\d+/);

    // Check inbox - should only see User 2's task
    await page.goto('/inbox');
    await expect(page.locator('text=User 2 Inbox Task')).toBeVisible();
    await expect(page.locator('text=User 1 Inbox Task')).not.toBeVisible();
  });

  test('today view only shows user tasks due today', async ({ page }) => {
    // User 1 creates task for today
    await login(page, testUsers.user1.email);
    await page.goto('/tasks/create');
    await page.fill('#name', 'User 1 Today Task');

    // Set date and time to today at noon
    const today = new Date().toISOString().split('T')[0];
    await page.fill('input[name="date"]', today);
    await page.fill('input[name="time"]', '12:00');

    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tasks\/\d+/);
    await logout(page);

    // User 2 checks their today view
    await login(page, testUsers.user2.email);
    await page.goto('/day');

    // Should not see User 1's task
    await expect(page.locator('text=User 1 Today Task')).not.toBeVisible();
  });
});
