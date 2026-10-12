<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

afterEach(fn () => cleanUpDeploy());

/**
 * @return array<string, string>
 */
function deployHeaders(?string $key = DEPLOY_TEST_KEY): array
{
    return $key === null ? [] : ['Authorization' => 'Bearer '.$key];
}

/**
 * The address opened in a browser after a pull, with the key as a query parameter.
 */
function deployAddress(?string $key = DEPLOY_TEST_KEY): string
{
    return $key === null ? '/deploy' : '/deploy?'.http_build_query(['key' => $key]);
}

it('refuses a missing or wrong key', function (?string $key) {
    $runner = prepareDeploy();

    $this->postJson('/deploy', headers: deployHeaders($key))->assertForbidden();
    $this->getJson(deployAddress($key))->assertForbidden();

    expect($runner->calls)->toBe([])
        ->and(deployedRelease())->toBeNull();
})->with([
    'missing' => [null],
    'wrong' => [str_repeat('x', 36)],
    'empty' => [''],
]);

it('refuses every request when the configured key is shorter than 32 characters', function () {
    $runner = prepareDeploy();
    config()->set('app.deploy.key', 'short-key');

    $this->postJson('/deploy', headers: deployHeaders('short-key'))->assertForbidden();
    $this->getJson(deployAddress('short-key'))->assertForbidden();

    expect($runner->calls)->toBe([])
        ->and(deployedRelease())->toBeNull();
});

it('refuses every request when no key is configured', function (?string $configured) {
    $runner = prepareDeploy();
    config()->set('app.deploy.key', $configured);

    $this->postJson('/deploy', headers: deployHeaders(''))->assertForbidden();
    $this->postJson('/deploy')->assertForbidden();
    $this->getJson(deployAddress(''))->assertForbidden();
    $this->getJson('/deploy')->assertForbidden();

    expect($runner->calls)->toBe([]);
})->with([[null], ['']]);

it('refuses a key given as a list', function () {
    $runner = prepareDeploy();

    $this->getJson('/deploy?key[]='.DEPLOY_TEST_KEY)->assertForbidden();

    expect($runner->calls)->toBe([]);
});

it('deploys when the address is opened in a browser with the key', function () {
    $runner = prepareDeploy('release-1');

    $response = $this->get(deployAddress())
        ->assertOk()
        ->assertJson(['status' => 'deployed', 'message' => __('deploy.deployed')]);

    expect($runner->calls)->toBe(DEPLOY_STEPS)
        ->and(deployedRelease())->toBe('release-1')
        ->and($response->getContent())->toContain(__('deploy.deployed'));

    $this->get(deployAddress())->assertOk()->assertJson(['status' => 'up_to_date']);

    expect($runner->calls)->toBe(DEPLOY_STEPS);
});

it('accepts only GET and POST', function () {
    $runner = prepareDeploy();

    $this->putJson('/deploy', headers: deployHeaders())->assertStatus(405);
    $this->deleteJson('/deploy', headers: deployHeaders())->assertStatus(405);

    expect($runner->calls)->toBe([]);
});

it('runs the deploy steps once and remembers the release', function () {
    $runner = prepareDeploy('release-1');
    config()->set('app.admin', ['name' => 'İşletme Sahibi', 'email' => 'sahip@example.com', 'password' => 'ilk-sifre']);

    $this->postJson('/deploy', headers: deployHeaders())
        ->assertOk()
        ->assertJson(['status' => 'deployed', 'message' => __('deploy.deployed')]);

    expect($runner->calls)->toBe(DEPLOY_STEPS)
        ->and(deployedRelease())->toBe('release-1')
        ->and(User::where('email', 'sahip@example.com')->exists())->toBeTrue();
});

it('removes the stale icon manifest that the console optimize wrote', function () {
    prepareDeploy('release-1');
    $manifest = deployTestDirectory().'/blade-icons.php';
    file_put_contents($manifest, '<?php return [];');

    $this->postJson('/deploy', headers: deployHeaders())->assertOk();

    expect(file_exists($manifest))->toBeFalse();
});

it('removes the icon manifest before the later steps, so a failed deploy leaves no stale one', function () {
    Log::spy();
    prepareDeploy('release-1', failingCommand: 'migrate');
    $manifest = deployTestDirectory().'/blade-icons.php';
    file_put_contents($manifest, '<?php return [];');

    $this->postJson('/deploy', headers: deployHeaders())->assertStatus(500);

    expect(file_exists($manifest))->toBeFalse();
});

it('deploys when there is no icon manifest', function () {
    prepareDeploy('release-1');

    expect(file_exists(deployTestDirectory().'/blade-icons.php'))->toBeFalse();

    $this->postJson('/deploy', headers: deployHeaders())->assertOk()->assertJson(['status' => 'deployed']);
});

it('does not run again for the same release', function () {
    $runner = prepareDeploy('release-1');

    $this->postJson('/deploy', headers: deployHeaders())->assertOk();
    $this->postJson('/deploy', headers: deployHeaders())
        ->assertOk()
        ->assertJson(['status' => 'up_to_date']);

    expect($runner->calls)->toBe(DEPLOY_STEPS);
});

it('runs again when a new release is uploaded', function () {
    $runner = prepareDeploy('release-1');

    $this->postJson('/deploy', headers: deployHeaders())->assertOk();
    file_put_contents(config('app.deploy.release_file'), "release-2\n");
    $this->postJson('/deploy', headers: deployHeaders())->assertOk()->assertJson(['status' => 'deployed']);

    expect($runner->calls)->toBe([...DEPLOY_STEPS, ...DEPLOY_STEPS])
        ->and(deployedRelease())->toBe('release-2');
});

it('answers 422 without doing anything when there is no RELEASE file', function () {
    $runner = prepareDeploy(null);

    $this->postJson('/deploy', headers: deployHeaders())
        ->assertStatus(422)
        ->assertJson(['status' => 'no_release', 'message' => __('deploy.no_release')]);

    expect($runner->calls)->toBe([])
        ->and(deployedRelease())->toBeNull();
});

it('does not remember the release when a step fails and tries again on the next call', function () {
    Log::spy();
    $runner = prepareDeploy('release-1', failingCommand: 'migrate');

    $this->postJson('/deploy', headers: deployHeaders())->assertStatus(500);

    expect($runner->calls)->toBe(['optimize:clear', 'filament:clear-cached-components', 'migrate'])
        ->and(deployedRelease())->toBeNull();

    Log::shouldHaveReceived('error')->once();

    $fixed = swapArtisanRunner();
    $this->postJson('/deploy', headers: deployHeaders())->assertOk()->assertJson(['status' => 'deployed']);

    expect($fixed->calls)->toBe(DEPLOY_STEPS)
        ->and(deployedRelease())->toBe('release-1');
});

it('answers a failure with a generic message only', function () {
    Log::spy();
    prepareDeploy('release-1', failingCommand: 'migrate');

    $response = $this->postJson('/deploy', headers: deployHeaders())->assertStatus(500);

    expect($response->json())->toBe(['status' => 'failed', 'message' => __('deploy.failed')])
        ->and($response->getContent())->not->toContain('migrate')
        ->and($response->getContent())->not->toContain('exit code');
});

it('answers 409 and does nothing while another deploy holds the lock', function () {
    $runner = prepareDeploy('release-1');
    $lock = Cache::store('file')->lock('deploy', 600);
    expect($lock->get())->toBeTrue();

    try {
        $this->postJson('/deploy', headers: deployHeaders())
            ->assertStatus(409)
            ->assertJson(['status' => 'busy']);
    } finally {
        $lock->release();
    }

    expect($runner->calls)->toBe([])
        ->and(deployedRelease())->toBeNull();
});
