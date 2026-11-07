<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoContentService;
use Database\Seeders\BeerSeeder;
use Illuminate\Console\Command;

class DemoContent extends Command
{
    protected $signature = 'app:demo-content
        {--fresh : Remove existing demo content first, then re-seed}
        {--remove : Remove all demo content and exit}
        {--force : Allow running when BEERCHECK_DEMO is off}';

    protected $description = 'Seed (or remove) fictional demo venues, punters, prices, reviews and photos for staging';

    public function handle(DemoContentService $demo): int
    {
        if ($this->option('remove')) {
            $this->components->info('Removed demo content: '.json_encode($demo->remove()));

            return self::SUCCESS;
        }

        if (! config('beercheck.demo.enabled') && ! $this->option('force')) {
            $this->components->warn('BEERCHECK_DEMO is not enabled; skipping (use --force to override).');

            return self::SUCCESS;
        }

        if ($this->option('fresh')) {
            $demo->remove();
        } elseif ($demo->exists()) {
            $this->components->info('Demo content already present; nothing to do (use --fresh to rebuild).');

            return self::SUCCESS;
        }

        $this->callSilently('db:seed', ['--class' => BeerSeeder::class, '--force' => true]);
        $counts = $demo->seed();
        $this->components->info(sprintf('Seeded %d demo venues, %d users, %d prices, %d publican replies.',
            $counts['venues'], $counts['users'], $counts['prices'], $counts['replies']));

        return self::SUCCESS;
    }
}
