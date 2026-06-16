import { test, expect } from '@playwright/test';
import { login, logout, testUsers } from './helpers/auth.js';

/**
 * Tag Visibility Tests
 *
 * These tests verify that tags are globally accessible to all users.
 * Unlike tasks and projects, tags should be visible and manageable by any user.
 */

test.describe('Tag Visibility & Global Access', () => {
  test.beforeEach(async ({ page }) => {
    // Ensure clean state for each test
    await page.goto('/login');
  });

  test('tags created by one user are visible to all users', async ({ page }) => {
    // User 1 creates a tag
    await login(page, testUsers.user1.email);
    await page.goto('/tags/create');
    const uniqueTagName = `Global Tag ${Date.now()}`;
    await page.fill('#tag_name', uniqueTagName);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tags\/\d+/);
    await logout(page);

    // User 2 should see the tag
    await login(page, testUsers.user2.email);
    await page.goto('/tags');
    await expect(page.locator('main').locator(`text=${uniqueTagName}`)).toBeVisible();
    await logout(page);

    // User 3 should also see the tag
    await login(page, testUsers.user3.email);
    await page.goto('/tags');
    await expect(page.locator('main').locator(`text=${uniqueTagName}`)).toBeVisible();
  });

  test('all users can view tag details', async ({ page }) => {
    // User 1 creates a tag
    await login(page, testUsers.user1.email);
    await page.goto('/tags/create');
    await page.fill('#tag_name', 'Viewable Tag');
    await page.click('button[type="submit"]');

    await page.waitForURL(/\/tags\/(\d+)/);
    const tagUrl = page.url();
    const tagId = tagUrl.match(/\/tags\/(\d+)/)[1];
    await logout(page);

    // User 2 can view the tag detail
    await login(page, testUsers.user2.email);
    await page.goto(`/tags/${tagId}`);
    await page.waitForLoadState('networkidle');
    // Verify the page is accessible (task input bar always present on tag show page)
    await expect(page.locator('textarea[name="name"]')).toBeVisible();
  });

  test('all users can edit any tag', async ({ page }) => {
    // User 1 creates a tag
    await login(page, testUsers.user1.email);
    await page.goto('/tags/create');
    await page.fill('#tag_name', 'Editable Tag');
    await page.click('button[type="submit"]');

    await page.waitForURL(/\/tags\/(\d+)/);
    const tagUrl = page.url();
    const tagId = tagUrl.match(/\/tags\/(\d+)/)[1];
    await logout(page);

    // User 2 edits the tag inline on the show page
    await login(page, testUsers.user2.email);
    await page.goto(`/tags/${tagId}`);

    // Click the h2 tag name in the header to start inline editing
    await page.locator('h2[x-text="name"]').click();

    // Fill new name and save
    const input = page.locator('input[x-show="editing"]');
    await input.fill('Editable Tag Updated');
    await input.press('Enter');

    // Wait for page reload after save
    await page.waitForLoadState('networkidle');
    // Tag name is in the header (inline-editable h2 via Alpine.js x-text)
    await expect(page.locator('h2[x-text="name"]')).toContainText('Editable Tag Updated');
  });

  test('users can use tags created by other users on their tasks', async ({ page }) => {
    // User 1 creates a tag
    await login(page, testUsers.user1.email);
    await page.goto('/tags/create');
    const sharedTagName = `Shared Tag ${Date.now()}`;
    await page.fill('#tag_name', sharedTagName);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tags\/\d+/);
    await logout(page);

    // User 2 creates a task and applies User 1's tag
    await login(page, testUsers.user2.email);
    await page.goto('/tasks/create');
    await page.fill('#name', 'Task with Shared Tag');

    // Apply the tag created by User 1 (tags are checkboxes)
    await page.check(`label:has-text("${sharedTagName}") input[name="tag_ids[]"]`);
    await page.click('button[type="submit"]');

    // Verify tag is applied
    await page.waitForURL(/\/tasks\/\d+/);
    await expect(page.locator('main').locator(`text=${sharedTagName}`).first()).toBeVisible();
  });

  test('tag list shows tags from all users', async ({ page }) => {
    // User 1 creates a tag
    await login(page, testUsers.user1.email);
    await page.goto('/tags/create');
    await page.fill('#tag_name', 'User 1 Tag');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tags\/\d+/);
    await logout(page);

    // User 2 creates a tag
    await login(page, testUsers.user2.email);
    await page.goto('/tags/create');
    await page.fill('#tag_name', 'User 2 Tag');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tags\/\d+/);

    // User 2 should see both tags
    await page.goto('/tags');
    await expect(page.locator('main').locator('text=User 1 Tag')).toBeVisible();
    await expect(page.locator('main').locator('text=User 2 Tag')).toBeVisible();
    await logout(page);

    // User 3 should also see both tags
    await login(page, testUsers.user3.email);
    await page.goto('/tags');
    await expect(page.locator('main').locator('text=User 1 Tag')).toBeVisible();
    await expect(page.locator('main').locator('text=User 2 Tag')).toBeVisible();
  });

  test('tags appear in search for all users', async ({ page }) => {
    // User 1 creates a searchable tag
    await login(page, testUsers.user1.email);
    await page.goto('/tags/create');
    const searchableTag = `Searchable ${Date.now()}`;
    await page.fill('#tag_name', searchableTag);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tags\/\d+/);
    await logout(page);

    // User 2 visits search page — tag filter panel is expanded by default
    await login(page, testUsers.user2.email);
    await page.goto('/search');
    await page.waitForLoadState('networkidle');

    // Tag should appear in the filter panel (tags are global)
    await expect(page.locator('main').locator(`text=${searchableTag}`).first()).toBeVisible();
  });

  test('deleting a tag affects all users (global impact)', async ({ page }) => {
    // User 1 creates a tag
    await login(page, testUsers.user1.email);
    await page.goto('/tags/create');
    const deletableTag = `Deletable ${Date.now()}`;
    await page.fill('#tag_name', deletableTag);
    await page.click('button[type="submit"]');

    await page.waitForURL(/\/tags\/(\d+)/);
    const tagUrl = page.url();
    const tagId = tagUrl.match(/\/tags\/(\d+)/)[1];
    await logout(page);

    // User 2 verifies tag exists
    await login(page, testUsers.user2.email);
    await page.goto('/tags');
    await expect(page.locator('main').locator(`text=${deletableTag}`)).toBeVisible();
    await logout(page);

    // User 3 deletes the tag via the Details modal on the show page.
    await login(page, testUsers.user3.email);
    await page.goto(`/tags/${tagId}`);
    await page.getByTitle('More options').click();
    await page.locator('button:has-text("Details")').click();
    await page.waitForSelector('button:has-text("Delete Tag")', { state: 'visible' });
    await page.evaluate(() => { window.confirm = () => true; });
    await Promise.all([
      page.waitForURL(/\/tags$/),
      page.locator('button:has-text("Delete Tag")').click(),
    ]);
    await page.waitForLoadState('networkidle');

    // User 2 should no longer see the tag
    await logout(page);
    await login(page, testUsers.user2.email);
    await page.goto('/tags');
    await expect(page.locator('main').locator(`text=${deletableTag}`)).not.toBeVisible();
  });

  test('tag changes made by one user are visible to all users', async ({ page }) => {
    // User 1 creates a tag
    await login(page, testUsers.user1.email);
    await page.goto('/tags/create');
    await page.fill('#tag_name', 'Mutable Tag');
    await page.click('button[type="submit"]');

    await page.waitForURL(/\/tags\/(\d+)/);
    const tagUrl = page.url();
    const tagId = tagUrl.match(/\/tags\/(\d+)/)[1];
    await logout(page);

    // User 2 edits the tag name inline on the show page
    await login(page, testUsers.user2.email);
    await page.goto(`/tags/${tagId}`);

    await page.locator('h2[x-text="name"]').click();
    const input = page.locator('input[x-show="editing"]');
    await input.fill('Mutable Tag Modified');
    await input.press('Enter');
    await page.waitForLoadState('networkidle');
    await logout(page);

    // User 1 should see the changes made by User 2
    await login(page, testUsers.user1.email);
    await page.goto(`/tags/${tagId}`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h2[x-text="name"]')).toContainText('Mutable Tag Modified');
  });

  test('but users still cannot see tasks tagged with tags if not authorized', async ({ page }) => {
    // User 1 creates a tag
    await login(page, testUsers.user1.email);
    await page.goto('/tags/create');
    const tagName = `Privacy Test Tag ${Date.now()}`;
    await page.fill('#tag_name', tagName);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tags\/\d+/);

    // User 1 creates a private task with this tag
    await page.goto('/tasks/create');
    await page.fill('#name', 'Private Task with Tag');
    await page.check(`label:has-text("${tagName}") input[name="tag_ids[]"]`);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/tasks\/\d+/);
    await logout(page);

    // User 2 can see the tag exists
    await login(page, testUsers.user2.email);
    await page.goto('/tags');
    await expect(page.locator('main').locator(`text=${tagName}`)).toBeVisible();

    // But User 2 should NOT see User 1's private task
    await page.goto('/all-tasks');
    await expect(page.locator('main').locator('text=Private Task with Tag')).not.toBeVisible();

    // And searching for the tag should not reveal User 1's task to User 2
    await page.goto('/search');
    await page.fill('#search', tagName);
    await page.click('button[type="submit"]');

    // User 2 might see the tag itself in results, but not User 1's private task
    await expect(page.locator('main').locator('text=Private Task with Tag')).not.toBeVisible();
  });
});
