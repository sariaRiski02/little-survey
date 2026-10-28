<?php

namespace App\Services;

/**
 * Small statistics toolbox: no external dependency, double precision.
 */
final class Statistics
{
    /**
     * Critical z for a two-sided confidence level in percent (90, 95 or 99).
     */
    public static function zForConfidence(int $confidence): float
    {
        return StatistikService::CONFIDENCE_LEVELS[$confidence] ?? StatistikService::CONFIDENCE_LEVELS[95];
    }

    /**
     * P(X >= x) for X ~ chi-square with $df degrees of freedom.
     */
    public static function chiSquareSurvival(float $x, int $df): float
    {
        if ($x <= 0) {
            return 1.0;
        }

        return self::upperIncompleteGamma($df / 2, $x / 2);
    }

    /**
     * Two-sided p-value of a standard normal statistic.
     */
    public static function twoSidedPValue(float $z): float
    {
        return self::chiSquareSurvival($z * $z, 1);
    }

    /**
     * Wilson score interval for a proportion, as fractions between 0 and 1.
     *
     * @return array{0: float, 1: float}
     */
    public static function wilsonInterval(int $successes, int $n, float $z): array
    {
        if ($n < 1) {
            return [0.0, 0.0];
        }

        $p = $successes / $n;
        $denominator = 1 + $z ** 2 / $n;
        $center = ($p + $z ** 2 / (2 * $n)) / $denominator;
        $half = $z * sqrt($p * (1 - $p) / $n + $z ** 2 / (4 * $n ** 2)) / $denominator;

        return [max(0.0, $center - $half), min(1.0, $center + $half)];
    }

    /**
     * z statistic for the difference of two proportions taken from the same sample (multinomial):
     * Var(p1 - p2) = (p1 + p2 - (p1 - p2)^2) / n.
     */
    public static function differenceZ(int $count1, int $count2, int $n): ?float
    {
        if ($n < 1) {
            return null;
        }

        $p1 = $count1 / $n;
        $p2 = $count2 / $n;
        $variance = ($p1 + $p2 - ($p1 - $p2) ** 2) / $n;

        if ($variance <= 0) {
            return null;
        }

        return ($p1 - $p2) / sqrt($variance);
    }

    /**
     * Regularized upper incomplete gamma function Q(a, x).
     */
    private static function upperIncompleteGamma(float $a, float $x): float
    {
        if ($x < $a + 1) {
            $sum = 1 / $a;
            $term = $sum;

            for ($i = 1; $i <= 500; $i++) {
                $term *= $x / ($a + $i);
                $sum += $term;

                if (abs($term) < abs($sum) * 1e-15) {
                    break;
                }
            }

            return max(0.0, min(1.0, 1 - $sum * exp(-$x + $a * log($x) - self::lnGamma($a))));
        }

        $tiny = 1e-300;
        $b = $x + 1 - $a;
        $c = 1 / $tiny;
        $d = 1 / $b;
        $h = $d;

        for ($i = 1; $i <= 500; $i++) {
            $an = -$i * ($i - $a);
            $b += 2;
            $d = $an * $d + $b;
            $d = abs($d) < $tiny ? $tiny : $d;
            $c = $b + $an / $c;
            $c = abs($c) < $tiny ? $tiny : $c;
            $d = 1 / $d;
            $delta = $d * $c;
            $h *= $delta;

            if (abs($delta - 1) < 1e-15) {
                break;
            }
        }

        return max(0.0, min(1.0, exp(-$x + $a * log($x) - self::lnGamma($a)) * $h));
    }

    /**
     * Natural log of the gamma function (Lanczos approximation, g = 7).
     */
    private static function lnGamma(float $x): float
    {
        if ($x < 0.5) {
            return log(M_PI / sin(M_PI * $x)) - self::lnGamma(1 - $x);
        }

        static $coefficients = [
            0.99999999999980993, 676.5203681218851, -1259.1392167224028, 771.32342877765313,
            -176.61502916214059, 12.507343278686905, -0.13857109526572012,
            9.9843695780195716e-6, 1.5056327351493116e-7,
        ];

        $x -= 1;
        $sum = $coefficients[0];
        $t = $x + 7.5;

        for ($i = 1; $i < 9; $i++) {
            $sum += $coefficients[$i] / ($x + $i);
        }

        return 0.5 * log(2 * M_PI) + ($x + 0.5) * log($t) - $t + log($sum);
    }
}
