<?php

namespace App\Support;

/**
 * Konversi angka ke kalimat Bahasa Indonesia (terbilang), mis. 16060000 => "Enam Belas Juta Enam Puluh Ribu".
 */
class Terbilang
{
    private const WORDS = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];

    public static function make(float|int $number): string
    {
        $n = (int) round(abs($number));

        return $n === 0 ? 'Nol' : trim(preg_replace('/\s+/', ' ', self::convert($n)));
    }

    private static function convert(int $n): string
    {
        return match (true) {
            $n < 12 => self::WORDS[$n],
            $n < 20 => self::convert($n - 10).' Belas',
            $n < 100 => self::convert(intdiv($n, 10)).' Puluh '.self::convert($n % 10),
            $n < 200 => 'Seratus '.self::convert($n - 100),
            $n < 1000 => self::convert(intdiv($n, 100)).' Ratus '.self::convert($n % 100),
            $n < 2000 => 'Seribu '.self::convert($n - 1000),
            $n < 1_000_000 => self::convert(intdiv($n, 1000)).' Ribu '.self::convert($n % 1000),
            $n < 1_000_000_000 => self::convert(intdiv($n, 1_000_000)).' Juta '.self::convert($n % 1_000_000),
            default => self::convert(intdiv($n, 1_000_000_000)).' Miliar '.self::convert($n % 1_000_000_000),
        };
    }
}
