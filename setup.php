<?php

use PluginGlurbackup\Plugin as GlurbackupPlugin;

define('PLUGIN_GLURBACKUP_VERSION', '1.0.4');
define('PLUGIN_GLURBACKUP_MIN_GLPI', '11.0.0');
define('PLUGIN_GLURBACKUP_MAX_GLPI', '11.0.99');

spl_autoload_register(static function (string $class): void {
    $prefix  = 'PluginGlurbackup\\';
    $baseDir = __DIR__ . '/src/';
    $len     = strlen($prefix);

    // Classi namespaced del plugin
    if (strncmp($prefix, $class, $len) === 0) {
        $relativeClass = substr($class, $len);
        $file          = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($file)) {
            require_once $file;
        }
        return;
    }

    // Wrapper legacy globale per Server
    if ($class === 'PluginGlurbackupServer') {
        $file = $baseDir . 'Server.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }

    // Wrapper legacy globale per Profile
    if ($class === 'PluginGlurbackupProfile') {
        $file = $baseDir . 'Profile.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }

    // Wrapper legacy globale per ComputerTab
    if ($class === 'PluginGlurbackupComputerTab') {
        $file = $baseDir . 'ComputerTab.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }

    // Wrapper legacy globale per MassiveActionComputer
    if ($class === 'PluginGlurbackupMassiveActionComputer') {
        $file = $baseDir . 'MassiveActionComputer.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }
});

function plugin_init_glurbackup(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['glurbackup'] = true;
    $PLUGIN_HOOKS['change_profile']['glurbackup'] = true;
    $PLUGIN_HOOKS['config_page']['glurbackup']    = 'front/config.php';

    // Menu amministrativo plugin
    $PLUGIN_HOOKS['menu_toadd']['glurbackup'] = ['admin' => 'PluginGlurbackupServer'];

    // Massive actions
    $PLUGIN_HOOKS['use_massive_action']['glurbackup'] = 1;

    // Rendering valori custom nelle liste/search
    $PLUGIN_HOOKS['giveItem']['glurbackup'] = 'plugin_glurbackup_giveItem';

    if (class_exists('\\Plugin')) {
        // Tab sui profili
        \Plugin::registerClass('PluginGlurbackupProfile', [
            'addtabon' => ['Profile'],
        ]);

        // Tab su Computer
        \Plugin::registerClass('PluginGlurbackupComputerTab', [
            'addtabon' => ['Computer'],
        ]);
    }
}

function plugin_version_glurbackup(): array
{
    return [
        'name'         => __('Glurbackup', 'glurbackup'),
        'version'      => PLUGIN_GLURBACKUP_VERSION,
        'author'       => 'Mariano Benzi',
        'license'      => 'GPLv2+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_GLURBACKUP_MIN_GLPI,
                'max' => PLUGIN_GLURBACKUP_MAX_GLPI,
            ],
            'php'  => [
                'min'  => '8.2',
                'max'  => '8.5',
                'exts' => [
                    'curl' => ['required' => true],
                    'json' => ['required' => true],
                ],
            ],
        ],
    ];
}

function plugin_glurbackup_check_prerequisites(): bool
{
    return true;
}

function plugin_glurbackup_check_config(bool $verbose = false): bool
{
    return true;
}

function plugin_glurbackup_getProfileRight(): array
{
    return [
        GlurbackupPlugin::RIGHTNAME => __('Glurbackup', 'glurbackup'),
    ];
}

function plugin_glurbackup_getProfileRights(): array
{
    return plugin_glurbackup_getProfileRight();
}
