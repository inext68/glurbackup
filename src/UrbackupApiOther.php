<?php

namespace PluginGlurbackup;

class UrbackupApiOther extends UrbackupApiV24
{
    public function getVersionKey(): string
    {
        return 'other';
    }

    public function getLabel(): string
    {
        return 'URBackup other';
    }
}
