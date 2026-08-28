<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Models\UserTablePreference;

class TableColumnPreferenceService
{
    public const VIEWPORTS = ['desktop', 'mobile'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allDefinitions(): array
    {
        return config('table-columns', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function definition(string $tableKey): ?array
    {
        return config("table-columns.{$tableKey}");
    }

    /**
     * @return list<array{key: string, label: string, default: bool, locked: bool, optional: bool}>
     */
    public function columnsFor(string $tableKey): array
    {
        $definition = $this->definition($tableKey);
        if (! $definition) {
            return [];
        }

        return $definition['columns'] ?? [];
    }

    /**
     * @return array{order: list<string>, visible: array<string, bool>, widths: array<string, int|null>}
     */
    public function defaultConfig(string $tableKey, string $viewport = 'desktop'): array
    {
        $adminDefault = $this->adminDefaultConfig($tableKey, $viewport);
        if ($adminDefault !== null) {
            return $this->normalizeConfig($tableKey, $adminDefault);
        }

        $columns = $this->columnsFor($tableKey);
        $order = [];
        $visible = [];
        $widths = [];

        foreach ($columns as $column) {
            $key = $column['key'];
            $order[] = $key;
            $visible[$key] = (bool) ($column['default'] ?? true);
            $widths[$key] = $column['width'] ?? null;
        }

        return [
            'order' => $order,
            'visible' => $visible,
            'widths' => $widths,
        ];
    }

    /**
     * @return array{order: list<string>, visible: array<string, bool>, widths: array<string, int|null>}|null
     */
    public function adminDefaultConfig(string $tableKey, string $viewport = 'desktop'): ?array
    {
        $raw = Setting::get("table_defaults.{$tableKey}.{$viewport}");
        if ($raw === null || $raw === '') {
            $raw = Setting::get("table_defaults.{$tableKey}");
        }

        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                return null;
            }

            return $this->normalizeConfig($tableKey, $decoded);
        }

        return is_array($raw) ? $this->normalizeConfig($tableKey, $raw) : null;
    }

    /**
     * @return array{order: list<string>, visible: array<string, bool>, widths: array<string, int|null>}
     */
    public function userConfig(?User $user, string $tableKey, string $viewport = 'desktop'): array
    {
        $defaults = $this->defaultConfig($tableKey, $viewport);

        if (! $user) {
            return $defaults;
        }

        $preference = UserTablePreference::query()
            ->where('user_id', $user->id)
            ->where('table_key', $tableKey)
            ->where('viewport', $viewport)
            ->first();

        if (! $preference || ! is_array($preference->config)) {
            return $defaults;
        }

        return $this->mergeWithDefaults($tableKey, $defaults, $preference->config);
    }

    /**
     * @param  array{order?: list<string>, visible?: array<string, bool>, widths?: array<string, int|null>}  $config
     * @return array{order: list<string>, visible: array<string, bool>, widths: array<string, int|null>}
     */
    public function saveUserConfig(User $user, string $tableKey, array $config, string $viewport = 'desktop'): array
    {
        $normalized = $this->normalizeConfig($tableKey, $config);

        UserTablePreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'table_key' => $tableKey,
                'viewport' => $viewport,
            ],
            ['config' => $normalized]
        );

        return $normalized;
    }

    /**
     * @param  array{order?: list<string>, visible?: array<string, bool>, widths?: array<string, int|null>}  $config
     * @return array{order: list<string>, visible: array<string, bool>, widths: array<string, int|null>}
     */
    public function saveAdminDefault(User $user, string $tableKey, array $config, string $viewport = 'desktop'): array
    {
        abort_unless($user->isSuperAdmin(), 403);

        $normalized = $this->normalizeConfig($tableKey, $config);
        Setting::set(
            "table_defaults.{$tableKey}.{$viewport}",
            json_encode($normalized, JSON_UNESCAPED_UNICODE),
            "Configuration par défaut des colonnes pour {$tableKey} ({$viewport})"
        );

        return $normalized;
    }

    /**
     * @param  list<string>  $visibleColumnKeys
     * @return list<string>
     */
    public function exportFieldsForVisible(string $tableKey, array $visibleColumnKeys): array
    {
        if ($visibleColumnKeys === []) {
            return [];
        }

        $fields = [];
        foreach ($this->columnsFor($tableKey) as $column) {
            $key = $column['key'];
            $exportField = $column['export_field'] ?? null;

            if ($exportField && in_array($key, $visibleColumnKeys, true)) {
                $fields[] = $exportField;
            }
        }

        return array_values(array_unique($fields));
    }

    /**
     * @return array{
     *     table_key: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, default: bool, locked: bool, optional: bool}>,
     *     desktop: array{order: list<string>, visible: array<string, bool>, widths: array<string, int|null>},
     *     mobile: array{order: list<string>, visible: array<string, bool>, widths: array<string, int|null>},
     *     can_edit_defaults: bool
     * }
     */
    public function payloadForPage(?User $user, string $tableKey): array
    {
        $definition = $this->definition($tableKey) ?? [
            'label' => $tableKey,
            'columns' => [],
        ];

        return [
            'table_key' => $tableKey,
            'label' => $definition['label'] ?? $tableKey,
            'columns' => $this->columnsFor($tableKey),
            'desktop' => $this->userConfig($user, $tableKey, 'desktop'),
            'mobile' => $this->userConfig($user, $tableKey, 'mobile'),
            'can_edit_defaults' => $user?->isSuperAdmin() ?? false,
        ];
    }

    /**
     * @param  array{order?: list<string>, visible?: array<string, bool>, widths?: array<string, int|null>}  $config
     * @return array{order: list<string>, visible: array<string, bool>, widths: array<string, int|null>}
     */
    public function normalizeConfig(string $tableKey, array $config): array
    {
        $columns = $this->columnsFor($tableKey);
        $knownKeys = array_column($columns, 'key');
        $locked = collect($columns)->filter(fn ($c) => $c['locked'] ?? false)->pluck('key')->all();

        $order = array_values(array_unique(array_filter(
            $config['order'] ?? [],
            fn ($key) => in_array($key, $knownKeys, true)
        )));

        foreach ($knownKeys as $key) {
            if (! in_array($key, $order, true)) {
                $order[] = $key;
            }
        }

        $visible = [];
        foreach ($columns as $column) {
            $key = $column['key'];
            $isLocked = $column['locked'] ?? false;
            $visible[$key] = $isLocked
                ? true
                : (bool) (($config['visible'][$key] ?? null) ?? ($column['default'] ?? true));
        }

        $widths = [];
        foreach ($columns as $column) {
            $key = $column['key'];
            $width = $config['widths'][$key] ?? ($column['width'] ?? null);
            $widths[$key] = is_numeric($width) ? (int) $width : null;
        }

        return [
            'order' => $order,
            'visible' => $visible,
            'widths' => $widths,
        ];
    }

    /**
     * @param  array{order: list<string>, visible: array<string, bool>, widths: array<string, int|null>}  $defaults
     * @param  array{order?: list<string>, visible?: array<string, bool>, widths?: array<string, int|null>}  $saved
     * @return array{order: list<string>, visible: array<string, bool>, widths: array<string, int|null>}
     */
    protected function mergeWithDefaults(string $tableKey, array $defaults, array $saved): array
    {
        return $this->normalizeConfig($tableKey, [
            'order' => $saved['order'] ?? $defaults['order'],
            'visible' => array_merge($defaults['visible'], $saved['visible'] ?? []),
            'widths' => array_merge($defaults['widths'], $saved['widths'] ?? []),
        ]);
    }
}
