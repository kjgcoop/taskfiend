import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const projectRoot = resolve(__dirname, '../../..');

/**
 * Domain used for every user the E2E suite creates, read from .env.testing
 * (the same file `php artisan ... --env=testing` reads TEST_USER_DOMAIN
 * from) so JS and PHP never drift. Falls back to config/taskfiend.php's own
 * default if the key is absent from .env.testing.
 */
export function testUserDomain() {
  try {
    const contents = readFileSync(resolve(projectRoot, '.env.testing'), 'utf8');
    const match = contents.match(/^TEST_USER_DOMAIN=(.*)$/m);
    if (match) {
      return match[1].trim();
    }
  } catch {
    // .env.testing missing — fall through to the default below.
  }

  return 'example.com';
}
