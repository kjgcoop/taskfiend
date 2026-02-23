/**
 * Authentication helper utilities for E2E tests
 */

/**
 * Log in as a specific user
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @param {string} email - User email
 * @param {string} password - User password
 */
export async function login(page, email, password = 'password123') {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');

  // Wait for navigation to complete (redirects to / which is the dashboard)
  await page.waitForURL(url => {
    const path = new URL(url).pathname;
    return path === '/' || path.startsWith('/today') || path.startsWith('/day') || path.startsWith('/dashboard') || path.startsWith('/tasks');
  });
}

/**
 * Log out the current user
 * @param {import('@playwright/test').Page} page - Playwright page object
 */
export async function logout(page) {
  // Ensure any in-flight navigation (e.g. after a form submit) has settled
  // before we try to interact with the nav bar.
  await page.waitForLoadState('domcontentloaded');

  // Click on user dropdown (avatar button in top-right nav)
  await page.click('[data-testid="user-menu"]');

  // The page has two "Log Out" links: one in the desktop dropdown and one in the
  // mobile responsive menu.  Use .first() so Playwright targets the desktop
  // dropdown entry unambiguously instead of warning about multiple matches.
  const logoutLink = page.locator('text=Log Out').first();
  await logoutLink.waitFor({ state: 'visible' });
  await logoutLink.click();

  // Wait for redirect to home/login
  await page.waitForURL(/\/login|\/$/);
}

/**
 * Set up authenticated session for a user
 * Reuses storage state if available
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @param {string} email - User email
 * @param {string} password - User password
 */
export async function setupAuthenticatedSession(page, email, password = 'password123') {
  await login(page, email, password);

  // Verify we're logged in by checking for dashboard or tasks
  await page.waitForSelector('nav', { timeout: 5000 });
}

/**
 * Test credentials
 */
export const testUsers = {
  user1: {
    email: 'user1@test.com',
    password: 'password123',
    name: 'User One'
  },
  user2: {
    email: 'user2@test.com',
    password: 'password123',
    name: 'User Two'
  },
  user3: {
    email: 'user3@test.com',
    password: 'password123',
    name: 'User Three'
  }
};
