---
name: zoho-time
description: Log billable time on a GitHub issue to Zoho Invoice timesheets. Use when work on an issue in a billable client repo is done or the session ends, or when asked to log time, e.g. "log 3h on bluebird-www #39 today".
---

# Log time to Zoho Invoice

The `zoho-time` CLI creates a billable time entry on the Zoho project for the issue's repository, on a task named by the issue's title (e.g. `Nightly database backups`), creating the task the first time. Without `--confirm` it only previews; nothing reaches Zoho.

**Clients see task names and notes on their invoices.** Nothing sent to Zoho carries the repo name, issue number or GitHub link; those appear only in the preview, for the user.

## When

- Which repos are billable is set in the CLI's config (`billable_repositories` and `billable_topics` in `~/.config/zoho-time/config.json`), and may also be spelled out in the user's own instructions. When an issue in a billable repo is done or the session ends, propose an entry.
- If you're not sure whether a repo is billable, run the preview: the CLI exits with code 3 ("isn't billable") for any other repo. Then say so and stop; don't propose an entry.

## Steps

1. **Estimate the time worked** from what actually happened: the session's length, `git log --since=<session start> --format='%ad %s' --date=iso` in the repo, and the issue's timestamps (`gh issue view <n> --json createdAt,closedAt,comments`). Pass the unrounded estimate (e.g. `1h05m`); the CLI rounds up to the quarter hour.
2. **Pick the task.** Run `zoho-time tasks <repo>` to see the project's existing tasks. By default the time goes on the issue's own task (created if new). If an existing task looks like a better fit, suggest it and let the user choose; don't switch on your own. Examples: a retainer or maintenance task the work belongs to, or an earlier issue's task this one continues.
3. **Word the notes.** They default to the issue's title. If the title is technical or internal, suggest client-friendly wording that says what was done for the client, e.g. "Back up the site's database every night and keep copies offsite" rather than "Nightly database backups with offsite copies". Plain language, no repo names, issue numbers, links or internal jargon; at most 500 characters.
4. **Preview:**
   ```
   zoho-time log <repo>#<issue> <time> [--date=YYYY-MM-DD] [--task="<existing task name or ID>"] [--notes="<client-facing text>"]
   ```
   `--task` only uses an existing task; it fails rather than creating one.
5. **Ask for confirmation.** Show the issue (e.g. `bluebird-www #39`), the task name (and whether it's new or existing), the Zoho project, the date, the hours and the notes from the preview. Say how the hours were estimated so they can be adjusted, and mention any "Warning: this task already has … logged that day" line. Ask whether to save it.
6. **Only after a clear yes** in this conversation, run the same command with `--confirm` and report the saved entry ID. If the hours, date, task or notes are adjusted, preview again and re-confirm. A **no** means the issue isn't billed: skip it and don't ask again.

Never pass `--confirm` without that yes, even when asked to log time in the same message. A request to log time is not a confirmation of the previewed entry.

## Other commands

- `zoho-time tasks <repo>`: tasks on the repo's Zoho project, with their IDs.
- `zoho-time entries [--date=YYYY-MM-DD]`: entries logged on a day.
- `zoho-time delete <entry-id>`: preview a deletion; add `--confirm` only after a yes.
- `zoho-time projects`: active Zoho projects (each repo's project is named after the repo unless mapped in the config).

## Credentials

Credentials live in the macOS Keychain (service `zoho-time`). Never ask for a client secret, grant code or token in the conversation. If the CLI says credentials are missing or rejected, ask the user to run `zoho-time auth` in their own terminal (not through `!`, which would put the answers in the conversation).
