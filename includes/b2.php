<?php

class B2 {
    private static $authToken = null;
    private static $apiUrl = null;
    private static $downloadUrl = null;
    private static $bucketId = null;
    private static $accountId = null;

    private static $configLoaded = false;
    private static $cfgKeyId = '';
    private static $cfgAppKey = '';
    private static $cfgBucket = '';
    private static $cfgCdnUrl = '';

    private static function loadConfig() {
        if (self::$configLoaded) return;
        self::$cfgKeyId = self::resolveConfig('KEY_ID');
        self::$cfgAppKey = self::resolveConfig('APP_KEY');
        self::$cfgBucket = self::resolveConfig('BUCKET');
        self::$cfgCdnUrl = self::resolveConfig('CDN_URL');
        self::$configLoaded = true;
    }

    private static function resolveConfig($suffix) {
        $const = 'B2_' . $suffix;
        if (defined($const) && constant($const) !== '') {
            return constant($const);
        }
        if (function_exists('getSetting')) {
            $dbVal = getSetting('b2_' . strtolower($suffix));
            if ($dbVal !== '') return $dbVal;
        }
        $envVal = getenv('B2_' . $suffix);
        if ($envVal !== false && $envVal !== '') return $envVal;
        return '';
    }

    public static function getResolvedConfig() {
        self::loadConfig();
        return [
            'key_id'  => self::$cfgKeyId,
            'app_key' => self::$cfgAppKey,
            'bucket'  => self::$cfgBucket,
            'cdn_url' => self::$cfgCdnUrl,
        ];
    }

    public static function isConfigured() {
        self::loadConfig();
        return self::$cfgKeyId !== '' && self::$cfgAppKey !== '' && self::$cfgBucket !== '';
    }

    public static function authorize() {
        if (self::$authToken !== null) return true;
        self::loadConfig();
        if (!self::isConfigured()) return false;

        $ch = curl_init('https://api.backblazeb2.com/b2api/v2/b2_authorize_account');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => self::$cfgKeyId . ':' . self::$cfgAppKey,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("B2 authorize failed (HTTP $httpCode): " . substr($resp, 0, 500));
            return false;
        }

        $data = json_decode($resp, true);
        if (!$data || empty($data['authorizationToken'])) {
            error_log("B2 authorize: invalid response");
            return false;
        }

        self::$authToken = $data['authorizationToken'];
        self::$apiUrl = $data['apiUrl'] . '/b2api/v2';
        self::$downloadUrl = $data['downloadUrl'];
        self::$accountId = $data['accountId'] ?? null;
        return true;
    }

    private static function getBucketId() {
        if (self::$bucketId !== null) return self::$bucketId;
        if (!self::authorize()) return null;

        $ch = curl_init(self::$apiUrl . '/b2_list_buckets');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . self::$authToken],
            CURLOPT_POSTFIELDS => json_encode([
                'accountId' => self::$accountId,
                'bucketName' => self::$cfgBucket,
                'bucketTypes' => ['allPrivate', 'allPublic'],
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) return null;
        $data = json_decode($resp, true);
        if (empty($data['buckets'][0]['bucketId'])) return null;

        self::$bucketId = $data['buckets'][0]['bucketId'];
        return self::$bucketId;
    }

    public static function upload($localPath, $b2Name) {
        if (!self::authorize()) return false;

        $bucketId = self::getBucketId();
        if (!$bucketId) return false;

        $ch = curl_init(self::$apiUrl . '/b2_get_upload_url');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . self::$authToken],
            CURLOPT_POSTFIELDS => json_encode(['bucketId' => $bucketId]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("B2 get_upload_url failed (HTTP $httpCode)");
            return false;
        }

        $upData = json_decode($resp, true);
        if (!$upData || empty($upData['uploadUrl'])) return false;

        $uploadUrl = $upData['uploadUrl'];
        $uploadToken = $upData['authorizationToken'];
        $content = file_get_contents($localPath);
        if ($content === false) return false;

        $sha1 = sha1($content);
        $mime = self::getMimeType($b2Name);

        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $uploadToken,
                'X-Bz-File-Name: ' . urlencode($b2Name),
                'Content-Type: ' . $mime,
                'X-Bz-Content-Sha1: ' . $sha1,
                'Content-Length: ' . strlen($content),
            ],
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_TIMEOUT => 120,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("B2 upload failed (HTTP $httpCode) for $b2Name: " . substr($resp, 0, 500));
            return false;
        }

        return true;
    }

    public static function delete($b2Name) {
        if (!self::authorize()) return false;
        if (!self::getBucketId()) return false;

        $ch = curl_init(self::$apiUrl . '/b2_list_file_versions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . self::$authToken],
            CURLOPT_POSTFIELDS => json_encode([
                'bucketId' => self::$bucketId,
                'startFileName' => $b2Name,
                'maxFileCount' => 1,
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) return false;
        $data = json_decode($resp, true);
        if (empty($data['files'][0])) return false;

        $file = $data['files'][0];
        if ($file['fileName'] !== $b2Name) return false;

        $ch = curl_init(self::$apiUrl . '/b2_delete_file_version');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . self::$authToken],
            CURLOPT_POSTFIELDS => json_encode([
                'fileId' => $file['fileId'],
                'fileName' => $b2Name,
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    public static function getUrl($b2Name) {
        self::loadConfig();
        if (self::$cfgCdnUrl) {
            return rtrim(self::$cfgCdnUrl, '/') . '/' . ltrim($b2Name, '/');
        }
        if (!self::authorize()) return false;
        return self::$downloadUrl . '/file/' . self::$cfgBucket . '/' . ltrim($b2Name, '/');
    }

    public static function fileExists($b2Name) {
        if (!self::authorize()) return false;
        if (!self::getBucketId()) return false;

        $ch = curl_init(self::$apiUrl . '/b2_list_file_names');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . self::$authToken],
            CURLOPT_POSTFIELDS => json_encode([
                'bucketId' => self::$bucketId,
                'prefix' => $b2Name,
                'maxFileCount' => 1,
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) return false;
        $data = json_decode($resp, true);
        return !empty($data['files'][0]) && $data['files'][0]['fileName'] === $b2Name;
    }

    public static function listFiles($prefix = '') {
        if (!self::authorize()) return [];
        if (!self::getBucketId()) return [];

        $files = [];
        $startFileName = null;

        while (true) {
            $params = [
                'bucketId' => self::$bucketId,
                'prefix' => $prefix,
                'maxFileCount' => 1000,
            ];
            if ($startFileName) $params['startFileName'] = $startFileName;

            $ch = curl_init(self::$apiUrl . '/b2_list_file_names');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Authorization: ' . self::$authToken],
                CURLOPT_POSTFIELDS => json_encode($params),
                CURLOPT_TIMEOUT => 30,
            ]);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) break;

            $data = json_decode($resp, true);
            if (empty($data['files'])) break;

            $files = array_merge($files, $data['files']);

            if (empty($data['nextFileName'])) break;
            $startFileName = $data['nextFileName'];
        }

        return $files;
    }

    public static function listBuckets() {
        if (!self::authorize()) return [];
        $ch = curl_init(self::$apiUrl . '/b2_list_buckets');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . self::$authToken],
            CURLOPT_POSTFIELDS => json_encode(['accountId' => self::$accountId]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) return [];
        $data = json_decode($resp, true);
        return $data['buckets'] ?? [];
    }

    public static function configureCORS() {
        if (!self::authorize()) return false;
        if (!self::getBucketId()) return false;

        $corsRules = [[
            'corsRuleName' => 'allowAllOrigins',
            'allowedOrigins' => ['*'],
            'allowedHeaders' => ['authorization', 'content-type', 'x-bz-file-name', 'x-bz-content-sha1'],
            'allowedOperations' => ['b2_download_file_by_name', 'b2_upload_file', 'b2_get_upload_url'],
            'maxAgeSeconds' => 3600,
        ]];

        $ch = curl_init(self::$apiUrl . '/b2_update_bucket');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . self::$authToken],
            CURLOPT_POSTFIELDS => json_encode([
                'bucketId' => self::$bucketId,
                'corsRules' => $corsRules,
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("B2 CORS config failed: " . substr($resp, 0, 500));
            return false;
        }
        return true;
    }

    private static function getMimeType($filename) {
        $map = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'zip' => 'application/zip', 'gz' => 'application/gzip',
            'pdf' => 'application/pdf', 'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg',
            'mp4' => 'video/mp4', 'html' => 'text/html', 'css' => 'text/css',
            'js' => 'application/javascript', 'json' => 'application/json',
            'sfc' => 'application/octet-stream', 'smc' => 'application/octet-stream',
            'nes' => 'application/octet-stream', 'gba' => 'application/octet-stream',
            'gb' => 'application/octet-stream', 'md' => 'application/octet-stream',
            'bin' => 'application/octet-stream', 'chd' => 'application/octet-stream',
            'iso' => 'application/octet-stream', 'z64' => 'application/octet-stream',
        ];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return $map[$ext] ?? 'application/octet-stream';
    }

    public static function getFileId($b2Name) {
        if (!self::authorize()) return null;
        if (!self::getBucketId()) return null;

        $ch = curl_init(self::$apiUrl . '/b2_list_file_names');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . self::$authToken],
            CURLOPT_POSTFIELDS => json_encode([
                'bucketId' => self::$bucketId,
                'prefix' => $b2Name,
                'maxFileCount' => 1,
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) return null;
        $data = json_decode($resp, true);
        if (empty($data['files'][0]) || $data['files'][0]['fileName'] !== $b2Name) return null;
        return $data['files'][0]['fileId'];
    }
}
