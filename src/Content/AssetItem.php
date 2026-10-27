<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Content;

use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Auth\User;

/**
 * An asset's metadata: alt text, title and the container blueprint's other
 * fields. The file itself, its name and its path are never touched.
 *
 * Statamic 6 keeps one set of asset metadata for every site, so an asset
 * belongs to no one site. A sweep scoped to some sites leaves assets out
 * rather than change text every other site shows too (see Items).
 */
final class AssetItem implements Item
{
    public function __construct(private readonly Asset $asset) {}

    public static function referenceFor(string $id): string
    {
        return 'asset::'.$id;
    }

    public function reference(): string
    {
        return self::referenceFor((string) $this->asset->id());
    }

    public function kind(): string
    {
        return 'asset';
    }

    public function site(): ?string
    {
        return null;
    }

    public function title(): string
    {
        return (string) $this->asset->path();
    }

    public function editUrl(): ?string
    {
        return $this->asset->editUrl();
    }

    public function fields(FieldTree $tree): array
    {
        return $tree->for($this->asset->blueprint());
    }

    public function data(): array
    {
        return $this->asset->data()->all();
    }

    public function mayEdit(User $user): bool
    {
        return (bool) $user->can('edit', $this->asset);
    }

    public function write(array $data, string $message, ?User $user): void
    {
        $current = $this->data();

        foreach ($data as $handle => $value) {
            if (! array_key_exists($handle, $current) || $current[$handle] !== $value) {
                $this->asset->set($handle, $value);
            }
        }

        $this->asset->save();
    }
}
