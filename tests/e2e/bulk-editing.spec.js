import { test, expect } from '@playwright/test';
import { login, logout, testUsers } from './helpers/auth.js';

/**
 * Bulk Editing UI Tests
 *
 * Verifies the complete bulk-edit flow:
 *   toggle → select tasks → configure fields → confirm → apply
 *
 * Each test creates its own tasks via the quick-add bar so it is
 * independent of other specs (the global setup already seeded users).
 */

// ── helpers ─────────────────────────────────────────────────────────────────

/**
 * Use the quick-add bar to create a task on the Inbox page.
 * Returns the task name so callers can locate rows.
 */
async function quickAddTask(page, name) {
  await page.fill('textarea[name="name"]', name);
  await page.keyboard.press('Enter');
  // Wait for the row to appear in the list
  await expect(page.locator(`[data-task-name="${name.toLowerCase()}"]`)).toBeVisible({ timeout: 5000 });
  return name;
}

/**
 * Click the bulk-edit toggle (clipboard/checkmark icon) in the input bar.
 * Uses the legacy page.click() API which clicks the first matching element,
 * avoiding strict-mode issues with the two (create-mode / filter-mode) copies.
 */
async function clickBulkToggle(page) {
  await page.click('button[title="Bulk edit mode"]');
}

/** Click the "Exit bulk edit" button in the bulk-mode header. */
async function exitBulkMode(page) {
  await page.click('button[title="Exit bulk edit"]');
}

/**
 * Return the task-group wrapper div for the given task name.
 * The group is identified by the data-filterable child that holds
 * the data-task-name attribute.
 */
function taskGroupLocator(page, name) {
  return page
    .locator('[data-task-group]')
    .filter({ has: page.locator(`[data-task-name="${name.toLowerCase()}"]`) });
}

/**
 * Click the main "Apply" button in the fixed bottom bar.
 * Uses :text-is() to match the button whose full text is exactly "Apply",
 * avoiding the "Yes, apply changes Applying…" submit button which also
 * contains the word "Apply".
 */
async function clickApply(page) {
  await page.locator('.fixed.bottom-0 button:text-is("Apply")').click();
}

/**
 * Return a locator for the main "Apply" button in the fixed bottom bar.
 * (Same :text-is() technique as clickApply.)
 */
function applyButton(page) {
  return page.locator('.fixed.bottom-0 button:text-is("Apply")');
}

/**
 * Return the confirmation dialog locator.
 */
function confirmDialog(page) {
  return page.locator('.bg-gray-800.border.border-gray-700.rounded-lg.shadow-xl');
}

// ── tests ────────────────────────────────────────────────────────────────────

test.describe('Bulk Editing', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, testUsers.user1.email);
    await page.goto('/inbox');
    await page.waitForLoadState('networkidle');
  });

  test.afterEach(async ({ page }) => {
    await logout(page);
  });

  // ── toggle ────────────────────────────────────────────────────────────────

  test('bulk edit toggle button is visible in the quick-add bar', async ({ page }) => {
    // Two copies exist in DOM (one per input-bar mode); at least the first is visible.
    await expect(page.locator('button[title="Bulk edit mode"]').first()).toBeVisible();
  });

  test('clicking the toggle enters bulk edit mode', async ({ page }) => {
    await clickBulkToggle(page);

    // The bulk-mode header span has exact text "Bulk edit"
    await expect(page.getByText('Bulk edit', { exact: true })).toBeVisible();
    // The create-task form is hidden
    await expect(page.locator('textarea[name="name"]')).not.toBeVisible();
  });

  test('clicking the toggle again exits bulk edit mode', async ({ page }) => {
    await clickBulkToggle(page);
    await exitBulkMode(page);

    // Back to create mode
    await expect(page.locator('textarea[name="name"]')).toBeVisible();
    // Exact match avoids hitting the hidden "Confirm bulk edit" heading
    await expect(page.getByText('Bulk edit', { exact: true })).not.toBeVisible();
  });

  // ── selection ─────────────────────────────────────────────────────────────

  test('task circles become squares in bulk mode', async ({ page }) => {
    const name = `BulkCircle-${Date.now()}`;
    await quickAddTask(page, name);

    await clickBulkToggle(page);

    const group = taskGroupLocator(page, name);

    // Square selector button is visible
    await expect(group.locator('button[title="Select task"]')).toBeVisible();
  });

  test('clicking the square selects the task and shows a ring', async ({ page }) => {
    const name = `BulkSelect-${Date.now()}`;
    await quickAddTask(page, name);

    await clickBulkToggle(page);

    const group = taskGroupLocator(page, name);
    await group.locator('button[title="Select task"]').click();

    // Ring class applied to the card
    await expect(group.locator('[data-filterable]')).toHaveClass(/ring-2/);

    // Counter in the bulk header — use .first() because the same count
    // text also appears in the (currently hidden) bottom bar.
    await expect(page.locator('text=1 task selected').first()).toBeVisible();
  });

  test('clicking a selected task deselects it', async ({ page }) => {
    const name = `BulkDeselect-${Date.now()}`;
    await quickAddTask(page, name);

    await clickBulkToggle(page);

    const group   = taskGroupLocator(page, name);
    const square  = group.locator('button[title="Select task"]');

    await square.click(); // select
    await square.click(); // deselect

    await expect(group.locator('[data-filterable]')).not.toHaveClass(/ring-2/);
    await expect(page.locator('text=0 tasks selected').first()).toBeVisible();
  });

  test('clicking the task row also toggles selection in bulk mode', async ({ page }) => {
    const name = `BulkRowClick-${Date.now()}`;
    await quickAddTask(page, name);

    await clickBulkToggle(page);

    const group = taskGroupLocator(page, name);
    // Click the task name area (the flex-1 content div)
    await group.locator('[data-task-name-display]').click();

    await expect(group.locator('[data-filterable]')).toHaveClass(/ring-2/);
  });

  test('"Select all visible" selects every visible task', async ({ page }) => {
    const nameA = `BulkAll-A-${Date.now()}`;
    const nameB = `BulkAll-B-${Date.now()}`;
    await quickAddTask(page, nameA);
    await quickAddTask(page, nameB);

    await clickBulkToggle(page);

    // Click "Select all visible" in the bulk header
    await page.click('button:has-text("Select all visible")');

    // Both task cards should now have the selection ring
    await expect(taskGroupLocator(page, nameA).locator('[data-filterable]')).toHaveClass(/ring-2/);
    await expect(taskGroupLocator(page, nameB).locator('[data-filterable]')).toHaveClass(/ring-2/);
  });

  test('"Deselect all" clears all selections', async ({ page }) => {
    const nameA = `BulkDeselAll-A-${Date.now()}`;
    const nameB = `BulkDeselAll-B-${Date.now()}`;
    await quickAddTask(page, nameA);
    await quickAddTask(page, nameB);

    await clickBulkToggle(page);
    await page.click('button:has-text("Select all visible")');

    // Both selected; now deselect all
    await page.click('button:has-text("Deselect all")');

    await expect(taskGroupLocator(page, nameA).locator('[data-filterable]')).not.toHaveClass(/ring-2/);
    await expect(taskGroupLocator(page, nameB).locator('[data-filterable]')).not.toHaveClass(/ring-2/);
  });

  test('exiting bulk mode clears selections', async ({ page }) => {
    const name = `BulkExit-${Date.now()}`;
    await quickAddTask(page, name);

    await clickBulkToggle(page);
    await taskGroupLocator(page, name).locator('button[title="Select task"]').click();

    await exitBulkMode(page);
    await clickBulkToggle(page); // re-enter

    // Task should not be selected (ring gone)
    await expect(taskGroupLocator(page, name).locator('[data-filterable]')).not.toHaveClass(/ring-2/);
  });

  // ── bottom bar ────────────────────────────────────────────────────────────

  test('bottom bar is hidden when no tasks are selected', async ({ page }) => {
    const name = `BulkBar-${Date.now()}`;
    await quickAddTask(page, name);

    await clickBulkToggle(page);

    // The status select inside the fixed bar should not be visible yet
    await expect(page.locator('.fixed.bottom-0 select:text-is("— no change —")').first()).not.toBeVisible();
  });

  test('bottom bar appears once a task is selected', async ({ page }) => {
    const name = `BulkBarVisible-${Date.now()}`;
    await quickAddTask(page, name);

    await clickBulkToggle(page);
    await taskGroupLocator(page, name).locator('button[title="Select task"]').click();

    // Any select inside the fixed bar should now be visible
    await expect(page.locator('.fixed.bottom-0 select').first()).toBeVisible();
  });

  test('Apply button is disabled until a field is changed', async ({ page }) => {
    const name = `BulkApply-${Date.now()}`;
    await quickAddTask(page, name);

    await clickBulkToggle(page);
    await taskGroupLocator(page, name).locator('button[title="Select task"]').click();

    // :text-is("Apply") matches only the main Apply button, not the submit button
    await expect(applyButton(page)).toHaveClass(/cursor-not-allowed/);
    await expect(applyButton(page)).toBeDisabled();
  });

  test('Apply button becomes enabled after selecting a status', async ({ page }) => {
    const name = `BulkApplyEnabled-${Date.now()}`;
    await quickAddTask(page, name);

    await clickBulkToggle(page);
    await taskGroupLocator(page, name).locator('button[title="Select task"]').click();

    await page.locator('.fixed.bottom-0 select[x-model="status"]').selectOption('archived');

    await expect(applyButton(page)).not.toBeDisabled();
  });

  // ── confirmation dialog ───────────────────────────────────────────────────

  test('Apply opens a confirmation dialog describing the changes', async ({ page }) => {
    const name = `BulkConfirm-${Date.now()}`;
    await quickAddTask(page, name);

    await clickBulkToggle(page);
    await taskGroupLocator(page, name).locator('button[title="Select task"]').click();

    await page.locator('.fixed.bottom-0 select[x-model="status"]').selectOption('done');
    await clickApply(page);

    const dialog = confirmDialog(page);
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('p')).toContainText('status');
    await expect(dialog.locator('p')).toContainText('1 task');

    // Dismiss dialog so the afterEach logout isn't blocked by the backdrop.
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
  });

  test('confirmation dialog mentions all changed fields', async ({ page }) => {
    const name = `BulkConfirmFields-${Date.now()}`;
    await quickAddTask(page, name);

    await clickBulkToggle(page);
    await taskGroupLocator(page, name).locator('button[title="Select task"]').click();

    await page.locator('.fixed.bottom-0 input[type="date"]').fill('2026-09-01');
    await page.locator('.fixed.bottom-0 select[x-model="status"]').selectOption('archived');
    await clickApply(page);

    const message = await confirmDialog(page).locator('p').innerText();
    expect(message).toContain('due date');
    expect(message).toContain('status');

    // Dismiss dialog so the afterEach logout isn't blocked by the backdrop.
    await page.keyboard.press('Escape');
    await expect(confirmDialog(page)).not.toBeVisible();
  });

  test('Cancel closes the confirmation dialog without making changes', async ({ page }) => {
    const name = `BulkCancel-${Date.now()}`;
    await quickAddTask(page, name);

    await clickBulkToggle(page);
    await taskGroupLocator(page, name).locator('button[title="Select task"]').click();

    await page.locator('.fixed.bottom-0 select[x-model="status"]').selectOption('done');
    await clickApply(page);

    const dialog = confirmDialog(page);
    await expect(dialog).toBeVisible();

    await dialog.locator('button:has-text("Cancel")').click();

    // Dialog gone; task row still visible (not marked done / removed from list)
    await expect(dialog).not.toBeVisible();
    await expect(taskGroupLocator(page, name)).toBeVisible();
  });

  // ── end-to-end apply ──────────────────────────────────────────────────────

  test('confirming Apply archives the selected task and reloads the page', async ({ page }) => {
    const name = `BulkApplyE2E-${Date.now()}`;
    await quickAddTask(page, name);

    await clickBulkToggle(page);
    await taskGroupLocator(page, name).locator('button[title="Select task"]').click();

    await page.locator('.fixed.bottom-0 select[x-model="status"]').selectOption('archived');
    await clickApply(page);

    await confirmDialog(page).locator('button:has-text("Yes, apply changes")').click();

    // Page reloads; archived tasks are filtered from Inbox view by default
    await page.waitForLoadState('networkidle');
    await expect(taskGroupLocator(page, name)).not.toBeVisible();
  });

  test('after a successful apply, bulk mode is exited', async ({ page }) => {
    const name = `BulkExitAfterApply-${Date.now()}`;
    await quickAddTask(page, name);

    await clickBulkToggle(page);
    await taskGroupLocator(page, name).locator('button[title="Select task"]').click();

    await page.locator('.fixed.bottom-0 select[x-model="status"]').selectOption('archived');
    await clickApply(page);

    await confirmDialog(page).locator('button:has-text("Yes, apply changes")').click();

    await page.waitForLoadState('networkidle');

    // Create-task input should be visible again (bulk mode off)
    await expect(page.locator('textarea[name="name"]')).toBeVisible();
  });
});
