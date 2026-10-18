<?php

declare(strict_types=1);

namespace App\Enums;

enum Kit: string
{
    case Vue = 'vue';
    case React = 'react';
    case Svelte = 'svelte';
    case Livewire = 'livewire';
    case Api = 'api';
    case Blade = 'blade';

    public static function fromPackage(string $package): ?self
    {
        foreach (self::cases() as $kit) {
            if ($kit->package() === $package) {
                return $kit;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $kit) {
            $options[$kit->value] = "{$kit->label()} ({$kit->description()})";
        }

        return $options;
    }

    public function label(): string
    {
        return match ($this) {
            self::Vue => 'Vue',
            self::React => 'React',
            self::Svelte => 'Svelte',
            self::Livewire => 'Livewire',
            self::Api => 'API',
            self::Blade => 'Blade',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Vue => 'Inertia, Vue 3 and shadcn-vue',
            self::React => 'Inertia, React 19 and shadcn/ui',
            self::Svelte => 'Inertia, Svelte 5 and shadcn-svelte',
            self::Livewire => 'Livewire 4 and Flux',
            self::Api => 'Sanctum tokens and JSON resources',
            self::Blade => 'Blade views and Tailwind CSS',
        };
    }

    public function package(): string
    {
        return "shire-studio/laravel-starter-kit-{$this->value}";
    }

    public function hasFrontend(): bool
    {
        return $this !== self::Api;
    }

    public function generatesTypes(): bool
    {
        return in_array($this, [self::Vue, self::React, self::Svelte], true);
    }
}
