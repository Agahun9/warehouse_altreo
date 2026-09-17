<?php

declare(strict_types=1);
namespace App\Services;

final class EmpikService extends MiraklIntegration
{
    public function platform(): string { return 'empik'; }
    protected function label(): string { return 'Empik'; }
    public function defaultApiUrl(): string { return 'https://marketplace.empik.com'; }
    protected function productLocale(): string { return 'pl_PL'; }
}
