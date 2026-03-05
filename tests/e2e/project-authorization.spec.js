import { test, expect } from '@playwright/test';
import { login, logout, testUsers } from './helpers/auth.js';

/**
 * Project Authorization & Privacy Tests
 *
 * These tests verify that users can only see and interact with projects
 * they created or are assigned to, ensuring proper data privacy.
 */

test.describe('Project Authorization & Privacy', () => {
  test.beforeEach(async ({ page }) => {
    // Ensure clean state for each test
    await page.goto('/login');
  });

  test('user can only see their own projects in project list', async ({ page }) => {
    // User 1 creates a project
    await login(page, testUsers.user1.email);
    await page.goto('/projects/create');
    await page.fill('#name', 'User 1 Private Project');
    await page.fill('textarea[name="description"]', 'This project should only be visible to User 1');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/projects\/\d+/);
    await logout(page);

    // User 2 creates a project
    await login(page, testUsers.user2.email);
    await page.goto('/projects/create');
    await page.fill('#name', 'User 2 Private Project');
    await page.fill('textarea[name="description"]', 'This project should only be visible to User 2');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/projects\/\d+/);

    // Verify User 2 can see their project but not User 1's
    await page.goto('/projects');
    await expect(page.locator('text=User 2 Private Project')).toBeVisible();
    await expect(page.locator('text=User 1 Private Project')).not.toBeVisible();
    await logout(page);

    // Verify User 1 can see only their project
    await login(page, testUsers.user1.email);
    await page.goto('/projects');
    await expect(page.locator('text=User 1 Private Project')).toBeVisible();
    await expect(page.locator('text=User 2 Private Project')).not.toBeVisible();
  });

  test('user can see projects they are assigned to', async ({ page }) => {
    // User 1 creates a project and assigns it to User 2
    await login(page, testUsers.user1.email);
    await page.goto('/projects/create');
    await page.fill('#name', 'Shared Project for User 2');
    await page.fill('textarea[name="description"]', 'User 2 should see this project');

    // Assign to User 2
    await page.check(`label:has-text("${testUsers.user2.name}") input[name="assignee_ids[]"]`);

    await page.click('button[type="submit"]');
    await page.waitForURL(/\/projects\/\d+/);
    await logout(page);

    // User 2 should see the assigned project
    await login(page, testUsers.user2.email);
    await page.goto('/projects');
    await expect(page.locator('text=Shared Project for User 2')).toBeVisible();

    // User 2 should be able to view the project detail
    await page.click('text=Shared Project for User 2');
    await expect(page.locator('text=User 2 should see this project')).toBeVisible();
  });

  test('user cannot access project detail page for unauthorized project', async ({ page }) => {
    // User 1 creates a project
    await login(page, testUsers.user1.email);
    await page.goto('/projects/create');
    await page.fill('#name', 'User 1 Secret Project');
    await page.fill('textarea[name="description"]', 'User 2 should not see this project');
    await page.click('button[type="submit"]');

    // Get the project ID from the URL
    await page.waitForURL(/\/projects\/(\d+)/);
    const projectUrl = page.url();
    const projectId = projectUrl.match(/\/projects\/(\d+)/)[1];
    await logout(page);

    // User 2 tries to directly access the project URL
    await login(page, testUsers.user2.email);
    await page.goto(`/projects/${projectId}`);

    // Should be redirected or see an error (403/404)
    const currentUrl = page.url();
    const isAccessDenied =
      currentUrl !== `/projects/${projectId}` ||
      await page.locator('text=/forbidden|unauthorized|access denied|not found/i').isVisible().catch(() => false);

    expect(isAccessDenied).toBeTruthy();
  });

  test('user cannot edit projects they do not own', async ({ page }) => {
    // User 1 creates a project
    await login(page, testUsers.user1.email);
    await page.goto('/projects/create');
    await page.fill('#name', 'User 1 Project to Edit');
    await page.fill('textarea[name="description"]', 'Original description');
    await page.click('button[type="submit"]');

    await page.waitForURL(/\/projects\/(\d+)/);
    const projectUrl = page.url();
    const projectId = projectUrl.match(/\/projects\/(\d+)/)[1];
    await logout(page);

    // User 2 tries to access the edit page
    await login(page, testUsers.user2.email);
    await page.goto(`/projects/${projectId}/edit`);

    // Should be denied access
    const currentUrl = page.url();
    const isAccessDenied =
      currentUrl !== `/projects/${projectId}/edit` ||
      await page.locator('text=/forbidden|unauthorized|access denied|not found/i').isVisible().catch(() => false);

    expect(isAccessDenied).toBeTruthy();
  });

  test('project creator can add and remove assignees', async ({ page }) => {
    // User 1 creates a project
    await login(page, testUsers.user1.email);
    await page.goto('/projects/create');
    await page.fill('#name', 'Project with Assignees');
    await page.fill('textarea[name="description"]', 'Testing assignee management');
    await page.click('button[type="submit"]');

    await page.waitForURL(/\/projects\/(\d+)/);

    // Open the inline assignee editor and add User 2
    // Projects use inline editing on the show page (no separate edit route)
    // Assignees are inside a collapsed <details> — expand it first
    await page.locator('details summary').click();
    await page.locator('[x-show="!editing.assignee_ids"]').click();
    await page.waitForSelector(`label:has-text("${testUsers.user2.name}") input[type="checkbox"]`, { state: 'visible' });
    await page.check(`label:has-text("${testUsers.user2.name}") input[type="checkbox"]`);
    await page.locator('[x-show="editing.assignee_ids"] button:has-text("Save")').click();

    // saveField() reloads the page on success — re-expand details to verify and to remove
    await page.waitForLoadState('networkidle');
    await page.locator('details summary').click();
    await expect(page.locator(`text=${testUsers.user2.name}`).first()).toBeVisible();

    // Remove assignee
    await page.locator('[x-show="!editing.assignee_ids"]').click();
    await page.waitForSelector(`label:has-text("${testUsers.user2.name}") input[type="checkbox"]`, { state: 'visible' });
    await page.uncheck(`label:has-text("${testUsers.user2.name}") input[type="checkbox"]`);
    await page.locator('[x-show="editing.assignee_ids"] button:has-text("Save")').click();
    await page.waitForLoadState('networkidle');
  });

  test('assignee can view assigned project', async ({ page }) => {
    // User 1 creates and assigns project to User 3
    await login(page, testUsers.user1.email);
    await page.goto('/projects/create');
    await page.fill('#name', 'Assigned to User 3 Project');
    await page.fill('textarea[name="description"]', 'User 3 is assigned to this project');
    await page.check(`label:has-text("${testUsers.user3.name}") input[name="assignee_ids[]"]`);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/projects\/\d+/);
    await logout(page);

    // User 3 logs in and views assigned project
    await login(page, testUsers.user3.email);
    await page.goto('/projects');
    await expect(page.locator('text=Assigned to User 3 Project')).toBeVisible();

    // User 3 can view the project detail
    await page.click('text=Assigned to User 3 Project');
    await expect(page.locator('text=User 3 is assigned to this project')).toBeVisible();
  });

  test('tasks in project inherit project visibility', async ({ page }) => {
    // User 1 creates a project
    await login(page, testUsers.user1.email);
    await page.goto('/projects/create');
    await page.fill('#name', 'Project with Tasks');
    await page.fill('textarea[name="description"]', 'Testing task visibility in projects');
    await page.click('button[type="submit"]');

    await page.waitForURL(/\/projects\/(\d+)/);
    const projectUrl = page.url();
    const projectId = projectUrl.match(/\/projects\/(\d+)/)[1];

    // Create a task in this project
    await page.goto('/tasks/create');
    await page.fill('#name', 'Task in Private Project');
    await page.selectOption('select[name="project_id"]', projectId);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tasks\/\d+/);
    await logout(page);

    // User 2 should not see the task (not assigned to project)
    await login(page, testUsers.user2.email);
    await page.goto('/tasks');
    await expect(page.locator('text=Task in Private Project')).not.toBeVisible();
  });

  test('user cannot create tasks in projects they cannot access', async ({ page }) => {
    // User 1 creates a project
    await login(page, testUsers.user1.email);
    await page.goto('/projects/create');
    await page.fill('#name', 'User 1 Exclusive Project');
    await page.click('button[type="submit"]');

    await page.waitForURL(/\/projects\/(\d+)/);
    const projectUrl = page.url();
    const projectId = projectUrl.match(/\/projects\/(\d+)/)[1];
    await logout(page);

    // User 2 tries to create a task in User 1's project
    await login(page, testUsers.user2.email);
    await page.goto('/tasks/create');

    // The project should not appear in the dropdown for User 2
    const projectOptions = await page.locator(`select[name="project_id"] option`).allTextContents();
    expect(projectOptions.join()).not.toContain('User 1 Exclusive Project');
  });

  test('user cannot see other users projects in search', async ({ page }) => {
    // User 1 creates a unique project
    await login(page, testUsers.user1.email);
    await page.goto('/projects/create');
    const uniqueName = `Unique Project ${Date.now()}`;
    await page.fill('#name', uniqueName);
    await page.fill('textarea[name="description"]', 'Should not appear in User 2 search');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/projects\/\d+/);
    await logout(page);

    // User 2 tries to search for the project
    await login(page, testUsers.user2.email);
    await page.goto('/search');
    await page.fill('#search', uniqueName);
    await page.click('button[type="submit"]');

    // Project should not appear in results
    await expect(page.locator(`text=${uniqueName}`)).not.toBeVisible();
  });

  test('removing user from project hides project from their view', async ({ page }) => {
    // User 1 creates project and assigns to User 2
    await login(page, testUsers.user1.email);
    await page.goto('/projects/create');
    await page.fill('#name', 'Temporary Access Project');
    await page.check(`label:has-text("${testUsers.user2.name}") input[name="assignee_ids[]"]`);
    await page.click('button[type="submit"]');

    await page.waitForURL(/\/projects\/(\d+)/);
    const projectUrl = page.url();
    const projectId = projectUrl.match(/\/projects\/(\d+)/)[1];
    await logout(page);

    // User 2 verifies they can see the project
    await login(page, testUsers.user2.email);
    await page.goto('/projects');
    await expect(page.locator('text=Temporary Access Project')).toBeVisible();
    await logout(page);

    // User 1 removes User 2 from the project via inline editing on the show page
    await login(page, testUsers.user1.email);
    await page.goto(`/projects/${projectId}`);
    // Assignees are inside a collapsed <details> — expand it first
    await page.locator('details summary').click();
    await page.locator('[x-show="!editing.assignee_ids"]').click();
    await page.waitForSelector(`label:has-text("${testUsers.user2.name}") input[type="checkbox"]`, { state: 'visible' });
    await page.uncheck(`label:has-text("${testUsers.user2.name}") input[type="checkbox"]`);
    await page.locator('[x-show="editing.assignee_ids"] button:has-text("Save")').click();
    await page.waitForLoadState('networkidle');
    await logout(page);

    // User 2 should no longer see the project
    await login(page, testUsers.user2.email);
    await page.goto('/projects');
    await expect(page.locator('text=Temporary Access Project')).not.toBeVisible();

    // User 2 should not be able to access the project directly
    await page.goto(`/projects/${projectId}`);
    const currentUrl = page.url();
    const isAccessDenied =
      currentUrl !== `/projects/${projectId}` ||
      await page.locator('text=/forbidden|unauthorized|access denied|not found/i').isVisible().catch(() => false);

    expect(isAccessDenied).toBeTruthy();
  });
});
