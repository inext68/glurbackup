<?php

namespace PluginGlurbackup;

class UrbackupApiV25 extends UrbackupApiV24
{
    public function getVersionKey(): string
    {
        return '2.5';
    }

    public function getLabel(): string
    {
        return 'URBackup 2.5';
    }
}
