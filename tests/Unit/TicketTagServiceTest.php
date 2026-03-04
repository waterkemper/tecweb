<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TicketTagService;
use PHPUnit\Framework\TestCase;

class TicketTagServiceTest extends TestCase
{
    public function test_parse_tags_normalizes_and_deduplicates_values(): void
    {
        $service = new TicketTagService();

        $tags = $service->parseTags('  urgente, urgente suporte@@, cliente:vip  , --ok  ');

        $this->assertSame(['urgente', 'suporte', 'cliente:vip', '--ok'], $tags);
    }
}
