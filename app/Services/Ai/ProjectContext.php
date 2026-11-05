<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Project;
use App\Models\Setting;

/**
 * Project-level context that every Kiki turn sees.
 *
 * AGENTS.md is written by the site-study agent and edited by the owner. It is
 * the durable memory of the project; the chat prompt only carries a bounded
 * slice so a long instruction file cannot eat the context window.
 */
class ProjectContext
{
    private const AGENTS_LIMIT = 7000;

    private const INSTRUCTIONS_LIMIT = 2500;

    public function siteUrl(): ?string
    {
        $url = trim((string) $this->value('project_site_url'));

        return $url !== '' ? $url : null;
    }

    public function agentsMd(): string
    {
        return trim((string) $this->value('project_agents_md'));
    }

    public function instructions(): string
    {
        return trim((string) $this->value('project_instructions'));
    }

    public function studiedAt(): ?string
    {
        $value = trim((string) $this->value('project_studied_at'));

        return $value !== '' ? $value : null;
    }

    /**
     * Bounded prompt block for a concrete project. Falls back to the legacy
     * global settings when the conversation is not scoped to a project.
     */
    public function promptFor(?Project $project): string
    {
        if (! $project instanceof Project) {
            return $this->prompt();
        }

        $parts = ["Проект: {$project->name} ({$project->key})"];

        if ($project->client !== null) {
            $parts[] = "Клиент проекта: {$project->client->name}";
        }

        if (filled($project->site_url)) {
            $parts[] = "Сайт проекта: {$project->site_url}";
        }

        if (filled($project->agents_md)) {
            $parts[] = "Инструкция проекта (AGENTS.md):\n".$this->truncate((string) $project->agents_md, self::AGENTS_LIMIT);
        }

        if (filled($project->instructions)) {
            $parts[] = "Указания владельца по проекту:\n".$this->truncate((string) $project->instructions, self::INSTRUCTIONS_LIMIT);
        }

        return implode("\n\n", $parts);
    }

    /**
     * Bounded prompt block. Headings survive truncation, so the model keeps the
     * document skeleton even when the body does not fit.
     */
    public function prompt(): string
    {
        $parts = [];

        if (($url = $this->siteUrl()) !== null) {
            $parts[] = "Сайт проекта: {$url}";
        }

        if (($agents = $this->agentsMd()) !== '') {
            $parts[] = "Инструкция проекта (AGENTS.md):\n".$this->truncate($agents, self::AGENTS_LIMIT);
        }

        if (($instructions = $this->instructions()) !== '') {
            $parts[] = "Указания владельца:\n".$this->truncate($instructions, self::INSTRUCTIONS_LIMIT);
        }

        return implode("\n\n", $parts);
    }

    public function truncate(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $headings = [];

        foreach (preg_split('/\n/u', $text) ?: [] as $line) {
            if (preg_match('/^#{1,3}\s/u', $line) === 1) {
                $headings[] = $line;
            }
        }

        $head = mb_substr($text, 0, (int) ($limit * 0.6));
        $skeleton = mb_substr(implode("\n", $headings), 0, (int) ($limit * 0.4));

        return $head."\n…\n".$skeleton;
    }

    private function value(string $key): string
    {
        return (string) (Setting::query()->where('key', $key)->value('value') ?? '');
    }
}
