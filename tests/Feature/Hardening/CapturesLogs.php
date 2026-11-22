<?php

namespace Tests\Feature\Hardening;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * Records every log line so a test can assert the exact level, message and
 * context keys, and prove no personal data was written.
 */
trait CapturesLogs
{
    /** @var list<MessageLogged> */
    protected array $logged = [];

    protected bool $listeningToLogs;

    /** @var list<string> Values that must never appear in any log context. */
    protected array $personal = ['Zed Zebra', 'zed@zebra.test'];

    protected function captureLogs(): void
    {
        $listening = $this->logged !== [] || isset($this->listeningToLogs);
        $this->logged = [];
        if ($listening) {
            return; // already listening: a second listener would double every line
        }
        $this->listeningToLogs = true;
        Event::listen(MessageLogged::class, function (MessageLogged $log): void {
            $this->logged[] = $log;
        });
    }

    /**
     * @return list<MessageLogged>
     */
    protected function logsNamed(string $message): array
    {
        return array_values(array_filter($this->logged, fn (MessageLogged $log) => $log->message === $message));
    }

    /**
     * @param  list<string>  $keys  the exact context keys, in order
     */
    protected function assertLoggedOnce(string $level, string $message, array $keys): MessageLogged
    {
        $matches = $this->logsNamed($message);
        $this->assertCount(1, $matches, "Expected exactly one [{$message}] log");
        $this->assertSame($level, $matches[0]->level);
        $this->assertSame($keys, array_keys($matches[0]->context));

        return $matches[0];
    }

    protected function assertNotLogged(string $message): void
    {
        $this->assertSame([], $this->logsNamed($message), "Did not expect a [{$message}] log");
    }

    protected function assertNoPersonalData(): void
    {
        $dump = '';
        foreach ($this->logged as $log) {
            $dump .= json_encode(array_map(
                fn (mixed $value) => $value instanceof Throwable ? $value->getMessage() : $value,
                $log->context,
            ), JSON_PARTIAL_OUTPUT_ON_ERROR);
            $dump .= implode(',', array_keys($log->context));
        }

        foreach ([...$this->personal, 'email', 'name', 'address', 'postcode'] as $needle) {
            $this->assertStringNotContainsString($needle, $dump);
        }
    }
}
