<?php

namespace PluginGlurbackup;

final class UrbackupApiFactory
{
    public static function createFromServer(array $server): UrbackupApiInterface
    {
        $version = (string) ($server['version'] ?? '2.5');

        return match ($version) {
            '2.4'   => new UrbackupApiV24(),
            '2.5'   => new UrbackupApiV25(),
            default => new UrbackupApiOther(),
        };
    }

    public static function getChoices(): array
    {
        return [
            '2.4'   => '2.4',
            '2.5'   => '2.5',
            'other' => __('Other', 'glurbackup'),
        ];
    }

    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        return match ($value) {
            '2.4', '2.5', 'other' => $value,
            default               => '2.5',
        };
    }
}
