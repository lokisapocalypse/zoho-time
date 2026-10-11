# zoho-time

Log time from GitHub issues to Zoho Invoice timesheets, from the command line or from [Claude Code](https://claude.com/claude-code).

I bill clients through Zoho Invoice and track the work as GitHub issues. Copying hours from one to the other was tedious and easy to get wrong, so this is a small PHP CLI, plus a Claude Code skill, that turns "I spent an hour on issue 39" into a time entry on the right Zoho project and task.

What it does:

- **Only bills client work.** A repo is billable if it's listed in the config or has one of the configured topics (e.g. `client-bluebird`). Anything else is refused with exit code 3.
- **One Zoho project per repo, one task per issue.** The task is named after the issue's title and created the first time time is logged to it. `--task` puts the time on an existing task instead (a retainer, say), and never creates one.
- **Keeps invoices clean.** Clients see task names and notes, so nothing sent to Zoho includes the repo name, issue number or GitHub link. Notes default to the issue's title, and `--notes` sets friendlier wording.
- **Previews before saving.** Nothing is written without `--confirm`. The Claude Code skill shows you the preview and only saves after you say yes.
- **Rounds up to the quarter hour** (1h 05m → 1.25h, 40m → 0.75h), and refuses dates in the future.

## Usage

```
zoho-time log bluebird-www#39 1h05m [--date=2026-10-01]            # preview
zoho-time log bluebird-www#39 1h05m [--date=2026-10-01] --confirm  # save
zoho-time log bluebird-www#39 30m --task="Monthly maintenance"      # on an existing task
zoho-time log bluebird-www#39 1h --notes="Set up nightly backups"   # client-facing notes
zoho-time tasks bluebird-www
zoho-time entries [--date=2026-10-01]
zoho-time delete <entry-id> [--confirm]
zoho-time projects
zoho-time auth
```

`--task` takes an existing task's name (ignoring case) or its ID, as listed by `zoho-time tasks <repo>`. `<time>` can be `1h05m`, `40m`, `1.25h`, `1.5` (hours) or `1:05`. An issue in another owner's repo is written `owner/repo#n`.

Exit codes: 0 ok, 1 error, 2 bad arguments, 3 the repo isn't billable.

## Setup

Requires PHP 8.3+ with curl, Composer, and an authenticated `gh`.

1. **Install:**
   ```
   composer install
   ln -s "$PWD/bin/zoho-time" /usr/local/bin/zoho-time
   ln -s "$PWD/skill/zoho-time" ~/.claude/skills/zoho-time
   ```
2. **Config:** copy `config.example.json` to `~/.config/zoho-time/config.json` (or point `ZOHO_TIME_CONFIG` at another path) and set:
   - `data_center`: your Zoho domain: `com`, `eu`, `in`, `com.au`, `jp` or `ca`.
   - `organization_id`: in Zoho Invoice under Settings → Organization Profile.
   - `github_owner`: the GitHub user or organization for short references like `bluebird-www#39`.
   - `billable_repositories` and `billable_topics`: which repos are billable, by name or by topic.
   - `projects`: only for repos whose Zoho project isn't named exactly like the repo (matched case-insensitively), e.g. `{"harbor-clinic": "Harbor Family Clinic"}`.

   The config holds no secrets.
3. **Zoho client:** at https://api-console.zoho.com add a **Self Client**. Under *Generate Code*, enter the scopes
   ```
   ZohoInvoice.projects.CREATE,ZohoInvoice.projects.READ,ZohoInvoice.projects.DELETE
   ```
   (DELETE is only for `zoho-time delete`), pick a duration, and copy the grant code.
4. **Credentials:** run `zoho-time auth` in your own terminal and enter the client ID, client secret and grant code. Each Zoho project needs you as a user, so the entries can be logged as you.
5. **Check:** `zoho-time projects` should list your projects.

## Credentials

The client ID, client secret, refresh token and the cached access token are stored in the macOS login Keychain under the service `zoho-time` (see them in Keychain Access, or `security find-generic-password -s zoho-time -a client_id`). An environment variable such as `ZOHO_TIME_CLIENT_SECRET` overrides the Keychain entry of the same name.

Access tokens last an hour and are refreshed automatically. The refresh token doesn't expire, but it stops working if it's revoked in the API Console, or if the Self Client is deleted or its secret regenerated. To renew, generate a new grant code (step 3) and run `zoho-time auth` again.

## Development

```
composer test   # PHPUnit
composer lint   # Pint, PSR-12
```

CI (`.github/workflows/ci.yml`) runs both on every pull request and on pushes to `development`. The tests use fakes, so they need no credentials or network access.

### Design

The code is layered, with the business rules on the domain objects rather than in services:

- **`src/Domain`:** `Duration` parses and rounds time, `BillingPolicy` decides what's billable, `Task::forIssue()` names a task, and `TimeEntry` refuses future dates and supplies default notes. The repository interfaces live here too.
- **`src/Application`:** `TimeEntryService` runs a use case: it loads the issue, project and task through repositories, asks the domain objects to build the entry, and saves it only when confirmed. It holds no rules of its own.
- **`src/Infrastructure`:** the Zoho API client and OAuth token refresh, GitHub via the `gh` CLI, and the macOS Keychain. Each sits behind an interface, so the tests swap in fakes (`tests/Fakes`).

## License

MIT; see [LICENSE](LICENSE).
