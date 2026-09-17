<?php

declare(strict_types=1);
namespace App\Services;

final class MediaMarktService extends MiraklIntegration
{
    public function platform(): string { return 'mediamarkt'; }
    protected function label(): string { return 'MediaMarkt'; }
    public function defaultApiUrl(): string { return 'https://mediamarktsaturn.mirakl.net'; }
}
