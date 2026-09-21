import { exec } from 'child_process';
import { promisify } from 'util';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';
import { testUserDomain } from './env.js';

const execAsync = promisify(exec);

// Get the project root directory (2 levels up from tests/e2e/helpers/)
const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const projectRoot = resolve(__dirname, '../../..');

/**
 * Execute command in project root directory
 */
async function runCommand(command) {
  try {
    const { stdout, stderr } = await execAsync(command, { cwd: projectRoot });
    if (stderr && !stderr.includes('INFO')) {
      console.error('Command stderr:', stderr);
    }
    return stdout;
  } catch (error) {
    console.error('Command failed:', error.message);
    if (error.stdout) console.log('stdout:', error.stdout);
    if (error.stderr) console.error('stderr:', error.stderr);
    throw error;
  }
}

/**
 * Database helper utilities for E2E tests
 */

/**
 * Reset and migrate the database
 */
export async function resetDatabase() {
  await runCommand('php artisan migrate:fresh --force --env=testing');
}

/**
 * Seed database with test data
 */
export async function seedTestData() {
  const domain = testUserDomain();

  // Create test users
  await runCommand(`php artisan user:create user1@${domain} "User One" password123 --env=testing`);
  await runCommand(`php artisan user:create user2@${domain} "User Two" password123 --env=testing`);
  await runCommand(`php artisan user:create user3@${domain} "User Three" password123 --env=testing`);
}

/**
 * Clean up database after tests
 */
export async function cleanDatabase() {
  await runCommand('php artisan migrate:fresh --force --env=testing');
}

/**
 * Execute raw artisan command
 * @param {string} command - The artisan command (without 'php artisan')
 */
export async function artisan(command) {
  return await runCommand(`php artisan ${command} --env=testing`);
}
