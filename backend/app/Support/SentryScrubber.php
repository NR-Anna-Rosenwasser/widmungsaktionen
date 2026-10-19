<?php

namespace App\Support;

use Sentry\Breadcrumb;
use Sentry\Event;

/**
 * Strips customer data from everything sent to Sentry: email addresses,
 * order tokens (they open an order with name and address), SQL bindings
 * in database errors and function arguments in stack traces.
 *
 * Request bodies are never sent (config/sentry.php, max_request_body_size).
 */
class SentryScrubber
{
    public static function event(Event $event): Event
    {
        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(self::text($exception->getValue()));

            foreach ($exception->getStacktrace()?->getFrames() ?? [] as $frame) {
                $frame->setVars([]);
            }
        }

        if ($event->getMessage() !== null) {
            $event->setMessage(self::text($event->getMessage()), $event->getMessageParams(), self::text($event->getMessageFormatted()));
        }

        if ($event->getTransaction() !== null) {
            $event->setTransaction(self::text($event->getTransaction()));
        }

        $event->setRequest(self::data($event->getRequest()));
        $event->setExtra(self::data($event->getExtra()));
        $event->setBreadcrumb(array_map(self::breadcrumb(...), $event->getBreadcrumbs()));

        return $event;
    }

    public static function breadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        if ($breadcrumb->getMessage() !== null) {
            $breadcrumb = $breadcrumb->withMessage(self::text($breadcrumb->getMessage()));
        }

        foreach (self::data($breadcrumb->getMetadata()) as $key => $value) {
            $breadcrumb = $breadcrumb->withMetadata($key, $value);
        }

        return $breadcrumb;
    }

    public static function text(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        // Database errors quote the full statement with its values.
        $text = preg_replace('/(SQL: ).*\)$/s', '$1[removed])', $text);

        return preg_replace(
            [
                '/[^\s@<>"\'(),;:]+@[^\s@<>"\'(),;:]+\.[a-z]{2,}/i',
                '#((?:orders|bestellung)/)[A-Za-z0-9]{20,}#',
                '/([?&]t=)[A-Za-z0-9]{20,}/',
            ],
            ['[email]', '$1[token]', '$1[token]'],
            $text,
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected static function data(array $data): array
    {
        array_walk_recursive($data, function (mixed &$value) {
            if (is_string($value)) {
                $value = self::text($value);
            }
        });

        return $data;
    }
}
