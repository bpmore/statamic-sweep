<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Content;

use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Globals\Variables;

/**
 * A global set's values in one site.
 */
final class GlobalItem implements Item
{
    public function __construct(private readonly Variables $variables) {}

    public static function referenceFor(string $handle, string $site): string
    {
        return 'global::'.$handle.'::'.$site;
    }

    public function reference(): string
    {
        return self::referenceFor((string) $this->variables->handle(), (string) $this->variables->locale());
    }

    public function kind(): string
    {
        return 'global';
    }

    public function site(): ?string
    {
        return $this->variables->locale();
    }

    public function title(): string
    {
        return (string) $this->variables->globalSet()->title();
    }

    public function editUrl(): ?string
    {
        return $this->variables->editUrl();
    }

    public function fields(FieldTree $tree): array
    {
        $blueprint = $this->variables->blueprint();

        return $blueprint ? $tree->for($blueprint) : [];
    }

    public function data(): array
    {
        return $this->variables->data()->all();
    }

    public function mayEdit(User $user): bool
    {
        return (bool) $user->can('edit', $this->variables);
    }

    public function write(array $data, string $message, ?User $user): void
    {
        $current = $this->data();

        foreach ($data as $handle => $value) {
            if (! array_key_exists($handle, $current) || $current[$handle] !== $value) {
                $this->variables->set($handle, $value);
            }
        }

        $this->variables->save();
    }
}
