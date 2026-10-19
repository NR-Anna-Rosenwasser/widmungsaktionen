<?php

namespace Tests\Feature;

use App\Support\SentryScrubber;
use Illuminate\Database\QueryException;
use RuntimeException;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Stacktrace;
use Tests\TestCase;

class SentryScrubberTest extends TestCase
{
    public function test_removes_emails_tokens_and_sql_values(): void
    {
        $token = str_repeat('aB3', 16);

        $this->assertSame('Mail to [email] failed', SentryScrubber::text('Mail to mia.muster+buch@example.ch failed'));
        $this->assertSame('/api/tenants/anna/orders/[token]/submit', SentryScrubber::text("/api/tenants/anna/orders/$token/submit"));
        $this->assertSame('/anna/bestellen?t=[token]', SentryScrubber::text("/anna/bestellen?t=$token"));

        $query = new QueryException('pgsql', 'insert into orders (email) values (?)', ['mia@example.ch'], new RuntimeException('duplicate key'));
        $scrubbed = SentryScrubber::text($query->getMessage());
        $this->assertStringNotContainsString('mia', $scrubbed);
        $this->assertStringContainsString('duplicate key', $scrubbed);
        $this->assertStringEndsWith('SQL: [removed])', $scrubbed);
    }

    public function test_scrubs_whole_event(): void
    {
        $frame = new Frame('handle', 'OrderController.php', 12, vars: ['email' => 'mia@example.ch']);
        $event = Event::createEvent();
        $event->setExceptions([new ExceptionDataBag(new RuntimeException('x'), new Stacktrace([$frame]))]);
        $event->getExceptions()[0]->setValue('No order for mia@example.ch');
        $event->setRequest(['url' => 'https://anna.example/api/tenants/anna/orders/'.str_repeat('x', 48)]);
        $event->setBreadcrumb([new Breadcrumb('info', 'default', 'log', 'Sent to mia@example.ch', ['to' => 'mia@example.ch'])]);

        $event = SentryScrubber::event($event);

        $this->assertSame('No order for [email]', $event->getExceptions()[0]->getValue());
        $this->assertSame([], $frame->getVars());
        $this->assertStringEndsWith('/orders/[token]', $event->getRequest()['url']);
        $this->assertSame('Sent to [email]', $event->getBreadcrumbs()[0]->getMessage());
        $this->assertSame('[email]', $event->getBreadcrumbs()[0]->getMetadata()['to']);
    }

    public function test_request_bodies_are_never_sent(): void
    {
        $this->assertSame('never', config('sentry.max_request_body_size'));
        $this->assertFalse((bool) config('sentry.send_default_pii'));
    }
}
