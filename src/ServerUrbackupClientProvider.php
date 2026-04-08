<?php

namespace PluginGlurbackup;

require_once __DIR__ . '/ComputerServer.php';

final class ServerUrbackupClientProvider
{
    public function buildServerUiUrl(array $server): string
    {
        $protocol = (($server['protocol'] ?? 'http') === 'https') ? 'https' : 'http';
        $host     = trim((string) ($server['ip_address'] ?? ''));
        $port     = (int) ($server['port'] ?? 55414);

        if ($host === '' || $port <= 0) {
            return '';
        }

        return $protocol . '://' . $host . ':' . $port;
    }

    public function getUnlinkedClientsForServer(array $serverFields, int $serverId): array
    {
        try {
            $clients = $this->fetchStatusClientsForServer($serverFields);
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => __('Error communicating with the URBackup server.', 'glurbackup') . ' ' . $e->getMessage(),
                'clients' => [],
            ];
        }

        $linkedRows = ComputerServer::getLinkedComputersForServer($serverId);
        $unlinked   = [];

        foreach ($clients as $client) {
            $matchedBy = null;
            if ($this->findLinkedComputerMatch($linkedRows, $client, $matchedBy) !== null) {
                continue;
            }

            $ipAddress = trim((string) ($client['ip'] ?? ''));
            if ($ipAddress === '') {
                $ipAddress = '—';
            }

            $unlinked[] = [
                'name'              => trim((string) ($client['name'] ?? '')) !== '' ? (string) $client['name'] : '—',
                'ip_address'        => $ipAddress,
                'client_version'    => $this->normalizeClientVersion($client),
                'online_offline'    => $this->normalizeOnlineOffline($client),
                'last_file_backup'  => $this->normalizeLastBackupValue($client['lastbackup'] ?? null),
                'last_image_backup' => $this->normalizeLastBackupValue($client['lastbackup_image'] ?? null),
            ];
        }

        usort($unlinked, static function (array $a, array $b): int {
            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return [
            'ok'      => true,
            'message' => '',
            'clients' => $unlinked,
        ];
    }

    /**
     * Riutilizza la stessa logica di match già usata nel tab computer:
     * 1) confronto per nome normalizzato
     * 2) fallback confronto per IP normalizzato
     */
    private function findLinkedComputerMatch(array $linkedRows, array $client, ?string &$matchedBy = null): ?array
    {
        $clientName = $this->normalizeString((string) ($client['name'] ?? ''));

        if ($clientName !== '') {
            foreach ($linkedRows as $row) {
                $computerName = $this->normalizeString((string) ($row['name'] ?? ''));
                if ($computerName !== '' && $computerName === $clientName) {
                    $matchedBy = 'name';
                    return $row;
                }
            }
        }

        $clientIp = $this->normalizeIp((string) ($client['ip'] ?? ''));
        if ($clientIp !== null) {
            foreach ($linkedRows as $row) {
                $computerIp = $this->normalizeIp((string) ($row['ip_address'] ?? ''));
                if ($computerIp !== null && $computerIp === $clientIp) {
                    $matchedBy = 'ip';
                    return $row;
                }
            }
        }

        return null;
    }

    private function buildApiBaseUrl(array $server): string
    {
        $protocol = (($server['protocol'] ?? 'http') === 'https') ? 'https' : 'http';
        $host     = trim((string) ($server['ip_address'] ?? ''));
        $port     = (int) ($server['port'] ?? 55414);

        if ($host === '' || $port <= 0) {
            return '';
        }

        return $protocol . '://' . $host . ':' . $port . '/x';
    }

    private function fetchStatusClientsForServer(array $server): array
    {
        $baseUrl   = $this->buildApiBaseUrl($server);
        $username  = trim((string) ($server['username'] ?? ''));
        $password  = (string) ($server['password'] ?? '');
        $ignoreSsl = ((int) ($server['ignore_ssl'] ?? 0) === 1);

        if ($baseUrl === '' || $username === '' || $password === '') {
            throw new \RuntimeException(__('Invalid server connection data.', 'glurbackup'));
        }

        $ses = $this->loginAndGetSession($baseUrl, $username, $password, $ignoreSsl);
        if ($ses === '') {
            throw new \RuntimeException(__('URBackup login failed.', 'glurbackup'));
        }

        $statusResponse = $this->requestJson(
            $baseUrl,
            'status',
            ['ses' => $ses],
            'POST',
            $ignoreSsl
        );

        if (!is_array($statusResponse)) {
            throw new \RuntimeException(__('Invalid response from URBackup status API.', 'glurbackup'));
        }

        if (isset($statusResponse['error']) && (int) ($statusResponse['error'] ?? 0) === 1) {
            throw new \RuntimeException(__('URBackup status API returned an error.', 'glurbackup'));
        }

        if (isset($statusResponse['status']) && is_array($statusResponse['status'])) {
            return $statusResponse['status'];
        }

        if ($this->isClientList($statusResponse)) {
            return $statusResponse;
        }

        foreach (['clients', 'result', 'data'] as $key) {
            if (isset($statusResponse[$key]) && is_array($statusResponse[$key]) && $this->isClientList($statusResponse[$key])) {
                return $statusResponse[$key];
            }
        }

        throw new \RuntimeException(__('Unable to extract the clients list from URBackup.', 'glurbackup'));
    }

    private function loginAndGetSession(
        string $baseUrl,
        string $username,
        string $password,
        bool $ignoreSsl
    ): string {
        $saltResponse = $this->requestJson(
            $baseUrl,
            'salt',
            ['username' => $username],
            'POST',
            $ignoreSsl
        );

        if (
            !is_array($saltResponse)
            || empty($saltResponse['ses'])
            || empty($saltResponse['salt'])
            || empty($saltResponse['rnd'])
        ) {
            return '';
        }

        $ses          = (string) $saltResponse['ses'];
        $salt         = (string) $saltResponse['salt'];
        $rnd          = (string) $saltResponse['rnd'];
        $pbkdf2Rounds = (int) ($saltResponse['pbkdf2_rounds'] ?? 0);

        $finalPasswordHex = $this->buildPasswordHash($password, $salt, $rnd, $pbkdf2Rounds);

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
            return '';
        }

        $sessionOut = (string) ($loginResponse['session'] ?? $ses);
        return $sessionOut !== '' ? $sessionOut : $ses;
    }

    private function requestJson(
        string $baseUrl,
        string $action,
        array $params,
        string $method,
        bool $ignoreSsl
    ): ?array {
        $response = $this->httpRequest($baseUrl, $action, $params, $method, $ignoreSsl);
        $decoded  = json_decode($response['body'], true);

        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function httpRequest(
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

    private function buildPasswordHash(string $plainPassword, string $salt, string $rnd, int $pbkdf2Rounds): string
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

    private function isClientList(array $data): bool
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

    private function normalizeString(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function normalizeIp(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-' || $value === '—') {
            return null;
        }

        return $value;
    }

    private function normalizeClientVersion(array $client): string
    {
        $value = trim((string) ($client['client_version_string'] ?? ($client['client_version'] ?? '')));
        return $value !== '' ? $value : '—';
    }

    private function normalizeOnlineOffline(array $client): string
    {
        if (!array_key_exists('online', $client)) {
            return '—';
        }

        return ((bool) $client['online'])
            ? __('Online', 'glurbackup')
            : __('Offline', 'glurbackup');
    }

    private function normalizeLastBackupValue($value): string
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

        $text = trim((string) $value);
        return $text !== '' ? $text : '—';
    }
}
