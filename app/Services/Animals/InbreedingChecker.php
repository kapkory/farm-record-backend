<?php

namespace App\Services\Animals;

use App\Models\Core\Animal;

/**
 * Non-blocking relationship check run before a mating is recorded. Walks a
 * few generations of recorded parentage (dam_id / sire_id) for both animals
 * and flags close relationships — parent/offspring, full or half siblings,
 * grandparent, or any shared ancestor — so the farmer can reconsider.
 *
 * It only sees relationships the farmer has actually recorded; an empty
 * pedigree simply returns "not related".
 */
class InbreedingChecker
{
    /** How many generations of ancestors to consider. */
    protected const MAX_DEPTH = 4;

    /**
     * @return array{related: bool, severity: string|null, warnings: string[], relationship: string|null}
     */
    public function check(Animal $dam, Animal $sire): array
    {
        if ($dam->id === $sire->id) {
            return $this->result(true, 'high', 'same animal', 'You selected the same animal as mother and father.');
        }

        // Direct parent ↔ offspring.
        if ($dam->dam_id === $sire->id || $dam->sire_id === $sire->id) {
            return $this->result(true, 'high', 'parent-offspring', 'These two are parent and offspring.');
        }
        if ($sire->dam_id === $dam->id || $sire->sire_id === $dam->id) {
            return $this->result(true, 'high', 'parent-offspring', 'These two are parent and offspring.');
        }

        $damAncestors = $this->ancestors($dam);
        $sireAncestors = $this->ancestors($sire);

        // One is an ancestor of the other (grandparent etc.).
        if (isset($sireAncestors[$dam->id]) || isset($damAncestors[$sire->id])) {
            return $this->result(true, 'high', 'direct-ancestor', 'One of these animals is a direct ancestor of the other.');
        }

        // Shared parents → full or half siblings.
        $sharedParents = array_values(array_intersect(
            array_filter([$dam->dam_id, $dam->sire_id]),
            array_filter([$sire->dam_id, $sire->sire_id])
        ));

        if (count($sharedParents) >= 2) {
            return $this->result(true, 'high', 'full-siblings', 'These two are full siblings (same mother and father).');
        }
        if (count($sharedParents) === 1) {
            return $this->result(true, 'medium', 'half-siblings', 'These two share a parent (half siblings).');
        }

        // Any shared ancestor further back (cousins, etc.).
        $commonAncestors = array_intersect_key($damAncestors, $sireAncestors);
        if (! empty($commonAncestors)) {
            $name = reset($commonAncestors);

            return $this->result(true, 'low', 'shared-ancestor', "These two share a common ancestor ({$name}). Mating them raises inbreeding risk.");
        }

        return $this->result(false, null, null, null);
    }

    /**
     * Map of ancestorId => label for an animal, up to MAX_DEPTH generations.
     *
     * @return array<int, string>
     */
    protected function ancestors(Animal $animal, int $depth = 0, array $seen = []): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return [];
        }

        $result = [];

        foreach ([$animal->dam_id, $animal->sire_id] as $parentId) {
            if (! $parentId || isset($seen[$parentId])) {
                continue;
            }

            $seen[$parentId] = true;
            $parent = Animal::select(['id', 'name', 'tag_number', 'dam_id', 'sire_id'])->find($parentId);

            if (! $parent) {
                continue;
            }

            $result[$parent->id] = $parent->name ?: ($parent->tag_number ?: "Animal #{$parent->id}");
            $result += $this->ancestors($parent, $depth + 1, $seen);
        }

        return $result;
    }

    protected function result(bool $related, ?string $severity, ?string $relationship, ?string $warning): array
    {
        return [
            'related' => $related,
            'severity' => $severity,
            'relationship' => $relationship,
            'warnings' => $warning ? [$warning] : [],
        ];
    }
}
