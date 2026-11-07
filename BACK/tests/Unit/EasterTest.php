<?php

namespace Tests\Unit;

use App\Support\Easter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EasterTest extends TestCase
{
    public static function knownDates(): array
    {
        return [
            [2000, '2000-04-23'],
            [2019, '2019-04-21'],
            [2024, '2024-03-31'],
            [2025, '2025-04-20'],
            [2026, '2026-04-05'],
            [2027, '2027-03-28'],
            [2038, '2038-04-25'],
        ];
    }

    #[DataProvider('knownDates')]
    public function test_easter_sunday(int $year, string $expected): void
    {
        $this->assertSame($expected, Easter::sunday($year)->toDateString());
    }

    public function test_good_friday_is_two_days_before(): void
    {
        $this->assertSame('2026-04-03', Easter::goodFriday(2026)->toDateString());
        $this->assertSame('2027-03-26', Easter::goodFriday(2027)->toDateString());
    }
}
