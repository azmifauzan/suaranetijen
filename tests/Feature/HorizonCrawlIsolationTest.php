<?php

use Illuminate\Support\Env;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\ProvisioningPlan;

it('omits crawl on an isolated light node while retaining its other supervisors', function () {
    $environment = Env::getRepository();
    $previous = $environment->get('HORIZON_LIGHT_CRAWL_MAX_PROCESSES');
    $environment->set('HORIZON_LIGHT_CRAWL_MAX_PROCESSES', '0');

    try {
        $horizon = require config_path('horizon.php');
    } finally {
        if ($previous === null) {
            $environment->clear('HORIZON_LIGHT_CRAWL_MAX_PROCESSES');
        } else {
            $environment->set('HORIZON_LIGHT_CRAWL_MAX_PROCESSES', $previous);
        }
    }

    $provisioned = [];
    $commands = Mockery::mock(HorizonCommandQueue::class);
    $commands->shouldReceive('push')->once()->andReturnUsing(function ($queue, $command, $options) use (&$provisioned): void {
        $provisioned[] = $options['queue'];
    });
    $this->app->instance(HorizonCommandQueue::class, $commands);
    $plan = new ProvisioningPlan('test-node', $horizon['environments'], $horizon['defaults']);

    $plan->deploy('worker-light');

    expect($provisioned)->toBe(['critical,aggregate,analysis,crawl-youtube,themes,maintenance']);
    expect($plan->optionsFor('worker-heavy', 'supervisor-crawl')->maxProcesses)->toBeGreaterThan(0);
});

it('runs every non-crawl queue on a light node in one fixed pool, plus crawl when enabled', function () {
    $horizon = require config_path('horizon.php');
    $provisioned = [];
    $commands = Mockery::mock(HorizonCommandQueue::class);
    $commands->shouldReceive('push')->twice()->andReturnUsing(function ($queue, $command, $options) use (&$provisioned): void {
        $provisioned[$options['queue']] = [$options['balance'], $options['minProcesses'], $options['maxProcesses']];
    });
    $this->app->instance(HorizonCommandQueue::class, $commands);

    (new ProvisioningPlan('test-node', $horizon['environments'], $horizon['defaults']))->deploy('worker-light');

    expect($provisioned)->toBe([
        'discovery,crawl' => ['auto', 1, 1],
        'critical,aggregate,analysis,crawl-youtube,themes,maintenance' => [false, 2, 2],
    ]);
});
