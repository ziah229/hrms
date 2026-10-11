<?php

namespace Tests\Unit;

use App\Support\Terbilang;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_terbilang_converts_rupiah_amounts(): void
    {
        $this->assertSame('Enam Belas Juta Enam Puluh Ribu', Terbilang::make(16_060_000));
        $this->assertSame('Seratus Sebelas', Terbilang::make(111));
        $this->assertSame('Seribu Dua Ratus Lima Puluh', Terbilang::make(1250));
        $this->assertSame('Nol', Terbilang::make(0));
    }
}
