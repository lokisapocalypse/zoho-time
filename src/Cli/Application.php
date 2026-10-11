<?php

declare(strict_types=1);

namespace ZohoTime\Cli;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ZohoTime\Application\LogTimeRequest;
use ZohoTime\Application\PreparedEntry;
use ZohoTime\Application\TimeEntryService;
use ZohoTime\Domain\Duration;
use ZohoTime\Domain\NotBillableException;
use ZohoTime\Domain\TimeEntry;
use ZohoTime\Infrastructure\CredentialStore;
use ZohoTime\Infrastructure\ZohoOAuth;

/**
 * The zoho-time command line: parses arguments and prints results.
 *
 * Nothing is written to Zoho without --confirm, so a confirmation can always come first.
 */
final class Application
{
    public const EXIT_OK = 0;

    public const EXIT_ERROR = 1;

    public const EXIT_USAGE = 2;

    public const EXIT_NOT_BILLABLE = 3;

    private const USAGE = <<<'TXT'
        Usage:
          zoho-time log <repo#issue> <time> [--date=YYYY-MM-DD] [--task=<name or ID>] [--notes=<text>] [--confirm]
              Preview (or, with --confirm, save) a billable time entry on the issue's task.
              <time> is e.g. 1h05m, 40m, 1.25h or 1:05; it's rounded up to the quarter hour.
              --date defaults to today. --task logs on an existing task instead (never created).
              --notes is the client-facing description; it defaults to the issue's title.
          zoho-time tasks <repo>                  List the tasks on the repo's Zoho project.
          zoho-time entries [--date=YYYY-MM-DD]   List the time entries logged on a day.
          zoho-time delete <entry-id> [--confirm] Preview (or, with --confirm, do) a deletion.
          zoho-time projects                      List the active Zoho projects.
          zoho-time auth                          Store Zoho credentials in the Keychain.
        TXT;

    /** @var Closure(): TimeEntryService */
    private readonly Closure $service;

    /** @var Closure(): array{ZohoOAuth, CredentialStore} */
    private readonly Closure $auth;

    /** @var resource */
    private $output;

    /** @var resource */
    private $errors;

    /** @var resource */
    private $input;

    private readonly DateTimeImmutable $today;

    /**
     * Create the application.
     *
     * @param Closure(): TimeEntryService                   $service Builds the service when a command needs Zoho.
     * @param Closure(): array{ZohoOAuth, CredentialStore}  $auth    Builds the OAuth helper and credential store.
     * @param resource                                      $input   Where prompts are answered.
     * @param resource                                      $output  Where results are printed.
     * @param resource                                      $errors  Where errors are printed.
     * @param DateTimeImmutable                             $today   Today's date.
     */
    public function __construct(
        Closure $service,
        Closure $auth,
        $input,
        $output,
        $errors,
        DateTimeImmutable $today
    ) {
        $this->service = $service;
        $this->auth = $auth;
        $this->input = $input;
        $this->output = $output;
        $this->errors = $errors;
        $this->today = $today->setTime(0, 0);
    }

    /**
     * Run a command.
     *
     * @param list<string> $arguments Command-line arguments, without the program name.
     *
     * @return int The exit code.
     */
    public function run(array $arguments): int
    {
        [$positional, $options] = $this->parse($arguments);
        $command = array_shift($positional) ?? 'help';

        try {
            return match ($command) {
                'log' => $this->log($positional, $options),
                'entries' => $this->entries($options),
                'tasks' => $this->tasks($positional),
                'delete' => $this->delete($positional, $options),
                'projects' => $this->projects(),
                'auth' => $this->authorize(),
                'help', '--help', '-h' => $this->usage(self::EXIT_OK),
                default => $this->usage(self::EXIT_USAGE),
            };
        } catch (NotBillableException $exception) {
            $this->error($exception->getMessage());

            return self::EXIT_NOT_BILLABLE;
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::EXIT_USAGE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::EXIT_ERROR;
        }
    }

    /**
     * Preview or save a time entry on an issue.
     *
     * @param list<string>               $positional The issue reference and the time.
     * @param array<string, string|true> $options    --date, --task, --notes and --confirm.
     *
     * @return int
     *
     * @throws InvalidArgumentException When the arguments are wrong.
     */
    private function log(array $positional, array $options): int
    {
        if (count($positional) !== 2) {
            throw new InvalidArgumentException('log needs an issue and a time, e.g. log bluebird-www#39 1h05m.');
        }

        $request = new LogTimeRequest(
            $positional[0],
            Duration::parse($positional[1]),
            $this->date($options),
            $this->task($options),
            $this->notes($options)
        );
        $service = ($this->service)();

        if (!isset($options['confirm'])) {
            $this->preview($service->prepare($request, $this->today));
            $this->line('');
            $this->line('Not saved. Run again with --confirm to save it.');

            return self::EXIT_OK;
        }

        $saved = $service->log($request, $this->today);
        $entry = $saved->entry();
        $this->line(sprintf(
            'Saved time entry %s: %s on "%s" (%s), %s.',
            (string) $entry->id(),
            $this->hours($entry->duration()),
            $entry->task()->name(),
            $entry->task()->project()->name(),
            $entry->date()->format('Y-m-d')
        ));

        return self::EXIT_OK;
    }

    /**
     * Print a prepared entry for confirmation.
     *
     * @param PreparedEntry $prepared The entry.
     *
     * @return void
     */
    private function preview(PreparedEntry $prepared): void
    {
        $entry = $prepared->entry();
        $this->line('Issue:    ' . $prepared->issue()->reference() . ' (internal; not sent to Zoho)');
        $this->line(
            'Task:     ' . $entry->task()->name() . ($prepared->createsTask() ? ' (new Zoho task)' : ' (existing task)')
        );
        $this->line('Project:  ' . $entry->task()->project()->name());
        $this->line('Date:     ' . $entry->date()->format('Y-m-d'));
        $this->line(sprintf(
            'Hours:    %s (%s worked, rounded up to the quarter hour)',
            $this->hours($entry->duration()),
            $prepared->worked()->toHuman()
        ));
        $this->line('Notes:    ' . $entry->notes());

        foreach ($prepared->sameDayEntries() as $existing) {
            $this->line(sprintf(
                'Warning:  this task already has %s logged that day (entry %s).',
                $this->hours($existing->duration()),
                (string) $existing->id()
            ));
        }
    }

    /**
     * List the entries logged on a day.
     *
     * @param array<string, string|true> $options --date.
     *
     * @return int
     */
    private function entries(array $options): int
    {
        $date = $this->date($options);
        $entries = ($this->service)()->entriesOn($date);

        if ($entries === []) {
            $this->line("No time entries on {$date->format('Y-m-d')}.");

            return self::EXIT_OK;
        }

        foreach ($entries as $entry) {
            $this->line($this->describe($entry));
        }

        return self::EXIT_OK;
    }

    /**
     * List the tasks on a repository's Zoho project.
     *
     * @param list<string> $positional The repository name.
     *
     * @return int
     *
     * @throws InvalidArgumentException When no repository is given.
     */
    private function tasks(array $positional): int
    {
        if (count($positional) !== 1) {
            throw new InvalidArgumentException('tasks needs a repository, e.g. tasks bluebird-www.');
        }

        $tasks = ($this->service)()->tasksFor($positional[0]);

        if ($tasks === []) {
            $this->line("{$positional[0]}'s Zoho project has no tasks yet.");
        }

        foreach ($tasks as $task) {
            $this->line("{$task->id()}  {$task->name()}");
        }

        return self::EXIT_OK;
    }

    /**
     * Preview or delete a time entry.
     *
     * @param list<string>               $positional The entry ID.
     * @param array<string, string|true> $options    --confirm.
     *
     * @return int
     *
     * @throws InvalidArgumentException When no entry ID is given.
     */
    private function delete(array $positional, array $options): int
    {
        if (count($positional) !== 1) {
            throw new InvalidArgumentException('delete needs a time entry ID.');
        }

        $service = ($this->service)();
        $entry = $service->find($positional[0]);

        if (!isset($options['confirm'])) {
            $this->line('Would delete: ' . $this->describe($entry));
            $this->line('Not deleted. Run again with --confirm to delete it.');

            return self::EXIT_OK;
        }

        $service->delete($positional[0]);
        $this->line('Deleted: ' . $this->describe($entry));

        return self::EXIT_OK;
    }

    /**
     * List the active Zoho projects.
     *
     * @return int
     */
    private function projects(): int
    {
        foreach (($this->service)()->projects() as $project) {
            $this->line("{$project->id()}  {$project->name()}");
        }

        return self::EXIT_OK;
    }

    /**
     * Ask for the Zoho client credentials and a Self Client grant code, and store a refresh token.
     *
     * @return int
     *
     * @throws RuntimeException When an answer is empty.
     */
    private function authorize(): int
    {
        [$oauth, $credentials] = ($this->auth)();

        $this->line('Create a Self Client at https://api-console.zoho.com, then generate a grant code with the scope');
        $this->line('ZohoInvoice.projects.CREATE,ZohoInvoice.projects.READ,ZohoInvoice.projects.DELETE');
        $this->line('');
        $credentials->set('client_id', $this->ask('Client ID: ', false));
        $credentials->set('client_secret', $this->ask('Client secret: ', true));
        $oauth->authorize($this->ask('Grant code: ', true));
        $this->line('Stored in the Keychain (service "zoho-time"). Try: zoho-time projects');

        return self::EXIT_OK;
    }

    /**
     * Prompt for an answer, hiding what's typed when asked to.
     *
     * @param string $prompt The prompt.
     * @param bool   $hidden Whether to hide the typing.
     *
     * @return string
     *
     * @throws RuntimeException When the answer is empty.
     */
    private function ask(string $prompt, bool $hidden): string
    {
        fwrite($this->output, $prompt);
        $hide = $hidden && stream_isatty($this->input);

        if ($hide) {
            shell_exec('stty -echo');
        }

        $answer = trim((string) fgets($this->input));

        if ($hide) {
            shell_exec('stty echo');
            $this->line('');
        }

        if ($answer === '') {
            throw new RuntimeException('No answer given; nothing was stored.');
        }

        return $answer;
    }

    /**
     * Read --date, defaulting to today.
     *
     * @param array<string, string|true> $options The options.
     *
     * @return DateTimeImmutable
     *
     * @throws InvalidArgumentException When the date isn't YYYY-MM-DD.
     */
    private function date(array $options): DateTimeImmutable
    {
        if (!isset($options['date'])) {
            return $this->today;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $options['date']);

        if ($date === false || $date->format('Y-m-d') !== $options['date']) {
            throw new InvalidArgumentException("--date must be YYYY-MM-DD, not \"{$options['date']}\".");
        }

        return $date;
    }

    /**
     * Read --task, the existing task to log on, if any.
     *
     * @param array<string, string|true> $options The options.
     *
     * @return string|null
     *
     * @throws InvalidArgumentException When --task has no value.
     */
    private function task(array $options): ?string
    {
        if (!isset($options['task'])) {
            return null;
        }

        if ($options['task'] === true || trim($options['task']) === '') {
            throw new InvalidArgumentException('--task needs a task name or ID, e.g. --task="Monthly maintenance".');
        }

        return trim($options['task']);
    }

    /**
     * Read --notes, the client-facing notes, if given.
     *
     * @param array<string, string|true> $options The options.
     *
     * @return string|null
     *
     * @throws InvalidArgumentException When --notes has no value.
     */
    private function notes(array $options): ?string
    {
        if (!isset($options['notes'])) {
            return null;
        }

        if ($options['notes'] === true || trim($options['notes']) === '') {
            throw new InvalidArgumentException('--notes needs text, e.g. --notes="Set up nightly backups".');
        }

        return trim($options['notes']);
    }

    /**
     * Describe an entry on one line.
     *
     * @param TimeEntry $entry The entry.
     *
     * @return string
     */
    private function describe(TimeEntry $entry): string
    {
        return sprintf(
            '%s  %s  %6s  %s / %s',
            (string) $entry->id(),
            $entry->date()->format('Y-m-d'),
            $this->hours($entry->duration()),
            $entry->task()->project()->name(),
            $entry->task()->name()
        );
    }

    /**
     * Format a duration as hours, e.g. "1.25h".
     *
     * @param Duration $duration The duration.
     *
     * @return string
     */
    private function hours(Duration $duration): string
    {
        return rtrim(rtrim(number_format($duration->hours(), 2, '.', ''), '0'), '.') . 'h';
    }

    /**
     * Split arguments into positional ones and --options.
     *
     * @param list<string> $arguments The arguments.
     *
     * @return array{list<string>, array<string, string|true>}
     */
    private function parse(array $arguments): array
    {
        $positional = [];
        $options = [];

        foreach ($arguments as $argument) {
            if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $argument, $match)) {
                $options[$match[1]] = $match[2] ?? true;
            } else {
                $positional[] = $argument;
            }
        }

        return [$positional, $options];
    }

    /**
     * Print the usage.
     *
     * @param int $exitCode The exit code to return.
     *
     * @return int
     */
    private function usage(int $exitCode): int
    {
        fwrite($exitCode === self::EXIT_OK ? $this->output : $this->errors, self::USAGE . "\n");

        return $exitCode;
    }

    /**
     * Print a line of output.
     *
     * @param string $text The text.
     *
     * @return void
     */
    private function line(string $text): void
    {
        fwrite($this->output, $text . "\n");
    }

    /**
     * Print an error.
     *
     * @param string $text The message.
     *
     * @return void
     */
    private function error(string $text): void
    {
        fwrite($this->errors, "zoho-time: {$text}\n");
    }
}
