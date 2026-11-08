<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation;

use InvalidArgumentException;

/**
 * Dense Boolean event trace with up to 32 atomic flags and 4096 sequential ticks.
 */
final class Trace
{
    public const int MAX_ATOMS = 32;
    public const int MAX_SAMPLES = 4096;

    /** @var array<string, int> Map of atom name to bit index (0..31) */
    private array $atomBits;

    /** @var int[] Nonnegative 32-bit integers, one per tick */
    private array $sampleMasks;

    public function __construct(array $atomNames, array $sampleMasks)
    {
        if (count($atomNames) > self::MAX_ATOMS) {
            throw new InvalidArgumentException("Trace exceeds maximum allowed atomic flags (32): " . count($atomNames));
        }
        if (count($sampleMasks) > self::MAX_SAMPLES) {
            throw new InvalidArgumentException("Trace exceeds maximum allowed sample ticks (4096): " . count($sampleMasks));
        }

        $this->atomBits = [];
        foreach ($atomNames as $index => $name) {
            if (!is_string($name) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,31}$/', $name)) {
                throw new InvalidArgumentException("Invalid atom name in trace: '{$name}'");
            }
            if (isset($this->atomBits[$name])) {
                throw new InvalidArgumentException("Duplicate atom name in trace: '{$name}'");
            }
            $this->atomBits[$name] = $index;
        }

        $this->sampleMasks = [];
        foreach ($sampleMasks as $mask) {
            if (!is_int($mask) || $mask < 0 || $mask > 0xFFFFFFFF) {
                throw new InvalidArgumentException("Invalid sample mask: {$mask}");
            }
            $this->sampleMasks[] = $mask;
        }
    }

    public static function fromSamples(array $atomNames, array $samples): self
    {
        $atomBits = [];
        foreach ($atomNames as $index => $name) {
            $atomBits[$name] = $index;
        }

        $masks = [];
        foreach ($samples as $tick => $sample) {
            if (!is_array($sample)) {
                throw new InvalidArgumentException("Sample at tick {$tick} must be an associative array");
            }
            $mask = 0;
            foreach ($sample as $atom => $val) {
                if (!isset($atomBits[$atom])) {
                    throw new InvalidArgumentException("Unknown atom '{$atom}' at tick {$tick}");
                }
                if ($val === true || $val === 1) {
                    $mask |= (1 << $atomBits[$atom]);
                }
            }
            $masks[] = $mask;
        }

        return new self($atomNames, $masks);
    }

    public static function fromArray(array $data): self
    {
        $atoms = $data['atoms'] ?? throw new InvalidArgumentException("Trace missing 'atoms'");
        if (isset($data['masks'])) {
            return new self($atoms, $data['masks']);
        }
        if (isset($data['samples'])) {
            return self::fromSamples($atoms, $data['samples']);
        }
        throw new InvalidArgumentException("Trace requires 'masks' or 'samples'");
    }

    public function length(): int
    {
        return count($this->sampleMasks);
    }

    public function isObserved(int $tick): bool
    {
        return $tick >= 0 && $tick < count($this->sampleMasks);
    }

    public function hasAtom(string $atom): bool
    {
        return isset($this->atomBits[$atom]);
    }

    public function atomBit(string $atom): ?int
    {
        return $this->atomBits[$atom] ?? null;
    }

    /**
     * Evaluates an atomic proposition at a given tick.
     * Returns TRUE or FALSE if the tick is within the observed trace,
     * or UNKNOWN_FUTURE if the tick is beyond the trace length.
     */
    public function evaluateAtom(string $atom, int $tick): int
    {
        if ($tick < 0) {
            throw new InvalidArgumentException("Negative tick: {$tick}");
        }
        if (!isset($this->atomBits[$atom])) {
            throw new InvalidArgumentException("Atom '{$atom}' not declared in trace schema");
        }
        if ($tick >= count($this->sampleMasks)) {
            return Trit::UNKNOWN_FUTURE;
        }

        $bit = $this->atomBits[$atom];
        return (($this->sampleMasks[$tick] & (1 << $bit)) !== 0) ? Trit::TRUE : Trit::FALSE;
    }

    public function toArray(): array
    {
        return [
            'atoms' => array_keys($this->atomBits),
            'length' => count($this->sampleMasks),
            'masks' => $this->sampleMasks,
        ];
    }
}
