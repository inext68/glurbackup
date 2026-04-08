<?php

namespace PluginGlurbackup;

interface UrbackupApiInterface
{
    public function getVersionKey(): string;
    public function getLabel(): string;
    public function buildBaseUrl(array $server): string;
    public function getComputerOverview(array $server, string $computerName, ?string $computerIp = null): array;
    public function getInternetSettings(array $server, string $computerName, ?string $computerIp = null): array;
    public function saveInternetMode(array $server, string $computerName, ?string $computerIp, bool $enabled): array;
}
