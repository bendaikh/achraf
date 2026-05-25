<?php

namespace App\Support;

class AmountInWords
{
    private const UNITS = [
        '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    private const TENS = [
        '', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt',
    ];

    public static function dirhams(float $amount): string
    {
        $whole = (int) floor(abs($amount));
        $cents = (int) round((abs($amount) - $whole) * 100);

        $words = self::convert($whole);

        if ($whole === 0) {
            $result = 'zéro dirham';
        } elseif ($whole === 1) {
            $result = 'un dirham';
        } else {
            $result = $words.' dirhams';
        }

        if ($cents > 0) {
            $centWords = self::convert($cents);
            $result .= ' et '.($cents === 1 ? 'un centime' : $centWords.' centimes');
        }

        return mb_strtoupper($result);
    }

    private static function convert(int $number): string
    {
        if ($number < 20) {
            return self::UNITS[$number];
        }

        if ($number < 100) {
            $ten = intdiv($number, 10);
            $unit = $number % 10;

            if ($ten === 7 || $ten === 9) {
                return self::TENS[$ten].'-'.self::UNITS[10 + $unit];
            }

            if ($ten === 8 && $unit === 0) {
                return 'quatre-vingts';
            }

            $tenWord = self::TENS[$ten];
            if ($unit === 0) {
                return $tenWord;
            }
            if ($unit === 1 && $ten !== 8) {
                return $tenWord.'-et-un';
            }

            return $tenWord.'-'.self::UNITS[$unit];
        }

        if ($number < 1000) {
            $hundreds = intdiv($number, 100);
            $remainder = $number % 100;
            $hundredWord = $hundreds === 1 ? 'cent' : self::UNITS[$hundreds].' cent';

            if ($remainder === 0) {
                return $hundredWord.($hundreds > 1 && $remainder === 0 ? 's' : '');
            }

            return $hundredWord.' '.self::convert($remainder);
        }

        if ($number < 1000000) {
            $thousands = intdiv($number, 1000);
            $remainder = $number % 1000;
            $thousandWord = $thousands === 1 ? 'mille' : self::convert($thousands).' mille';

            if ($remainder === 0) {
                return $thousandWord;
            }

            return $thousandWord.' '.self::convert($remainder);
        }

        return (string) $number;
    }
}
