<?php

namespace PluginGlurbackup;

class UrbackupApiV24 implements UrbackupApiInterface
{
    public function getVersionKey(): string
    {
        return '2.4';
    }

    public function getLabel(): string
    {
        return 'URBackup 2.4';
    }

    public function buildBaseUrl(array $server): string
    {
        $protocol = (($server['protocol'] ?? 'http') === 'https') ? 'https' : 'http';
        $host     = trim((string) ($server['ip_address'] ?? ''));
        $port     = (int) ($server['port'] ?? 55414);

        return $protocol . '://' . $host . ':' . $port . '/x';
    }

    public function getComputerOverview(array $server, string $computerName, ?string $computerIp = null): array
    {
        $baseUrl   = $this->buildBaseUrl($server);
        $username  = trim((string) ($server['username'] ?? ''));
        $password  = (string) ($server['password'] ?? '');
        $ignoreSsl = ((int) ($server['ignore_ssl'] ?? 0) === 1);

        $result = [
            'ok'                       => false,
            'found'                    => false,
            'message'                  => '',
            'client_version'           => '—',
            'online_offline'           => '—',
            'last_file_backup'         => '—',
            'last_image_backup'        => '—',
            'last_file_backup_result'  => '—',
            'last_image_backup_result' => '—',
            'current_activities'       => '—',
            'default_dirs'             => '',
            'internet_auth_key'        => '',
            'internet_mode_enabled'    => false,
            'recent_backups'           => [],
            'recent_logs'              => [],
            'connection'               => $this->buildInitialConnectionDebug($baseUrl, $username, $ignoreSsl),
            'matched_by'               => '',
        ];

        if ($baseUrl === '' || $username === '' || $password === '') {
            $result['message'] = 'Errore comunicazione col server';
            return $result;
        }

        try {
            $session = $this->loginAndGetSession($baseUrl, $username, $password, $ignoreSsl, $result['connection']);

            if ($session === null || $session['ses'] === '') {
                $result['message'] = 'Errore comunicazione col server';
                return $result;
            }

            $clients = $this->fetchStatusClients($baseUrl, $session['ses'], $ignoreSsl, $result['connection']);
        } catch (\Throwable $e) {
            $result['message'] = 'Errore comunicazione col server';
            if (is_array($result['connection'])) {
                $result['connection']['exception'] = $e->getMessage();
            }
            return $result;
        }

        $result['ok'] = true;

        $matchedBy = null;
        $client    = $this->findClient($clients, $computerName, $computerIp, $matchedBy);

        if ($client === null) {
            $result['message'] = 'non presente sul server urbackup';
            return $result;
        }

        $clientId = (int) ($client['id'] ?? 0);

        $result['found']                    = true;
        $result['matched_by']               = (string) ($matchedBy ?? '');
        $result['client_version']           = $this->normalizeClientVersion($client);
        $result['online_offline']           = $this->normalizeOnlineOffline($client);
        $result['last_file_backup']         = $this->normalizeLastBackupValue($client['lastbackup'] ?? null);
        $result['last_image_backup']        = $this->normalizeLastBackupValue($client['lastbackup_image'] ?? null);
        $result['last_file_backup_result']  = $this->normalizeFileBackupResult($client);
        $result['last_image_backup_result'] = $this->normalizeImageBackupResult($client);
        $result['current_activities']       = $this->normalizeCurrentActivities($client);
        $result['default_dirs']             = $this->fetchClientDefaultDirs($baseUrl, $session['ses'], $clientId, $ignoreSsl);

        $internetSettings = $this->fetchInternetSettingsByClientId($baseUrl, $session['ses'], $clientId, $ignoreSsl);
        $result['internet_auth_key']     = (string) ($internetSettings['internet_auth_key'] ?? '');
        $result['internet_mode_enabled'] = (bool) ($internetSettings['internet_mode_enabled'] ?? false);

        $result['recent_backups'] = $this->fetchRecentBackups($baseUrl, $session['ses'], $clientId, $ignoreSsl, $result['connection']);
        if ($result['recent_backups'] === []) {
            $result['recent_backups'] = $this->buildFallbackRecentBackups($client);
        }

        $result['recent_logs'] = $this->getRecentLogsBySession($baseUrl, $session['ses'], $clientId, $ignoreSsl, 10);

        return $result;
    }

    public function getInternetSettings(array $server, string $computerName, ?string $computerIp = null): array
    {
        $baseUrl   = $this->buildBaseUrl($server);
        $username  = trim((string) ($server['username'] ?? ''));
        $password  = (string) ($server['password'] ?? '');
        $ignoreSsl = ((int) ($server['ignore_ssl'] ?? 0) === 1);

        $connection = $this->buildInitialConnectionDebug($baseUrl, $username, $ignoreSsl);

        if ($baseUrl === '' || $username === '' || $password === '') {
            return [
                'ok'                    => false,
                'message'               => __('Dati server non validi.', 'glurbackup'),
                'internet_auth_key'     => '',
                'internet_mode_enabled' => false,
                'matched_by'            => '',
            ];
        }

        try {
            $session = $this->loginAndGetSession($baseUrl, $username, $password, $ignoreSsl, $connection);
            if ($session === null || $session['ses'] === '') {
                return [
                    'ok'                    => false,
                    'message'               => __('Login URBackup non riuscito.', 'glurbackup'),
                    'internet_auth_key'     => '',
                    'internet_mode_enabled' => false,
                    'matched_by'            => '',
                ];
            }

            $clients = $this->fetchStatusClients($baseUrl, $session['ses'], $ignoreSsl, $connection);
            $matchedBy = null;
            $client = $this->findClient($clients, $computerName, $computerIp, $matchedBy);

            if ($client === null) {
                return [
                    'ok'                    => false,
                    'message'               => __('Client non trovato su URBackup.', 'glurbackup'),
                    'internet_auth_key'     => '',
                    'internet_mode_enabled' => false,
                    'matched_by'            => '',
                ];
            }

            $clientId = (int) ($client['id'] ?? 0);
            $settings = $this->fetchInternetSettingsByClientId($baseUrl, $session['ses'], $clientId, $ignoreSsl);

            return [
                'ok'                    => true,
                'message'               => '',
                'internet_auth_key'     => (string) ($settings['internet_auth_key'] ?? ''),
                'internet_mode_enabled' => (bool) ($settings['internet_mode_enabled'] ?? false),
                'matched_by'            => (string) ($matchedBy ?? ''),
            ];
        } catch (\Throwable $e) {
            return [
                'ok'                    => false,
                'message'               => __('Errore durante la lettura dei parametri Internet del client.', 'glurbackup') . ' ' . $e->getMessage(),
                'internet_auth_key'     => '',
                'internet_mode_enabled' => false,
                'matched_by'            => '',
            ];
        }
    }

    public function addClientAndVerify(array $server, string $computerName): array
    {
        $baseUrl   = $this->buildBaseUrl($server);
        $username  = trim((string) ($server['username'] ?? ''));
        $password  = (string) ($server['password'] ?? '');
        $ignoreSsl = ((int) ($server['ignore_ssl'] ?? 0) === 1);

        $connection = $this->buildInitialConnectionDebug($baseUrl, $username, $ignoreSsl);

        if ($baseUrl === '' || $username === '' || $password === '' || trim($computerName) === '') {
            return [
                'ok'      => false,
                'message' => __('Dati server o nome computer non validi.', 'glurbackup'),
            ];
        }

        try {
            $session = $this->loginAndGetSession($baseUrl, $username, $password, $ignoreSsl, $connection);
            if ($session === null || $session['ses'] === '') {
                return [
                    'ok'      => false,
                    'message' => __('Login URBackup non riuscito.', 'glurbackup'),
                ];
            }

            $ret = $this->requestJson(
                $baseUrl,
                'add_client',
                [
                    'clientname' => $computerName,
                    'ses'        => $session['ses'],
                ],
                'POST',
                $ignoreSsl
            );

            $clients = $this->fetchStatusClients($baseUrl, $session['ses'], $ignoreSsl, $connection);
            $matchedBy = null;
            $client = $this->findClient($clients, $computerName, null, $matchedBy);

            if ($client === null) {
                return [
                    'ok'      => false,
                    'message' => __('Client non trovato su URBackup dopo la creazione/verifica.', 'glurbackup'),
                ];
            }

            $created = false;
            if (is_array($ret)) {
                if (!empty($ret['new_clientid']) || !empty($ret['new_authkey'])) {
                    $created = true;
                }
                if (!empty($ret['already_exists'])) {
                    $created = true;
                }
            }

            return [
                'ok'      => true,
                'created' => $created,
                'client'  => $client,
                'message' => $created
                    ? __('Client creato/verificato correttamente su URBackup.', 'glurbackup')
                    : __('Client verificato correttamente su URBackup.', 'glurbackup'),
            ];
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => __('Errore durante la creazione/verifica del client su URBackup.', 'glurbackup') . ' ' . $e->getMessage(),
            ];
        }
    }

    public function runClientAction(array $server, string $computerName, string $actionKey): array
    {
        $baseUrl   = $this->buildBaseUrl($server);
        $username  = trim((string) ($server['username'] ?? ''));
        $password  = (string) ($server['password'] ?? '');
        $ignoreSsl = ((int) ($server['ignore_ssl'] ?? 0) === 1);

        $connection = $this->buildInitialConnectionDebug($baseUrl, $username, $ignoreSsl);

        $typeMap = [
            'backup_incr_file'  => 'incr_file',
            'backup_full_file'  => 'full_file',
            'backup_incr_image' => 'incr_image',
            'backup_full_image' => 'full_image',
        ];

        if (!isset($typeMap[$actionKey])) {
            return [
                'ok'      => false,
                'message' => __('Azione non supportata.', 'glurbackup'),
            ];
        }

        try {
            $session = $this->loginAndGetSession($baseUrl, $username, $password, $ignoreSsl, $connection);
            if ($session === null || $session['ses'] === '') {
                return [
                    'ok'      => false,
                    'message' => __('Login URBackup non riuscito.', 'glurbackup'),
                ];
            }

            $clients = $this->fetchStatusClients($baseUrl, $session['ses'], $ignoreSsl, $connection);
            $matchedBy = null;
            $client = $this->findClient($clients, $computerName, null, $matchedBy);

            if ($client === null || empty($client['id'])) {
                return [
                    'ok'      => false,
                    'message' => __('Client non trovato su URBackup.', 'glurbackup'),
                ];
            }

            $ret = $this->requestJson(
                $baseUrl,
                'start_backup',
                [
                    'start_client' => (int) $client['id'],
                    'start_type'   => $typeMap[$actionKey],
                    'ses'          => $session['ses'],
                ],
                'POST',
                $ignoreSsl
            );

            if (!is_array($ret) || !isset($ret['result'][0]['start_ok']) || !$ret['result'][0]['start_ok']) {
                return [
                    'ok'      => false,
                    'message' => __('Il server UrBackup non ha confermato l’avvio del backup.', 'glurbackup'),
                ];
            }

            return [
                'ok'      => true,
                'message' => __('Backup avviato correttamente su UrBackup.', 'glurbackup'),
            ];
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => __('Errore durante l’avvio del backup.', 'glurbackup') . ' ' . $e->getMessage(),
            ];
        }
    }

    public function updateDefaultDirs(array $server, string $computerName, string $newValue, ?string $computerIp = null): array
    {
        $baseUrl   = $this->buildBaseUrl($server);
        $username  = trim((string) ($server['username'] ?? ''));
        $password  = (string) ($server['password'] ?? '');
        $ignoreSsl = ((int) ($server['ignore_ssl'] ?? 0) === 1);

        $connection = $this->buildInitialConnectionDebug($baseUrl, $username, $ignoreSsl);

        try {
            $session = $this->loginAndGetSession($baseUrl, $username, $password, $ignoreSsl, $connection);
            if ($session === null || $session['ses'] === '') {
                return [
                    'ok'      => false,
                    'message' => __('Login URBackup non riuscito.', 'glurbackup'),
                ];
            }

            $clients = $this->fetchStatusClients($baseUrl, $session['ses'], $ignoreSsl, $connection);
            $matchedBy = null;
            $client = $this->findClient($clients, $computerName, $computerIp, $matchedBy);

            if ($client === null) {
                return [
                    'ok'      => false,
                    'message' => __('Client non trovato su URBackup.', 'glurbackup'),
                ];
            }

            $clientId = (int) ($client['id'] ?? 0);
            if ($clientId <= 0) {
                return [
                    'ok'      => false,
                    'message' => __('Client ID URBackup non valido.', 'glurbackup'),
                ];
            }

            $ret = $this->requestJson(
                $baseUrl,
                'settings',
                [
                    'default_dirs' => $newValue,
                    'overwrite'    => 'true',
                    'sa'           => 'clientsettings_save',
                    't_clientid'   => $clientId,
                    'ses'          => $session['ses'],
                ],
                'POST',
                $ignoreSsl
            );

            if (!is_array($ret) || (!isset($ret['saved_ok']) && !isset($ret['success']))) {
                return [
                    'ok'      => false,
                    'message' => __('Salvataggio delle directory di default non riuscito.', 'glurbackup'),
                ];
            }

            return [
                'ok'      => true,
                'message' => __('Directory di default salvata correttamente su URBackup.', 'glurbackup'),
            ];
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => __('Errore durante il salvataggio delle directory di default.', 'glurbackup') . ' ' . $e->getMessage(),
            ];
        }
    }

    public function createClientExplicit(array $server, string $computerName): array
    {
        return $this->addClientAndVerify($server, $computerName);
    }

    public function deleteClientExplicit(array $server, string $computerName): array
    {
        $baseUrl   = $this->buildBaseUrl($server);
        $username  = trim((string) ($server['username'] ?? ''));
        $password  = (string) ($server['password'] ?? '');
        $ignoreSsl = ((int) ($server['ignore_ssl'] ?? 0) === 1);

        $connection = $this->buildInitialConnectionDebug($baseUrl, $username, $ignoreSsl);

        try {
            $session = $this->loginAndGetSession(
                $baseUrl,
                $username,
                $password,
                $ignoreSsl,
                $connection
            );

            if ($session === null || $session['ses'] === '') {
                return [
                    'ok'      => false,
                    'message' => __('Login URBackup non riuscito.', 'glurbackup'),
                ];
            }

            $clients = $this->fetchStatusClients(
                $baseUrl,
                $session['ses'],
                $ignoreSsl,
                $connection
            );

            $matchedBy = null;
            $client = $this->findClient($clients, $computerName, null, $matchedBy);

            if ($client === null || empty($client['id'])) {
                return [
                    'ok'      => false,
                    'message' => __('Client non presente su URBackup.', 'glurbackup'),
                ];
            }

            $ret = $this->requestJson(
                $baseUrl,
                'remove_client',
                [
                    'clientid'       => (int) $client['id'],
                    'delete_backups' => 1,
                    'ses'            => $session['ses'],
                ],
                'POST',
                $ignoreSsl
            );

            if (!is_array($ret) || (isset($ret['error']) && (int) $ret['error'] === 1)) {
                return [
                    'ok'      => false,
                    'message' => __('Il server UrBackup ha rifiutato la rimozione del client.', 'glurbackup'),
                ];
            }

            return [
                'ok'      => true,
                'message' => __('Client e backup eliminati correttamente su UrBackup.', 'glurbackup'),
            ];
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => __('Errore durante l’eliminazione del client su UrBackup.', 'glurbackup') . ' ' . $e->getMessage(),
            ];
        }
    }

    public function saveInternetMode(array $server, string $computerName, ?string $computerIp, bool $enabled): array
    {
        $baseUrl   = $this->buildBaseUrl($server);
        $username  = trim((string) ($server['username'] ?? ''));
        $password  = (string) ($server['password'] ?? '');
        $ignoreSsl = ((int) ($server['ignore_ssl'] ?? 0) === 1);

        $connection = $this->buildInitialConnectionDebug($baseUrl, $username, $ignoreSsl);

        try {
            $session = $this->loginAndGetSession($baseUrl, $username, $password, $ignoreSsl, $connection);
            if ($session === null || $session['ses'] === '') {
                return [
                    'ok'      => false,
                    'message' => __('Login URBackup non riuscito.', 'glurbackup'),
                ];
            }

            $clients = $this->fetchStatusClients($baseUrl, $session['ses'], $ignoreSsl, $connection);
            $matchedBy = null;
            $client = $this->findClient($clients, $computerName, $computerIp, $matchedBy);

            if ($client === null) {
                return [
                    'ok'      => false,
                    'message' => __('Client non trovato su URBackup.', 'glurbackup'),
                ];
            }

            $clientId = (int) ($client['id'] ?? 0);
            if ($clientId <= 0) {
                return [
                    'ok'      => false,
                    'message' => __('Client ID URBackup non valido.', 'glurbackup'),
                ];
            }

            $ret = $this->requestJson(
                $baseUrl,
                'settings',
                [
                    'internet_mode_enabled' => $enabled ? 'true' : 'false',
                    'overwrite'             => 'true',
                    'sa'                    => 'clientsettings_save',
                    't_clientid'            => $clientId,
                    'ses'                   => $session['ses'],
                ],
                'POST',
                $ignoreSsl
            );

            if (!is_array($ret) || (!isset($ret['saved_ok']) && !isset($ret['success']))) {
                return [
                    'ok'      => false,
                    'message' => __('Salvataggio del parametro Internet mode non riuscito.', 'glurbackup'),
                ];
            }

            return [
                'ok'      => true,
                'message' => $enabled
                    ? __('Internet mode abilitato correttamente su URBackup.', 'glurbackup')
                    : __('Internet mode disabilitato correttamente su URBackup.', 'glurbackup'),
            ];
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => __('Errore durante il salvataggio del parametro Internet mode.', 'glurbackup') . ' ' . $e->getMessage(),
            ];
        }
    }

    protected function verifyBackupActivityStarted(
        string $baseUrl,
        string $ses,
        bool $ignoreSsl,
        array $expectedClient,
        string $actionKey,
        array &$connection
    ): bool {
        $expectedId   = (int) ($expectedClient['id'] ?? 0);
        $expectedName = $this->normalizeString((string) ($expectedClient['name'] ?? ''));

        for ($i = 0; $i < 6; $i++) {
            if ($i > 0) {
                sleep(1);
            }

            try {
                $clients = $this->fetchStatusClients($baseUrl, $ses, $ignoreSsl, $connection);

                foreach ($clients as $client) {
                    $clientId   = (int) ($client['id'] ?? 0);
                    $clientName = $this->normalizeString((string) ($client['name'] ?? ''));

                    if ($expectedId > 0 && $clientId !== $expectedId) {
                        continue;
                    }
                    if ($expectedId <= 0 && $expectedName !== '' && $clientName !== $expectedName) {
                        continue;
                    }

                    $processes = $client['processes'] ?? null;
                    if (!is_array($processes) || count($processes) === 0) {
                        continue;
                    }

                    foreach ($processes as $process) {
                        $text = '';
                        if (is_string($process)) {
                            $text = $process;
                        } elseif (is_array($process)) {
                            $text = implode(' ', array_map(static function ($v): string {
                                return is_scalar($v) ? (string) $v : '';
                            }, $process));
                        }

                        $text = mb_strtolower(trim($text));
                        if ($text === '') {
                            continue;
                        }

                        if (strpos($text, 'backup') !== false || strpos($text, 'file') !== false || strpos($text, 'image') !== false) {
                            return true;
                        }

                        if (
                            ($actionKey === 'backup_incr_file' || $actionKey === 'backup_full_file')
                            && strpos($text, 'file') !== false
                        ) {
                            return true;
                        }

                        if (
                            ($actionKey === 'backup_incr_image' || $actionKey === 'backup_full_image')
                            && strpos($text, 'image') !== false
                        ) {
                            return true;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // continua polling
            }
        }

        return false;
    }

    protected function isAcceptedActionResponse($ret): bool
    {
        if (!is_array($ret)) {
            return false;
        }

        if (isset($ret['error']) && (int) $ret['error'] === 1) {
            return false;
        }

        foreach (['ok', 'success', 'started', 'start_ok'] as $key) {
            if (isset($ret[$key]) && $ret[$key] === true) {
                return true;
            }
            if (isset($ret[$key]) && (string) $ret[$key] === '1') {
                return true;
            }
            if (isset($ret[$key]) && (int) $ret[$key] === 1) {
                return true;
            }
        }

        return !isset($ret['error']);
    }

    protected function requestDecodedResponse(
        string $baseUrl,
        string $action,
        array $params,
        string $method,
        bool $ignoreSsl
    ): array {
        $raw = $this->httpRequest($baseUrl, $action, $params, $method, $ignoreSsl);

        $decoded = json_decode((string) ($raw['body'] ?? ''), true);
        if (!is_array($decoded)) {
            $decoded = null;
        }

        return [
            'raw'     => $raw,
            'decoded' => $decoded,
        ];
    }

    protected function writeDebugLog(array $context): void
    {
        $path = $this->getDebugLogPath();

        $payload = [
            'ts'      => date('Y-m-d H:i:s'),
            'context' => $context,
        ];

        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }

        @file_put_contents($path, $line . PHP_EOL, FILE_APPEND);
    }

    protected function getDebugLogPath(): string
    {
        if (defined('GLPI_LOG_DIR')) {
            return rtrim(GLPI_LOG_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'glurbackup_api_debug.log';
        }

        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'glurbackup_api_debug.log';
    }

    protected function sanitizeForLog(array $params): array
    {
        $out = $params;

        foreach (['password', 'authkey', 'new_authkey', 'ses'] as $secretKey) {
            if (isset($out[$secretKey])) {
                $out[$secretKey] = '***';
            }
        }

        return $out;
    }

    protected function fetchClientDefaultDirs(string $baseUrl, string $ses, int $clientId, bool $ignoreSsl): string
    {
        if ($clientId <= 0) {
            return '';
        }

        $response = $this->requestJson(
            $baseUrl,
            'settings',
            [
                'sa'         => 'clientsettings',
                't_clientid' => $clientId,
                'ses'        => $ses,
            ],
            'POST',
            $ignoreSsl
        );

        if (!is_array($response) || !isset($response['settings']) || !is_array($response['settings'])) {
            return '';
        }

        $settings = $response['settings'];

        if (isset($settings['default_dirs']) && is_string($settings['default_dirs'])) {
            return $settings['default_dirs'];
        }

        if (isset($settings['default_dirs']['value']) && is_string($settings['default_dirs']['value'])) {
            return $settings['default_dirs']['value'];
        }

        return '';
    }

    protected function fetchInternetSettingsByClientId(string $baseUrl, string $ses, int $clientId, bool $ignoreSsl): array
    {
        if ($clientId <= 0) {
            return [
                'internet_auth_key'     => '',
                'internet_mode_enabled' => false,
            ];
        }

        $response = $this->requestJson(
            $baseUrl,
            'settings',
            [
                'sa'         => 'clientsettings',
                't_clientid' => $clientId,
                'ses'        => $ses,
            ],
            'POST',
            $ignoreSsl
        );

        if (!is_array($response) || !isset($response['settings']) || !is_array($response['settings'])) {
            return [
                'internet_auth_key'     => '',
                'internet_mode_enabled' => false,
            ];
        }

        $settings = $response['settings'];

        return [
            'internet_auth_key'     => $this->extractSettingString($settings, 'internet_authkey'),
            'internet_mode_enabled' => $this->extractSettingBool($settings, 'internet_mode_enabled'),
        ];
    }

    protected function extractSettingString(array $settings, string $key): string
    {
        if (!array_key_exists($key, $settings)) {
            return '';
        }

        $value = $settings[$key];

        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value) && isset($value['value']) && is_scalar($value['value'])) {
            return trim((string) $value['value']);
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    protected function extractSettingBool(array $settings, string $key): bool
    {
        $value = $this->extractSettingString($settings, $key);
        if ($value === '') {
            return false;
        }

        return in_array(mb_strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    protected function getRecentLogsBySession(string $baseUrl, string $ses, int $clientId, bool $ignoreSsl, int $limit = 10): array
    {
        if ($clientId <= 0) {
            return [];
        }

        try {
            $ret = $this->requestJson(
                $baseUrl,
                'livelog',
                [
                    'clientid' => $clientId,
                    'lastid'   => 0,
                    'ses'      => $ses,
                ],
                'POST',
                $ignoreSsl
            );

            if (!is_array($ret) || !isset($ret['logdata']) || !is_array($ret['logdata'])) {
                return [];
            }

            $rows = [];
            foreach ($ret['logdata'] as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $rows[] = [
                    'time'    => $this->extractLogTime($row),
                    'level'   => $this->extractLogLevel($row),
                    'message' => $this->extractLogMessage($row),
                    '_sort'   => (int) ($row['id'] ?? 0),
                ];
            }

            usort($rows, static function (array $a, array $b): int {
                return ((int) ($b['_sort'] ?? 0)) <=> ((int) ($a['_sort'] ?? 0));
            });

            foreach ($rows as &$row) {
                unset($row['_sort']);
            }

            return array_slice($rows, 0, $limit);
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function loginAndGetSession(
        string $baseUrl,
        string $username,
        string $password,
        bool $ignoreSsl,
        array &$connection
    ): ?array {
        $saltResponse = $this->requestJson(
            $baseUrl,
            'salt',
            ['username' => $username],
            'POST',
            $ignoreSsl
        );

        if (!is_array($saltResponse) || empty($saltResponse['ses']) || empty($saltResponse['salt']) || empty($saltResponse['rnd'])) {
            return null;
        }

        $ses              = (string) $saltResponse['ses'];
        $salt             = (string) $saltResponse['salt'];
        $rnd              = (string) $saltResponse['rnd'];
        $pbkdf2Rounds     = (int) ($saltResponse['pbkdf2_rounds'] ?? 0);
        $finalPasswordHex = $this->buildPasswordHash($password, $salt, $rnd, $pbkdf2Rounds);

        $connection['ses']          = $ses;
        $connection['salt_curl']    = $this->buildSaltCurl($baseUrl, $username, $ignoreSsl);
        $connection['hash_formula'] = 'password_md5_bin = MD5(salt + password); password_md5 = PBKDF2-HMAC-SHA256(password_md5_bin, salt, ' . $pbkdf2Rounds . '); final_password = MD5(rnd + password_md5)';
        $connection['login_curl']   = $this->buildLoginCurl($baseUrl, $username, $finalPasswordHex, $ses, $ignoreSsl);

        $loginResponse = $this->requestJson(
            $baseUrl,
            'login',
            [
                'username' => $username,
                'password' => $finalPasswordHex,
                'ses'      => $ses,
            ],
            'POST',
            $ignoreSsl
        );

        if (!is_array($loginResponse) || empty($loginResponse['success'])) {
            return null;
        }

        $sessionOut = (string) ($loginResponse['session'] ?? $ses);
        if ($sessionOut === '') {
            $sessionOut = $ses;
        }

        return [
            'ses' => $sessionOut,
        ];
    }

    protected function fetchStatusClients(string $baseUrl, string $ses, bool $ignoreSsl, array &$connection): array
    {
        $connection['status_curl'] = $this->buildStatusCurl($baseUrl, $ses, $ignoreSsl);

        $statusResponse = $this->requestJson(
            $baseUrl,
            'status',
            ['ses' => $ses],
            'POST',
            $ignoreSsl
        );

        if (!is_array($statusResponse)) {
            throw new \RuntimeException('Invalid status response');
        }

        if (isset($statusResponse['error']) && (int) $statusResponse['error'] === 1) {
            throw new \RuntimeException('Status returned error=1');
        }

        if (isset($statusResponse['status']) && is_array($statusResponse['status'])) {
            return $statusResponse['status'];
        }

        if ($this->isClientList($statusResponse)) {
            return $statusResponse;
        }

        foreach (['clients', 'result', 'data'] as $key) {
            if (isset($statusResponse[$key]) && is_array($statusResponse[$key])) {
                if ($this->isClientList($statusResponse[$key])) {
                    return $statusResponse[$key];
                }
            }
        }

        throw new \RuntimeException('Unable to find clients list in status response');
    }

    protected function fetchRecentBackups(
        string $baseUrl,
        string $ses,
        int $clientId,
        bool $ignoreSsl,
        array &$connection
    ): array {
        if ($clientId <= 0) {
            return [];
        }

        $connection['backups_curl'] = $this->buildBackupsCurl($baseUrl, $ses, $clientId, $ignoreSsl);

        $attempts = [
            ['clientid' => $clientId, 'ses' => $ses],
            ['id' => $clientId, 'ses' => $ses],
            ['clientid' => $clientId, 't_clientid' => $clientId, 'ses' => $ses],
        ];

        foreach ($attempts as $params) {
            try {
                $response = $this->requestJson($baseUrl, 'backups', $params, 'POST', $ignoreSsl);
                if (!is_array($response)) {
                    continue;
                }

                $rows = $this->extractBackupRowsFromResponse($response);
                if ($rows !== []) {
                    return array_slice($rows, 0, 7);
                }
            } catch (\Throwable $e) {
                // prossimo tentativo
            }
        }

        return [];
    }

    protected function extractBackupRowsFromResponse(array $response): array
    {
        $rows = [];
        $this->collectBackupRowsRecursive($response, $rows);

        usort($rows, static function (array $a, array $b): int {
            return ((int) ($b['_sort'] ?? 0)) <=> ((int) ($a['_sort'] ?? 0));
        });

        foreach ($rows as &$row) {
            unset($row['_sort']);
        }

        return $rows;
    }

    protected function collectBackupRowsRecursive(array $data, array &$rows): void
    {
        if ($this->looksLikeBackupRow($data)) {
            $rows[] = $this->normalizeBackupRow($data);
            return;
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $this->collectBackupRowsRecursive($value, $rows);
            }
        }
    }

    protected function looksLikeBackupRow(array $row): bool
    {
        return array_key_exists('id', $row)
            && (
                array_key_exists('backuptime', $row)
                || array_key_exists('size_bytes', $row)
                || array_key_exists('incr', $row)
                || array_key_exists('archived', $row)
            );
    }

    protected function normalizeBackupRow(array $row): array
    {
        $backupId   = (int) ($row['id'] ?? 0);
        $timestamp  = $this->extractBackupTimestamp($row);
        $dateString = $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : $this->normalizeLastBackupValue($row['backuptime'] ?? null);

        return [
            'date'        => $dateString,
            'backup_id'   => $backupId > 0 ? (string) $backupId : '—',
            'incremental' => $this->normalizeYesNo($row['incr'] ?? null),
            'size'        => $this->formatBytes($row['size_bytes'] ?? null),
            'archived'    => $this->normalizeYesNo($row['archived'] ?? null),
            '_sort'       => $timestamp,
        ];
    }

    protected function buildFallbackRecentBackups(array $client): array
    {
        $rows = [];

        $fileTs = (int) ($client['lastbackup'] ?? 0);
        if ($fileTs > 0) {
            $rows[] = [
                'date'        => date('Y-m-d H:i:s', $fileTs),
                'backup_id'   => '—',
                'incremental' => '—',
                'size'        => '—',
                'archived'    => '—',
                '_sort'       => $fileTs,
            ];
        }

        $imgTs = (int) ($client['lastbackup_image'] ?? 0);
        if ($imgTs > 0) {
            $rows[] = [
                'date'        => date('Y-m-d H:i:s', $imgTs),
                'backup_id'   => '—',
                'incremental' => '—',
                'size'        => '—',
                'archived'    => '—',
                '_sort'       => $imgTs,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return ((int) ($b['_sort'] ?? 0)) <=> ((int) ($a['_sort'] ?? 0));
        });

        foreach ($rows as &$row) {
            unset($row['_sort']);
        }

        return array_slice($rows, 0, 7);
    }

    protected function requestJson(
        string $baseUrl,
        string $action,
        array $params,
        string $method,
        bool $ignoreSsl
    ): ?array {
        $response = $this->httpRequest($baseUrl, $action, $params, $method, $ignoreSsl);

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    protected function httpRequest(
        string $baseUrl,
        string $action,
        array $params,
        string $method,
        bool $ignoreSsl
    ): array {
        $method = strtoupper($method);
        $url    = $baseUrl . '?a=' . rawurlencode($action);

        $ch = curl_init();
        if ($ch === false) {
            throw new \RuntimeException('Cannot initialize curl');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => !$ignoreSsl,
            CURLOPT_SSL_VERIFYHOST => $ignoreSsl ? 0 : 2,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST]       = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($params);
        } else {
            if ($params !== []) {
                $url .= '&' . http_build_query($params);
            }
        }

        $options[CURLOPT_URL] = $url;

        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($error !== '' ? $error : 'Unknown curl error');
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'body'   => (string) $body,
            'url'    => $url,
        ];
    }

    protected function buildPasswordHash(string $plainPassword, string $salt, string $rnd, int $pbkdf2Rounds): string
    {
        $passwordMd5Bin = md5($salt . $plainPassword, true);
        $passwordMd5    = bin2hex($passwordMd5Bin);

        if ($pbkdf2Rounds > 0) {
            $passwordMd5 = hash_pbkdf2(
                'sha256',
                $passwordMd5Bin,
                $salt,
                $pbkdf2Rounds,
                0,
                false
            );
        }

        return md5($rnd . $passwordMd5);
    }

    protected function findClient(array $clients, string $computerName, ?string $computerIp, ?string &$matchedBy = null): ?array
    {
        $normalizedName = $this->normalizeString($computerName);

        foreach ($clients as $client) {
            $clientName = $this->normalizeString((string) ($client['name'] ?? ''));
            if ($normalizedName !== '' && $clientName !== '' && $clientName === $normalizedName) {
                $matchedBy = 'name';
                return $client;
            }
        }

        $normalizedIp = $this->normalizeIp($computerIp);
        if ($normalizedIp !== null) {
            foreach ($clients as $client) {
                $clientIp = $this->normalizeIp((string) ($client['ip'] ?? ''));
                if ($clientIp !== null && $clientIp === $normalizedIp) {
                    $matchedBy = 'ip';
                    return $client;
                }
            }
        }

        return null;
    }

    protected function normalizeClientVersion(array $client): string
    {
        $value = trim((string) ($client['client_version_string'] ?? ($client['client_version'] ?? '')));
        return $value !== '' ? $value : '—';
    }

    protected function normalizeOnlineOffline(array $client): string
    {
        if (!array_key_exists('online', $client)) {
            return '—';
        }

        return ((bool) $client['online']) ? __('Online', 'glurbackup') : __('Offline', 'glurbackup');
    }

    protected function normalizeLastBackupValue($value): string
    {
        if ($value === '-' || $value === '' || $value === null) {
            return '—';
        }

        if (is_numeric($value)) {
            $ts = (int) $value;
            if ($ts <= 0) {
                return '—';
            }

            return date('Y-m-d H:i:s', $ts);
        }

        return trim((string) $value) !== '' ? (string) $value : '—';
    }

    protected function normalizeFileBackupResult(array $client): string
    {
        if (!array_key_exists('file_ok', $client)) {
            return '—';
        }

        $ok     = (bool) $client['file_ok'];
        $issues = (int) ($client['last_filebackup_issues'] ?? 0);

        if ($ok && $issues <= 0) {
            return __('OK', 'glurbackup');
        }

        if ($ok && $issues > 0) {
            return __('OK with issues', 'glurbackup') . ' (' . $issues . ')';
        }

        if (!$ok && $issues > 0) {
            return __('Failed', 'glurbackup') . ' (' . $issues . ')';
        }

        return __('Failed', 'glurbackup');
    }

    protected function normalizeImageBackupResult(array $client): string
    {
        if (!array_key_exists('image_ok', $client)) {
            return '—';
        }

        return ((bool) $client['image_ok']) ? __('OK', 'glurbackup') : __('Failed', 'glurbackup');
    }

    protected function normalizeCurrentActivities(array $client): string
    {
        $processes = $client['processes'] ?? null;
        if (!is_array($processes) || count($processes) === 0) {
            return '—';
        }

        $parts = [];

        foreach ($processes as $process) {
            if (is_string($process)) {
                $value = trim($process);
                if ($value !== '') {
                    $parts[] = $value;
                }
                continue;
            }

            if (is_array($process)) {
                foreach (['name', 'action', 'status', 'details', 'msg'] as $key) {
                    if (isset($process[$key]) && trim((string) $process[$key]) !== '') {
                        $parts[] = trim((string) $process[$key]);
                        break;
                    }
                }
            }
        }

        return $parts === [] ? '—' : implode(' | ', $parts);
    }

    protected function extractBackupTimestamp(array $row): int
    {
        $value = $row['backuptime'] ?? null;

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $ts = strtotime($value);
            return $ts !== false ? (int) $ts : 0;
        }

        return 0;
    }

    protected function normalizeYesNo($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_string($value)) {
            $normalized = mb_strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'y'], true)) {
                return __('Yes');
            }
            if (in_array($normalized, ['0', 'false', 'no', 'n'], true)) {
                return __('No');
            }
        }

        return ((bool) $value) ? __('Yes') : __('No');
    }

    protected function formatBytes($value): string
    {
        if (!is_numeric($value)) {
            return '—';
        }

        $bytes = (float) $value;
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $power = (int) floor(log($bytes, 1024));
        $power = max(0, min($power, count($units) - 1));

        $scaled = $bytes / (1024 ** $power);

        if ($scaled >= 100 || $power === 0) {
            return number_format($scaled, 0, '.', '') . ' ' . $units[$power];
        }

        if ($scaled >= 10) {
            return number_format($scaled, 1, '.', '') . ' ' . $units[$power];
        }

        return number_format($scaled, 2, '.', '') . ' ' . $units[$power];
    }

    protected function extractLogTime(array $row): string
    {
        $value = $row['time'] ?? ($row['times'] ?? null);

        if (is_numeric($value)) {
            $ts = (int) $value;
            return $ts > 0 ? date('Y-m-d H:i:s', $ts) : '—';
        }

        $str = trim((string) $value);
        return $str !== '' ? $str : '—';
    }

    protected function extractLogLevel(array $row): string
    {
        if (isset($row['level']) && trim((string) $row['level']) !== '') {
            return trim((string) $row['level']);
        }

        $value = $row['loglevel'] ?? null;
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_numeric($value)) {
            return match ((int) $value) {
                0 => 'DEBUG',
                1 => 'INFO',
                2 => 'WARNING',
                3 => 'ERROR',
                default => (string) $value,
            };
        }

        return trim((string) $value) !== '' ? trim((string) $value) : '—';
    }

    protected function extractLogMessage(array $row): string
    {
        foreach (['msg', 'message', 'data', 'utf8_msg'] as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return '—';
    }

    protected function isClientList(array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $first = reset($data);
        if (!is_array($first)) {
            return false;
        }

        return isset($first['name'])
            || isset($first['client_version_string'])
            || isset($first['lastbackup']);
    }

    protected function normalizeString(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    protected function normalizeIp(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return null;
        }

        return $value;
    }

    protected function buildInitialConnectionDebug(string $baseUrl, string $username, bool $ignoreSsl): array
    {
        return [
            'base_url'  => $baseUrl,
            'salt_curl' => $this->buildSaltCurl($baseUrl, $username, $ignoreSsl),
        ];
    }

    protected function buildSaltCurl(string $baseUrl, string $username, bool $ignoreSsl): string
    {
        $curlSsl = $ignoreSsl ? '-k ' : '';

        return 'curl ' . $curlSsl
            . '-X POST "' . $baseUrl . '?a=salt" '
            . '-H "Accept: application/json" '
            . '-H "Content-Type: application/x-www-form-urlencoded" '
            . '--data "username=' . $this->escapeForShell($username) . '"';
    }

    protected function buildLoginCurl(string $baseUrl, string $username, string $finalHash, string $ses, bool $ignoreSsl): string
    {
        $curlSsl = $ignoreSsl ? '-k ' : '';

        return 'curl ' . $curlSsl
            . '-X POST "' . $baseUrl . '?a=login" '
            . '-H "Accept: application/json" '
            . '-H "Content-Type: application/x-www-form-urlencoded" '
            . '--data "username=' . $this->escapeForShell($username)
            . '&password=' . $this->escapeForShell($finalHash)
            . '&ses=' . $this->escapeForShell($ses) . '"';
    }

    protected function buildStatusCurl(string $baseUrl, string $ses, bool $ignoreSsl): string
    {
        $curlSsl = $ignoreSsl ? '-k ' : '';

        return 'curl ' . $curlSsl
            . '-X POST "' . $baseUrl . '?a=status" '
            . '-H "Accept: application/json" '
            . '-H "Content-Type: application/x-www-form-urlencoded" '
            . '--data "ses=' . $this->escapeForShell($ses) . '"';
    }

    protected function buildBackupsCurl(string $baseUrl, string $ses, int $clientId, bool $ignoreSsl): string
    {
        $curlSsl = $ignoreSsl ? '-k ' : '';

        return 'curl ' . $curlSsl
            . '-X POST "' . $baseUrl . '?a=backups" '
            . '-H "Accept: application/json" '
            . '-H "Content-Type: application/x-www-form-urlencoded" '
            . '--data "clientid=' . $clientId . '&ses=' . $this->escapeForShell($ses) . '"';
    }

    protected function escapeForShell(string $value): string
    {
        return str_replace(
            ['\\', '"'],
            ['\\\\', '\\"'],
            $value
        );
    }
}

