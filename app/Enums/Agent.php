<?php

declare(strict_types=1);

namespace App\Enums;

enum Agent: string
{
    case ClaudeCode = 'claude';
    case Codex = 'codex';
    case Gemini = 'gemini';
    case OpenCode = 'opencode';
    case Cursor = 'cursor';

    public function label(): string
    {
        return match ($this) {
            self::ClaudeCode => 'Claude Code',
            self::Codex => 'Codex',
            self::Gemini => 'Gemini CLI',
            self::OpenCode => 'OpenCode',
            self::Cursor => 'Cursor',
        };
    }

    public function binary(): string
    {
        return match ($this) {
            self::ClaudeCode => 'claude',
            self::Codex => 'codex',
            self::Gemini => 'gemini',
            self::OpenCode => 'opencode',
            self::Cursor => 'cursor-agent',
        };
    }

    /**
     * @return list<string>
     */
    public function interactiveCommand(string $prompt): array
    {
        return match ($this) {
            self::Gemini => [$this->binary(), '--prompt-interactive', $prompt],
            self::OpenCode => [$this->binary(), '--prompt', $prompt],
            default => [$this->binary(), $prompt],
        };
    }
}
