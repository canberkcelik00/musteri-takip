<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

afterEach(fn () => cleanUpDeploy());

function scheduledEvent(string $command): Event
{
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains((string) $event->command, $command));

    expect($event)->not->toBeNull();

    return $event;
}

it('deploys a new release once', function () {
    $runner = prepareDeploy('release-1');

    $this->artisan('app:deploy')->assertSuccessful();
    $this->artisan('app:deploy')->assertSuccessful();

    expect($runner->calls)->toBe(DEPLOY_STEPS)
        ->and(deployedRelease())->toBe('release-1');
});

it('does nothing and succeeds when there is no RELEASE file', function () {
    $runner = prepareDeploy(null);

    $this->artisan('app:deploy')->assertSuccessful();

    expect($runner->calls)->toBe([])
        ->and(deployedRelease())->toBeNull();
});

it('fails with a generic message when a step fails', function () {
    Log::spy();
    prepareDeploy('release-1', failingCommand: 'migrate');

    $this->artisan('app:deploy')
        ->expectsOutputToContain(__('deploy.failed'))
        ->assertFailed();

    expect(deployedRelease())->toBeNull();
});

it('is not scheduled: a release is deployed by opening the deploy address', function () {
    $commands = collect(app(Schedule::class)->events())->map(fn (Event $event): string => (string) $event->command);

    expect($commands->filter(fn (string $command): bool => str_contains($command, 'app:deploy')))->toBeEmpty();
});

it('skips the customer service job while an uploaded release is not deployed yet', function () {
    prepareDeploy('release-1');
    $process = scheduledEvent('app:process-customer-services');

    expect($process->filtersPass(app()))->toBeFalse();

    $this->artisan('app:deploy')->assertSuccessful();

    expect($process->filtersPass(app()))->toBeTrue();

    file_put_contents(config('app.deploy.release_file'), 'release-2');

    expect($process->filtersPass(app()))->toBeFalse();
});

it('runs the customer service job when there is no RELEASE file', function () {
    prepareDeploy(null);

    expect(scheduledEvent('app:process-customer-services')->filtersPass(app()))->toBeTrue();
});
