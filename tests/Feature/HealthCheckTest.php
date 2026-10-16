<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

function mockRedisPing(bool $reachable): void
{
    $connection = Mockery::mock();
    $reachable
        ? $connection->shouldReceive('ping')->andReturn(true)
        : $connection->shouldReceive('ping')->andThrow(new RuntimeException('read error on connection'));

    Redis::shouldReceive('connection')->andReturn($connection);
}

test('health reports ok when database and redis respond', function () {
    mockRedisPing(true);

    $this->getJson('/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'checks' => ['database' => true, 'redis' => true]]);
});

test('health returns 503 without leaking the error when redis is down', function () {
    mockRedisPing(false);

    $this->getJson('/health')
        ->assertStatus(503)
        ->assertExactJson(['status' => 'degraded', 'checks' => ['database' => true, 'redis' => false]]);
});

test('health returns 503 when the database is down', function () {
    mockRedisPing(true);
    DB::shouldReceive('select')->andThrow(new RuntimeException('connection refused'));

    $this->getJson('/health')
        ->assertStatus(503)
        ->assertJsonPath('checks.database', false);
});

function heartbeatEvent()
{
    return collect(app(Schedule::class)->events())
        ->first(fn ($event) => $event->description === 'betterstack-heartbeat');
}

test('heartbeat is skipped when no url is configured', function () {
    config(['services.betterstack.heartbeat_url' => null]);

    expect(heartbeatEvent()->filtersPass(app()))->toBeFalse();
});

test('heartbeat is skipped when horizon has no live master supervisor', function () {
    config(['services.betterstack.heartbeat_url' => 'https://uptime.betterstack.com/api/v1/heartbeat/x']);
    $this->mock(MasterSupervisorRepository::class)->shouldReceive('all')->andReturn([]);

    expect(heartbeatEvent()->filtersPass(app()))->toBeFalse();
});

test('heartbeat fires when a url is set and horizon is running', function () {
    config(['services.betterstack.heartbeat_url' => 'https://uptime.betterstack.com/api/v1/heartbeat/x']);
    $this->mock(MasterSupervisorRepository::class)->shouldReceive('all')->andReturn([(object) ['name' => 'master']]);

    expect(heartbeatEvent()->filtersPass(app()))->toBeTrue();
});
