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
    $commands->shouldReceive('push')->times(5)->andReturnUsing(function ($queue, $command, $options) use (&$provisioned): void {
        $provisioned[] = $options['queue'];
    });
    $this->app->instance(HorizonCommandQueue::class, $commands);
    $plan = new ProvisioningPlan('test-node', $horizon['environments'], $horizon['defaults']);

    $plan->deploy('worker-light');

    expect($provisioned)->toContain('analysis', 'critical,aggregate', 'crawl-youtube', 'themes', 'maintenance')
        ->not->toContain('discovery,crawl');
    expect($plan->optionsFor('worker-heavy', 'supervisor-crawl')->maxProcesses)->toBeGreaterThan(0);
});
