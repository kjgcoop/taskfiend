The project root is /home/claude/shared-claude/taskfiend.

## Instructions

1. Study `spec.md` thoroughly. Understand the full architecture and conventions.
2. Study `implementation-plan.md` thoroughly. Understand all tasks and their status.
3. Find the **first** unchecked (`[ ]`) task in `implementation-plan.md`. Work on **only that task** this session — do not start any other task, even if time/context remains. Tasks are strictly sequential and later tasks depend on earlier ones, so never skip ahead.
4. **Red phase:** Write a test for the task. Run it. **Confirm it fails.** If the test already passes, your test isn't exercising new behaviour. Rewrite it until it genuinely fails.
5. **Green phase:** Implement the task until the failing test passes.
6. Run the **full test suite** (`npm test`, which runs both PHPUnit and Playwright) to confirm nothing else broke. If something is broken, fix it and rerun the full suite until it passes.
7. If everything passes, update `implementation-plan.md` to mark the task as `[x]`.
8. If all tasks are checked, create a file called `DONE` in the project root.

## Project Context

- Language: PHP (Laravel) and Javascript (Alpine.js)
- Test framework: PHPUnit (unit/feature tests) and Playwright (browser/e2e tests). `npm test` runs both. For the red/green cycle in step 4/5, write a PHPUnit test for backend-only behaviour (migrations, models, business logic) and a Playwright test for anything the user directly sees or clicks (buttons, indicators, redirects).
- Key conventions: reuse code where possible
- Resources available:
    - CLAUDE.md
    - The markdown files in /docs
    - Rendered copy of the files in /docs: https://taskfiend.online/docs/

## Rules

- Only work on ONE task per session — the first unchecked one, in strict order. Never skip ahead to a later task, even if the current one is failing.
- Do NOT modify the spec unless something is genuinely wrong (document why).
- Keep changes focused and minimal.
- **Use red/green TDD.** Always write the test first, confirm it fails (red), then implement until it passes (green). Never mark a task done on a test that was already passing.
- **Run the full test suite** after implementation to catch regressions before marking a task done.
- If your test still fails after two implementation attempts, or if your implementation breaks existing tests that you can't fix, use `git checkout` to revert your changes to the affected files. Do NOT mark the task as complete — leave it unchecked for the next iteration. Add a single-line note below the task describing what failed, prefixed with `  ⚠️`. Example:
  ```
  - [ ] Implement user registration endpoint
    ⚠️ bcrypt import failed — may need native dependency installed
  ```
- If a task already has 3 failure notes, do **not** skip to the next task — later tasks depend on this one. Instead, write `STUCK.md` in the project root summarizing what was tried and why it failed, and stop making further changes this session.
- You can use `git diff`, `git status`, and `git log` to understand the current state of the codebase.
- You do NOT have permission to `git commit`. The outer script handles commits.
