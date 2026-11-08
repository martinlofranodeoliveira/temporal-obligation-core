<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation;

use InvalidArgumentException;

/**
 * Immutable AST nodes representing propositional and bounded temporal formulas.
 */
abstract class Formula
{
    public const int MAX_NODES = 128;
    public const int MAX_DEPTH = 32;
    public const int MAX_HORIZON = 4095;
    public const int MAX_INTERVAL_TICK = 4095;

    public int $id = -1; // Assigned during DAG linearization

    abstract public function kind(): string;
    abstract public function horizon(): int;
    abstract public function children(): array;
    abstract public function toArray(): array;

    public static function constant(bool $value): Formula
    {
        return new ConstantFormula($value);
    }

    public static function atom(string $name): Formula
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,31}$/', $name)) {
            throw new InvalidArgumentException("Invalid atomic flag name: '{$name}'");
        }
        return new AtomFormula($name);
    }

    public static function not(Formula $child): Formula
    {
        return new NotFormula($child);
    }

    public static function and(Formula ...$children): Formula
    {
        if (count($children) === 0) {
            throw new InvalidArgumentException("And formula requires at least one child");
        }
        return new AndFormula($children);
    }

    public static function or(Formula ...$children): Formula
    {
        if (count($children) === 0) {
            throw new InvalidArgumentException("Or formula requires at least one child");
        }
        return new OrFormula($children);
    }

    public static function next(Formula $child): Formula
    {
        return new NextFormula($child);
    }

    public static function always(int $a, int $b, Formula $child): Formula
    {
        self::validateInterval($a, $b);
        return new AlwaysFormula($a, $b, $child);
    }

    public static function eventually(int $a, int $b, Formula $child): Formula
    {
        self::validateInterval($a, $b);
        return new EventuallyFormula($a, $b, $child);
    }

    public static function until(int $a, int $b, Formula $left, Formula $right): Formula
    {
        self::validateInterval($a, $b);
        return new UntilFormula($a, $b, $left, $right);
    }

    private static function validateInterval(int $a, int $b): void
    {
        if ($a < 0 || $b < $a || $b > self::MAX_INTERVAL_TICK) {
            throw new InvalidArgumentException("Invalid temporal interval: [{$a}, {$b}]. Require 0 <= a <= b <= 4095.");
        }
    }

    /**
     * Parses a formula DAG from a JSON-serializable associative array.
     */
    public static function fromArray(array $data): Formula
    {
        $kind = $data['kind'] ?? throw new InvalidArgumentException("Formula missing 'kind'");
        return match ($kind) {
            'constant' => self::constant((bool)($data['value'] ?? throw new InvalidArgumentException("Constant missing 'value'"))),
            'atom' => self::atom((string)($data['name'] ?? throw new InvalidArgumentException("Atom missing 'name'"))),
            'not' => self::not(self::fromArray($data['child'] ?? throw new InvalidArgumentException("Not missing 'child'"))),
            'and' => self::and(...array_map(self::fromArray(...), $data['children'] ?? throw new InvalidArgumentException("And missing 'children'"))),
            'or' => self::or(...array_map(self::fromArray(...), $data['children'] ?? throw new InvalidArgumentException("Or missing 'children'"))),
            'next' => self::next(self::fromArray($data['child'] ?? throw new InvalidArgumentException("Next missing 'child'"))),
            'always' => self::always(
                (int)($data['a'] ?? throw new InvalidArgumentException("Always missing 'a'")),
                (int)($data['b'] ?? throw new InvalidArgumentException("Always missing 'b'")),
                self::fromArray($data['child'] ?? throw new InvalidArgumentException("Always missing 'child'"))
            ),
            'eventually' => self::eventually(
                (int)($data['a'] ?? throw new InvalidArgumentException("Eventually missing 'a'")),
                (int)($data['b'] ?? throw new InvalidArgumentException("Eventually missing 'b'")),
                self::fromArray($data['child'] ?? throw new InvalidArgumentException("Eventually missing 'child'"))
            ),
            'until' => self::until(
                (int)($data['a'] ?? throw new InvalidArgumentException("Until missing 'a'")),
                (int)($data['b'] ?? throw new InvalidArgumentException("Until missing 'b'")),
                self::fromArray($data['left'] ?? throw new InvalidArgumentException("Until missing 'left'")),
                self::fromArray($data['right'] ?? throw new InvalidArgumentException("Until missing 'right'"))
            ),
            default => throw new InvalidArgumentException("Unknown formula kind: '{$kind}'"),
        };
    }

    /**
     * Validates DAG properties: acyclicity, node limits, nesting depth, and lookahead horizon.
     * Returns ordered list of unique nodes in post-order (leaves to root).
     *
     * @return Formula[]
     */
    public function validateAndLinearize(): array
    {
        $visited = []; // spl_object_id => state (1 = in progress, 2 = done)
        $depth = 0;
        $ordered = [];

        $this->dfsValidate($this, $visited, $depth, $ordered);

        if (count($ordered) > self::MAX_NODES) {
            throw new InvalidArgumentException("Formula DAG exceeds maximum node limit (128): " . count($ordered));
        }

        if ($this->horizon() > self::MAX_HORIZON) {
            throw new InvalidArgumentException("Formula lookahead horizon ({$this->horizon()}) exceeds maximum (4095)");
        }

        // Assign linear IDs from 0 to count-1
        foreach ($ordered as $index => $node) {
            $node->id = $index;
        }

        return $ordered;
    }

    private function dfsValidate(Formula $node, array &$visited, int $currentDepth, array &$ordered): void
    {
        if ($currentDepth > self::MAX_DEPTH) {
            throw new InvalidArgumentException("Formula nesting depth exceeds maximum (32)");
        }

        $oid = spl_object_id($node);
        $state = $visited[$oid] ?? 0;
        if ($state === 1) {
            throw new InvalidArgumentException("Formula graph contains a cycle (must be a DAG)");
        }
        if ($state === 2) {
            return;
        }

        $visited[$oid] = 1;
        foreach ($node->children() as $child) {
            $this->dfsValidate($child, $visited, $currentDepth + 1, $ordered);
        }
        $visited[$oid] = 2;
        $ordered[] = $node;
    }

    /**
     * Extracts all unique atomic flag names referenced in the formula DAG.
     *
     * @return string[]
     */
    public function referencedAtoms(): array
    {
        $atoms = [];
        $stack = [$this];
        $seen = [];
        while (!empty($stack)) {
            $curr = array_pop($stack);
            $oid = spl_object_id($curr);
            if (isset($seen[$oid])) {
                continue;
            }
            $seen[$oid] = true;
            if ($curr instanceof AtomFormula) {
                $atoms[$curr->name] = true;
            }
            foreach ($curr->children() as $child) {
                $stack[] = $child;
            }
        }
        return array_keys($atoms);
    }
}

final class ConstantFormula extends Formula
{
    public function __construct(public readonly bool $value) {}
    public function kind(): string { return 'constant'; }
    public function horizon(): int { return 0; }
    public function children(): array { return []; }
    public function toArray(): array { return ['kind' => 'constant', 'value' => $this->value]; }
}

final class AtomFormula extends Formula
{
    public function __construct(public readonly string $name) {}
    public function kind(): string { return 'atom'; }
    public function horizon(): int { return 0; }
    public function children(): array { return []; }
    public function toArray(): array { return ['kind' => 'atom', 'name' => $this->name]; }
}

final class NotFormula extends Formula
{
    public function __construct(public readonly Formula $child) {}
    public function kind(): string { return 'not'; }
    public function horizon(): int { return $this->child->horizon(); }
    public function children(): array { return [$this->child]; }
    public function toArray(): array { return ['kind' => 'not', 'child' => $this->child->toArray()]; }
}

final class AndFormula extends Formula
{
    /** @param Formula[] $children */
    public function __construct(public readonly array $children) {}
    public function kind(): string { return 'and'; }
    public function horizon(): int
    {
        $max = 0;
        foreach ($this->children as $child) {
            $max = max($max, $child->horizon());
        }
        return $max;
    }
    public function children(): array { return $this->children; }
    public function toArray(): array
    {
        return ['kind' => 'and', 'children' => array_map(static fn(Formula $c) => $c->toArray(), $this->children)];
    }
}

final class OrFormula extends Formula
{
    /** @param Formula[] $children */
    public function __construct(public readonly array $children) {}
    public function kind(): string { return 'or'; }
    public function horizon(): int
    {
        $max = 0;
        foreach ($this->children as $child) {
            $max = max($max, $child->horizon());
        }
        return $max;
    }
    public function children(): array { return $this->children; }
    public function toArray(): array
    {
        return ['kind' => 'or', 'children' => array_map(static fn(Formula $c) => $c->toArray(), $this->children)];
    }
}

final class NextFormula extends Formula
{
    public function __construct(public readonly Formula $child) {}
    public function kind(): string { return 'next'; }
    public function horizon(): int { return 1 + $this->child->horizon(); }
    public function children(): array { return [$this->child]; }
    public function toArray(): array { return ['kind' => 'next', 'child' => $this->child->toArray()]; }
}

final class AlwaysFormula extends Formula
{
    public function __construct(public readonly int $a, public readonly int $b, public readonly Formula $child) {}
    public function kind(): string { return 'always'; }
    public function horizon(): int { return $this->b + $this->child->horizon(); }
    public function children(): array { return [$this->child]; }
    public function toArray(): array
    {
        return ['kind' => 'always', 'a' => $this->a, 'b' => $this->b, 'child' => $this->child->toArray()];
    }
}

final class EventuallyFormula extends Formula
{
    public function __construct(public readonly int $a, public readonly int $b, public readonly Formula $child) {}
    public function kind(): string { return 'eventually'; }
    public function horizon(): int { return $this->b + $this->child->horizon(); }
    public function children(): array { return [$this->child]; }
    public function toArray(): array
    {
        return ['kind' => 'eventually', 'a' => $this->a, 'b' => $this->b, 'child' => $this->child->toArray()];
    }
}

final class UntilFormula extends Formula
{
    public function __construct(
        public readonly int $a,
        public readonly int $b,
        public readonly Formula $left,
        public readonly Formula $right
    ) {}
    public function kind(): string { return 'until'; }
    public function horizon(): int
    {
        // left is checked up to b-1, right at b
        return max($this->b + $this->left->horizon(), $this->b + $this->right->horizon());
    }
    public function children(): array { return [$this->left, $this->right]; }
    public function toArray(): array
    {
        return [
            'kind' => 'until',
            'a' => $this->a,
            'b' => $this->b,
            'left' => $this->left->toArray(),
            'right' => $this->right->toArray(),
        ];
    }
}
