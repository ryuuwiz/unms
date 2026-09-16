<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Str;
use Livewire\Attributes\Computed;

/**
 * Replacement for `<flux:select searchable>`, which is a no-op in the installed
 * Flux Free edition (see .ai/rules/flux-select-search.md). Hosts declare their
 * searchable fields via searchableFields(); markup lives in
 * resources/views/components/searchable-select.blade.php.
 */
trait HasSearchableOptions
{
    /** @var array<string, string> */
    public array $searchTerms = [];

    /** @var array<string, bool> */
    public array $searchOpen = [];

    /**
     * @return array<string, array{model: class-string, query: \Closure, label: \Closure, cap?: int}>
     */
    abstract protected function searchableFields(): array;

    /**
     * @return array<int, array{id: int, label: string}>
     */
    #[Computed]
    public function searchResults(string $field): array
    {
        $config = $this->searchableFields()[$field]
            ?? throw new \InvalidArgumentException("Field pencarian [{$field}] belum dikonfigurasi.");

        $term = trim($this->searchTerms[$field] ?? '');

        if ($term === '') {
            return [];
        }

        return $config['query']()
            ->search($term)
            ->limit($config['cap'] ?? 20)
            ->get()
            ->map(fn ($model) => ['id' => $model->getKey(), 'label' => $config['label']($model)])
            ->all();
    }

    public function searchableSelectedLabel(string $field): ?string
    {
        $config = $this->searchableFields()[$field] ?? null;
        $id = $this->{$field} ?? null;

        if ($config === null || ! $id) {
            return null;
        }

        $model = $config['model']::find($id);

        return $model ? $config['label']($model) : null;
    }

    public function openSearchable(string $field): void
    {
        $this->searchOpen[$field] = true;
        $this->searchTerms[$field] = '';
    }

    public function closeSearchable(string $field): void
    {
        $this->searchOpen[$field] = false;
    }

    public function selectSearchable(string $field, int $id): void
    {
        $this->{$field} = $id;
        $this->searchOpen[$field] = false;
        $this->searchTerms[$field] = '';

        $hook = 'updated'.Str::studly($field);

        if (method_exists($this, $hook)) {
            $this->{$hook}();
        }
    }
}
