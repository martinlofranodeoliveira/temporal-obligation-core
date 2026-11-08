<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation\Tests;

use InvalidArgumentException;
use Lofrano\TemporalObligation\Formula;

function test_horizon_calculation(): void
{
    $f1 = Formula::constant(true);
    assert_equals(0, $f1->horizon());

    $f2 = Formula::atom('req');
    assert_equals(0, $f2->horizon());

    $f3 = Formula::next($f2);
    assert_equals(1, $f3->horizon());

    $f4 = Formula::always(2, 8, $f3);
    assert_equals(9, $f4->horizon()); // 8 + 1 = 9

    $f5 = Formula::eventually(3, 15, $f4);
    assert_equals(24, $f5->horizon()); // 15 + 9 = 24

    $f6 = Formula::until(1, 10, Formula::atom('grant'), Formula::atom('release'));
    assert_equals(10, $f6->horizon());
}

function test_horizon_limit_rejection(): void
{
    assert_throws(static function (): void {
        Formula::always(0, 4096, Formula::atom('p'));
    }, InvalidArgumentException::class);

    $huge = Formula::always(0, 4000, Formula::always(0, 100, Formula::atom('p')));
    assert_throws(static function () use ($huge): void {
        $huge->validateAndLinearize();
    }, InvalidArgumentException::class);
}

function test_dag_cycle_detection(): void
{
    $cyclic = new class extends Formula {
        public function kind(): string { return 'cyclic'; }
        public function horizon(): int { return 0; }
        public function children(): array { return [$this]; }
        public function toArray(): array { return ['kind' => 'cyclic']; }
    };

    assert_throws(static function () use ($cyclic): void {
        $cyclic->validateAndLinearize();
    }, InvalidArgumentException::class);
}

function test_nesting_depth_limit(): void
{
    $curr = Formula::atom('leaf');
    for ($i = 0; $i < 35; $i++) {
        $curr = Formula::not($curr);
    }
    assert_throws(static function () use ($curr): void {
        $curr->validateAndLinearize();
    }, InvalidArgumentException::class);
}

function test_node_limit_rejection(): void
{
    $children = [];
    for ($i = 0; $i < 130; $i++) {
        $children[] = Formula::constant($i % 2 === 0);
    }
    $wide = Formula::and(...$children);
    assert_throws(static function () use ($wide): void {
        $wide->validateAndLinearize();
    }, InvalidArgumentException::class);
}
