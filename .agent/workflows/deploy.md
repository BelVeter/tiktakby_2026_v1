---
description: Safe deployment workflow with conflict checking, asset verification, and production safety checks.
---

# Deployment Workflow

Follow these steps to ensure a safe and successful deployment to production.

## 1. Context Synchronization & Conflict Check
**Goal**: Simulate the merge locally to ensure your PR will be clean and conflict-free.
*Since deployment happens via PR, you must ensure your code is compatible with `main` **BEFORE** you push.*

1.  **Fetch latest state**:
    ```bash
    git fetch origin
    ```
2.  **Merge `origin/main` into current branch**:
    ```bash
    git merge origin/main
    ```
    -   **If successful**: Proceed to the next step.
    -   **If CONFLICTS**:
        -   **STOP**. Do not proceed until conflicts are resolved.
        -   **User Action**: Resolve conflicts manually in the affected files.
        -   **Agent Advice**: Explain which files are conflicting. If the conflict is in logic (e.g., same lines modified), ask the user for the correct version. If it's a generated file (`mix-manifest.json`), usually accept the local version or rebuild.
        -   **Consequences**: Merging with unresolved conflicts will break the build or the site. Forcing a merge without understanding the conflict can overwrite critical production fixes.

## 2. Pre-Deployment Verification
**Goal**: Catch common issues that break production.

1.  **Check for Route Closures**:
    -   Run a check to ensure no closures exist in `routes/web.php` (they break `php artisan route:cache`).
    -   `grep "function (" routes/web.php` (should be empty or commented out).
2.  **Verify Assets**:
    -   Ensure `public/css/app.css`, `public/js/app.js`, and `mix-manifest.json` are modified and committed if you changed SCSS/JS.
    -   **Agent Action**: If frontend files changed but assets didn't, remind the user to run `npm run prod`.
3.  **Check `AGENTS.md`**:
    -   If architecture changed (new controllers, middleware, tables), ensure `AGENTS.md` was updated.

## 3. Deployment Triggers (The "Go Live" Moment)
**Goal**: Push your verified code, merge it, and tell the server to pull.

1.  **Push & PR**:
    -   Push your local branch: `git push origin <current-branch>`.
    -   Create a Pull Request to `main` on GitHub.
    -   **Wait** for checks (if any) and peer review.
2.  **Merge**:
    -   Merge the PR into `main`.
    -   *Now the code is on the remote server's git, but not live yet.*
3.  **Automated Deploy**:
    -   **GitHub Webhook** automatically triggers `https://tiktak.by/Deploy.php` after the merge.
    -   *No manual action required.*
4.  **Confirm**:
    -   Wait a moment for the webhook to finish.
4.  **Post-Deploy Verification (Smoke Test)**:
    -   **Critical**: Visit the home page and a few inner pages to ensure no 500 errors.
    -   **Feature Check**: Verify specifically the functionality you just added or modified.
    -   **Assets**: Ctrl+F5 to clear browser cache and ensure styles/scripts are loaded correctly (not 404).

## 4. Conflict Prevention Tips
-   **Pull Often**: Before starting new work, always run `git checkout main && git pull && git checkout -b feature/new-task` to start fresh.
-   **Communicate**: If touching `routes/web.php` or `webpack.mix.js`, check if others are working on them.
-   **Resolve Locally**: Never push a branch that has conflicts with `main`. Resolve them locally first.

---
**Agent Note**: If any step fails, stop and ask the user for guidance. Never force-push or skip verification steps without explicit approval.
