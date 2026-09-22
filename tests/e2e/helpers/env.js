import { readFileSync, existsSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const projectRoot = resolve(__dirname, '../../..');

function readEnvKey(filename, key) {
  try {
    const contents = readFileSync(resolve(projectRoot, filename), 'utf8');
    const match = contents.match(new RegExp(`^${key}=(.*)$`, 'm'));
    return match ? match[1].trim() : null;
  } catch {
    return null;
  }
}

/**
 * Domain used for every user the E2E suite creates. Mirrors Laravel's own
 * environment-file selection: when .env.testing exists, it *replaces* .env
 * entirely (Laravel never merges the two) — so TEST_USER_DOMAIN is read from
 * .env.testing alone in that case, falling straight to the code default if
 * that file doesn't set it. .env.testing is optional; when it's absent,
 * plain .env is what `--env=testing` actually reads, so we read from there
 * instead. Falls back to config/taskfiend.php's own default if neither
 * applicable file sets it.
 */
export function testUserDomain() {
  const envFile = existsSync(resolve(projectRoot, '.env.testing')) ? '.env.testing' : '.env';

  return readEnvKey(envFile, 'TEST_USER_DOMAIN') ?? 'example.com';
}
