<?php

namespace Database\Seeders\Concerns;

trait GuardsProductionSeeding
{
    protected function shouldAbortInProduction(): bool
    {
        if (app()->environment('production')) {
            $this->command?->error('Seeder blocked in production.');

            return true;
        }

        return false;
    }
}
