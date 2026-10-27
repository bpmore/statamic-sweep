<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Sweeps;

use Bpmore\Sweep\Content\Scope;
use Bpmore\Sweep\Content\Tally;
use Bpmore\Sweep\Engine\Query;

/**
 * One sweep: the search that made it, where it looked, what it found, and
 * what has been done with it since. The items themselves are kept beside it
 * in chunks (see SweepStore), so this record stays small enough to read on
 * every page of the history.
 */
final class Sweep
{
    /**
     * @param  array{id: string, name: string}|null  $createdBy
     * @param  array<string, mixed>  $progress  phase, done, total
     * @param  array<string, mixed>  $applyTally
     * @param  array<string, mixed>  $undoTally
     * @param  array{id: string, name: string}|null  $appliedBy
     * @param  array{id: string, name: string}|null  $undoneBy
     */
    public function __construct(
        public readonly string $id,
        public Status $status,
        public readonly Query $query,
        public readonly Scope $scope,
        public readonly ?array $createdBy,
        public readonly string $createdAt,
        public Tally $tally = new Tally,
        public int $items = 0,
        public int $chunks = 0,
        public array $progress = [],
        public ?string $appliedAt = null,
        public ?array $appliedBy = null,
        public array $applyTally = [],
        public ?string $undoneAt = null,
        public ?array $undoneBy = null,
        public array $undoTally = [],
        public ?string $error = null,
        public bool $pruned = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'query' => $this->query->toArray(),
            'scope' => $this->scope->toArray(),
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'tally' => $this->tally->toArray(),
            'items' => $this->items,
            'chunks' => $this->chunks,
            'progress' => $this->progress,
            'applied_at' => $this->appliedAt,
            'applied_by' => $this->appliedBy,
            'apply_tally' => $this->applyTally,
            'undone_at' => $this->undoneAt,
            'undone_by' => $this->undoneBy,
            'undo_tally' => $this->undoTally,
            'error' => $this->error,
            'pruned' => $this->pruned,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            id: (string) $values['id'],
            status: Status::from((string) $values['status']),
            query: Query::fromArray((array) $values['query']),
            scope: Scope::fromArray((array) ($values['scope'] ?? [])),
            createdBy: $values['created_by'] ?? null,
            createdAt: (string) ($values['created_at'] ?? ''),
            tally: Tally::fromArray((array) ($values['tally'] ?? [])),
            items: (int) ($values['items'] ?? 0),
            chunks: (int) ($values['chunks'] ?? 0),
            progress: (array) ($values['progress'] ?? []),
            appliedAt: $values['applied_at'] ?? null,
            appliedBy: $values['applied_by'] ?? null,
            applyTally: (array) ($values['apply_tally'] ?? []),
            undoneAt: $values['undone_at'] ?? null,
            undoneBy: $values['undone_by'] ?? null,
            undoTally: (array) ($values['undo_tally'] ?? []),
            error: $values['error'] ?? null,
            pruned: (bool) ($values['pruned'] ?? false),
        );
    }
}
