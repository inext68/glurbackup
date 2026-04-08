<?php

namespace PluginGlurbackup;

use CronTask;
use DBConnection;
use Migration;
use Profile;

final class Install
{
    public function doInstall(): bool
    {
        global $DB;

        $charset   = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();
        $migration = new Migration(PLUGIN_GLURBACKUP_VERSION);

        $tables = [
            'glpi_plugin_glurbackup_servers' => "
                CREATE TABLE `glpi_plugin_glurbackup_servers` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
                    `is_recursive` TINYINT NOT NULL DEFAULT 0,
                    `name` VARCHAR(255) NOT NULL,
                    `ip_address` VARCHAR(255) NOT NULL,
                    `port` INT UNSIGNED NOT NULL DEFAULT 55414,
                    `version` VARCHAR(10) NOT NULL DEFAULT '2.5',
                    `locations_id` INT UNSIGNED NOT NULL DEFAULT 0,
                    `username` VARCHAR(255) NOT NULL DEFAULT '',
                    `password` TEXT DEFAULT NULL,
                    `protocol` VARCHAR(10) NOT NULL DEFAULT 'http',
                    `ignore_ssl` TINYINT NOT NULL DEFAULT 0,
                    `is_default` TINYINT NOT NULL DEFAULT 0,
                    `comment` TEXT DEFAULT NULL,
                    `date_mod` TIMESTAMP NULL DEFAULT NULL,
                    `date_creation` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `entities_id` (`entities_id`),
                    KEY `locations_id` (`locations_id`),
                    KEY `is_default` (`is_default`),
                    KEY `version` (`version`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",

            'glpi_plugin_glurbackup_computers_servers' => "
                CREATE TABLE `glpi_plugin_glurbackup_computers_servers` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `computers_id` INT UNSIGNED NOT NULL DEFAULT 0,
                    `plugin_glurbackup_servers_id` INT UNSIGNED NOT NULL DEFAULT 0,
                    `date_mod` TIMESTAMP NULL DEFAULT NULL,
                    `date_creation` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq_computer` (`computers_id`),
                    KEY `plugin_glurbackup_servers_id` (`plugin_glurbackup_servers_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",

            'glpi_plugin_glurbackup_logs' => "
                CREATE TABLE `glpi_plugin_glurbackup_logs` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
                    `computers_id` INT UNSIGNED NOT NULL DEFAULT 0,
                    `plugin_glurbackup_servers_id` INT UNSIGNED NOT NULL DEFAULT 0,
                    `action` VARCHAR(255) NOT NULL,
                    `status` VARCHAR(50) NOT NULL,
                    `message` TEXT DEFAULT NULL,
                    `context` LONGTEXT DEFAULT NULL,
                    `users_id` INT UNSIGNED NOT NULL DEFAULT 0,
                    `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `computers_id` (`computers_id`),
                    KEY `plugin_glurbackup_servers_id` (`plugin_glurbackup_servers_id`),
                    KEY `date_creation` (`date_creation`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",

            'glpi_plugin_glurbackup_configs' => "
                CREATE TABLE `glpi_plugin_glurbackup_configs` (
                    `id` INT UNSIGNED NOT NULL,
                    `api_base_path` VARCHAR(255) NOT NULL DEFAULT '/x',
                    `api_timeout` INT UNSIGNED NOT NULL DEFAULT 30,
                    `log_retention_days` INT UNSIGNED NOT NULL DEFAULT 90,
                    `debug_mode` TINYINT NOT NULL DEFAULT 0,
                    `enable_delete_client_button` TINYINT NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
        ];

        foreach ($tables as $table => $sql) {
            if (!$DB->tableExists($table)) {
                $migration->addPostQuery($sql);
            }
        }

        // Aggiornamento tabella config esistente
        if ($DB->tableExists('glpi_plugin_glurbackup_configs')) {
            if (!$DB->fieldExists('glpi_plugin_glurbackup_configs', 'enable_delete_client_button')) {
                $migration->addPostQuery("
                    ALTER TABLE `glpi_plugin_glurbackup_configs`
                    ADD COLUMN `enable_delete_client_button` TINYINT NOT NULL DEFAULT 0
                ");
            }
        }

        $migration->executeMigration();

        // Riga config iniziale / allineamento
        $configTable = 'glpi_plugin_glurbackup_configs';

        $configExists = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => $configTable,
            'WHERE'  => ['id' => 1],
            'LIMIT'  => 1,
        ])->count() > 0;

        if (!$configExists) {
            $DB->insert($configTable, [
                'id'                          => 1,
                'api_base_path'               => '/x',
                'api_timeout'                 => 30,
                'log_retention_days'          => 90,
                'debug_mode'                  => 0,
                'enable_delete_client_button' => 0,
            ]);
        } else {
            if ($DB->fieldExists($configTable, 'enable_delete_client_button')) {
                $DB->update($configTable, [
                    'enable_delete_client_button' => 0,
                ], [
                    'id' => 1,
                    'enable_delete_client_button' => null,
                ]);
            }
        }

        $this->ensureProfileRightsForFreshInstall();

        if (
            class_exists(CronTask::class)
            && method_exists(CronTask::class, 'Register')
            && $DB->tableExists('glpi_crontasks')
        ) {
            $taskExists = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_crontasks',
                'WHERE'  => [
                    'itemtype' => Log::class,
                    'name'     => 'purge',
                ],
                'LIMIT'  => 1,
            ])->count() > 0;

            if (!$taskExists) {
                try {
                    CronTask::Register(Log::class, 'purge', DAY_TIMESTAMP, [
                        'mode'          => 2,
                        'allowmode'     => 3,
                        'logs_lifetime' => 90,
                        'comment'       => __('Purge Glurbackup logs', 'glurbackup'),
                    ]);
                } catch (\Throwable $e) {
                    // Non bloccare installazione/update
                }
            }
        }

        return true;
    }

    /**
     * Installazione pulita:
     * - profilo Supervisor => Write
     * - profilo attivo dell'utente che installa => Write
     * - tutti gli altri => No access
     */
    private function ensureProfileRightsForFreshInstall(): void
    {
        global $DB;

        $rightName        = 'plugin_glurbackup';
        $oldReadRight     = 'plugin_glurbackup_read';
        $oldUpdateRight   = 'plugin_glurbackup_update';
        $installerProfile = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);

        foreach ((new Profile())->find() as $profile) {
            $profileId   = (int) $profile['id'];
            $profileName = (string) ($profile['name'] ?? '');

            $defaultRight = 0;

            if ($profileName === 'Supervisor' || $profileId === $installerProfile) {
                $defaultRight = ALLSTANDARDRIGHT;
            }

            $existingNew = $this->getProfileRightValue($profileId, $rightName);

            if ($existingNew === null) {
                $DB->insert('glpi_profilerights', [
                    'profiles_id' => $profileId,
                    'name'        => $rightName,
                    'rights'      => $defaultRight,
                ]);
            } else {
                $DB->update('glpi_profilerights', [
                    'rights' => $defaultRight,
                ], [
                    'profiles_id' => $profileId,
                    'name'        => $rightName,
                ]);
            }
        }

        if ($DB->tableExists('glpi_profilerights')) {
            $DB->delete('glpi_profilerights', [
                'name' => [$oldReadRight, $oldUpdateRight],
            ]);
        }
    }

    private function getProfileRightValue(int $profileId, string $rightName): ?int
    {
        global $DB;

        $row = $DB->request([
            'SELECT' => ['rights'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => [
                'profiles_id' => $profileId,
                'name'        => $rightName,
            ],
            'LIMIT'  => 1,
        ])->current();

        if (!$row) {
            return null;
        }

        return (int) ($row['rights'] ?? 0);
    }
}
