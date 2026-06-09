<?php

class S3 {
    private static $configLoaded = false;
    private static $cfgAccessKey = '';
    private static $cfgSecretKey = '';
    private static $cfgEndpoint = '';
    private static $cfgRegion = 'auto';
    private static $cfgBucket = '';
    private static $cfgPublicUrl = '';

    private static function loadConfig() {
        if (self::$configLoaded) return;
        self::$cfgAccessKey = self::resolveConfig('ACCESS_KEY');
        self::$cfgSecretKey = self::resolveConfig('SECRET_KEY');
        self::$cfgEndpoint  = self::resolveConfig('ENDPOINT');
        self::$cfgRegion    = self::resolveConfig('REGION');
        self::$cfgBucket    = self::resolveConfig('BUCKET');
        self::$cfgPublicUrl = self::resolveConfig('PUBLIC_URL');
        if (self::$cfgRegion === '') self::$cfgRegion = 'auto';
        self::$configLoaded = true;
    }

    private static function resolveConfig($suffix) {
        $const = 'S3_' . $suffix;
        if (defined($const) && constant($const) !== '') return constant($const);
        if (function_exists('getSetting')) {
            $dbVal = getSetting('s3_' . strtolower($suffix));
            if ($dbVal !== '') return $dbVal;
        }
        if (!empty($_ENV['S3_' . $suffix])) return $_ENV['S3_' . $suffix];
        return '';
    }

    public static function getResolvedConfig() {
        self::loadConfig();
        return [
            'access_key' => self::$cfgAccessKey,
            'secret_key' => self::$cfgSecretKey,
            'endpoint'   => self::$cfgEndpoint,
            'region'     => self::$cfgRegion,
            'bucket'     => self::$cfgBucket,
            'public_url' => self::$cfgPublicUrl,
        ];
    }

    public static function isConfigured() {
        self::loadConfig();
        return self::$cfgAccessKey !== '' && self::$cfgSecretKey !== '' && self::$cfgEndpoint !== '' && self::$cfgBucket !== '';
    }

    private static function hmac($key, $msg) {
        return hash_hmac('sha256', $msg, $key, true);
    }

    private static function getSignatureKey($key, $dateStamp) {
        $kDate    = self::hmac('AWS4' . $key, $dateStamp);
        $kRegion  = self::hmac($kDate, self::$cfgRegion);
        $kService = self::hmac($kRegion, 's3');
        return self::hmac($kService, 'aws4_request');
    }

    private static function sign($method, $uri, $queryString, $headers, $payloadHash, $amzDate = null) {
        self::loadConfig();
        $algorithm   = 'AWS4-HMAC-SHA256';
        if ($amzDate === null) {
            $amzDate = $headers['X-Amz-Date'] ?? gmdate('Ymd\THis\Z');
        }
        $dateStamp   = substr($amzDate, 0, 8);

        $canonicalHeaders = '';
        $signedHeadersArr = [];
        ksort($headers);
        foreach ($headers as $k => $v) {
            $lk = strtolower($k);
            $canonicalHeaders .= $lk . ':' . trim($v) . "\n";
            $signedHeadersArr[] = $lk;
        }
        $signedHeaders = implode(';', $signedHeadersArr);
        $canonicalRequest = $method . "\n"
            . $uri . "\n"
            . $queryString . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $payloadHash;

        $credentialScope = $dateStamp . '/' . self::$cfgRegion . '/s3/aws4_request';
        $stringToSign = $algorithm . "\n"
            . $amzDate . "\n"
            . $credentialScope . "\n"
            . hash('sha256', $canonicalRequest);

        $signingKey = self::getSignatureKey(self::$cfgSecretKey, $dateStamp);
        $signature  = hash_hmac('sha256', $stringToSign, $signingKey);

        return $algorithm . ' Credential=' . self::$cfgAccessKey . '/' . $credentialScope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;
    }

    private static $lastResponseBody = '';

    public static function getLastResponseBody() {
        return self::$lastResponseBody;
    }

    private static function exec($method, $path, $payload = '', $extraHeaders = [], $timeout = 30) {
        self::loadConfig();
        $endpoint = rtrim(self::$cfgEndpoint, '/');
        $uri = '/' . ltrim($path, '/');
        $url = $endpoint . $uri;

        $payloadHash = hash('sha256', $payload);
        $amzDate = gmdate('Ymd\THis\Z');

        $headers = array_merge([
            'Host'                 => parse_url($endpoint, PHP_URL_HOST),
            'X-Amz-Content-SHA256' => $payloadHash,
            'X-Amz-Date'           => $amzDate,
        ], $extraHeaders);

        $parsed = parse_url($uri);
        $signPath = $parsed['path'] ?? '/';
        $signQuery = $parsed['query'] ?? '';

        $signature = self::sign($method, $signPath, $signQuery, $headers, $payloadHash, $amzDate);
        $headers['Authorization'] = $signature;

        $ch = curl_init($url);
        $httpHeaders = [];
        foreach ($headers as $k => $v) {
            $httpHeaders[] = "$k: $v";
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $httpHeaders,
            CURLOPT_TIMEOUT        => $timeout,
        ]);
        if ($method === 'PUT' || $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        self::$lastResponseBody = $resp;

        return ['code' => $httpCode, 'body' => $resp];
    }

    public static function upload($localPath, $s3Name) {
        self::loadConfig();
        self::$uploadError = '';
        if (!file_exists($localPath)) {
            self::$uploadError = "Arquivo local não encontrado: {$localPath}";
            error_log("S3::upload FAILED: local file not found {$localPath}");
            return false;
        }
        $size = filesize($localPath);
        $ext = strtolower(pathinfo($s3Name, PATHINFO_EXTENSION));
        $mimeMap = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon', 'zip' => 'application/zip', 'pdf' => 'application/pdf',
        ];
        $contentType = $mimeMap[$ext] ?? 'application/octet-stream';

        $endpoint = rtrim(self::$cfgEndpoint, '/');
        $uri = '/' . self::$cfgBucket . '/' . ltrim($s3Name, '/');
        $url = $endpoint . $uri;

        $payloadHash = 'UNSIGNED-PAYLOAD';
        $amzDate = gmdate('Ymd\THis\Z');

        $headers = [
            'Host'                 => parse_url($endpoint, PHP_URL_HOST),
            'X-Amz-Content-SHA256' => $payloadHash,
            'X-Amz-Date'           => $amzDate,
            'Content-Length'       => $size,
            'Content-Type'         => $contentType,
        ];

        $parsed = parse_url($uri);
        $signPath = $parsed['path'] ?? '/';
        $signQuery = $parsed['query'] ?? '';

        $signature = self::sign('PUT', $signPath, $signQuery, $headers, $payloadHash, $amzDate);
        $headers['Authorization'] = $signature;

        $ch = curl_init($url);
        $httpHeaders = [];
        foreach ($headers as $k => $v) {
            $httpHeaders[] = "$k: $v";
        }

        $fp = @fopen($localPath, 'rb');
        if (!$fp) {
            self::$uploadError = "Falha ao abrir arquivo para leitura: {$localPath}";
            error_log("S3::upload FAILED: cannot open {$localPath}");
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_UPLOAD         => true,
            CURLOPT_HTTPHEADER     => $httpHeaders,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_INFILE         => $fp,
            CURLOPT_INFILESIZE     => $size,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        self::$lastResponseBody = $resp;

        if (!in_array($httpCode, [200, 204])) {
            $detail = '';
            if ($resp !== false && $resp !== '') {
                $xml = @simplexml_load_string($resp);
                if ($xml && isset($xml->Code) && isset($xml->Message)) {
                    $detail = (string)$xml->Code . ': ' . (string)$xml->Message;
                } else {
                    $detail = substr($resp, 0, 300);
                }
            } elseif ($curlErr !== '') {
                $detail = $curlErr;
            }
            $errMsg = "S3::upload FAILED: HTTP {$httpCode} for {$s3Name}";
            if ($detail !== '') $errMsg .= " — {$detail}";
            self::$uploadError = $detail ?: "HTTP {$httpCode}";
            error_log($errMsg);
        }

        return in_array($httpCode, [200, 204]);
    }

    private static $downloadError = '';
    private static $uploadError = '';

    public static function getLastDownloadError() {
        return self::$downloadError;
    }

    public static function getLastUploadError() {
        return self::$uploadError;
    }

    public static function download($s3Name, $localPath) {
        self::$downloadError = '';
        $dir = dirname($localPath);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                self::$downloadError = "Falha ao criar diretório: {$dir}";
                error_log("S3::download FAILED to create dir {$dir}");
                return false;
            }
        }

        $endpoint = rtrim(self::$cfgEndpoint, '/');
        $uri = '/' . self::$cfgBucket . '/' . ltrim($s3Name, '/');
        $url = $endpoint . $uri;

        $payloadHash = hash('sha256', '');
        $amzDate = gmdate('Ymd\THis\Z');

        $headers = [
            'Host'                 => parse_url($endpoint, PHP_URL_HOST),
            'X-Amz-Content-SHA256' => $payloadHash,
            'X-Amz-Date'           => $amzDate,
        ];

        $parsed = parse_url($uri);
        $signPath = $parsed['path'] ?? '/';
        $signQuery = $parsed['query'] ?? '';

        $signature = self::sign('GET', $signPath, $signQuery, $headers, $payloadHash, $amzDate);
        $headers['Authorization'] = $signature;

        $fp = @fopen($localPath, 'wb');
        if (!$fp) {
            self::$downloadError = "Falha ao abrir arquivo para escrita: {$localPath}";
            error_log("S3::download FAILED to open {$localPath} for writing");
            return false;
        }

        $ch = curl_init($url);
        $httpHeaders = [];
        foreach ($headers as $k => $v) {
            $httpHeaders[] = "$k: $v";
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HTTPHEADER     => $httpHeaders,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($resp === false) {
            @unlink($localPath);
            self::$downloadError = "Erro de rede: {$curlErr}";
            error_log("S3::download FAILED for {$s3Name}: curl_error={$curlErr}");
            return false;
        }

        if ($httpCode !== 200) {
            @unlink($localPath);
            self::$downloadError = "HTTP {$httpCode}";
            error_log("S3::download FAILED for {$s3Name}: HTTP {$httpCode}");
            return false;
        }
        return true;
    }

    public static function fileExists($s3Name) {
        $result = self::exec('HEAD', self::$cfgBucket . '/' . ltrim($s3Name, '/'));
        return $result['code'] === 200;
    }

    public static function delete($s3Name) {
        $result = self::exec('DELETE', self::$cfgBucket . '/' . ltrim($s3Name, '/'));
        return $result['code'] === 204 || $result['code'] === 200;
    }

    public static function getUrl($s3Name) {
        self::loadConfig();
        if (self::$cfgPublicUrl !== '') {
            return rtrim(self::$cfgPublicUrl, '/') . '/' . ltrim($s3Name, '/');
        }
        return rtrim(self::$cfgEndpoint, '/') . '/' . self::$cfgBucket . '/' . ltrim($s3Name, '/');
    }

    public static function listBucketsWithCreds($endpoint, $accessKey, $secretKey, $region = 'auto') {
        $saved = self::getResolvedConfig();
        self::$configLoaded = false;
        self::$cfgEndpoint = rtrim($endpoint, '/');
        self::$cfgAccessKey = $accessKey;
        self::$cfgSecretKey = $secretKey;
        self::$cfgRegion = $region ?: 'auto';
        self::$configLoaded = true;
        $buckets = self::listBuckets();
        self::$configLoaded = false;
        self::$cfgEndpoint = $saved['endpoint'];
        self::$cfgAccessKey = $saved['access_key'];
        self::$cfgSecretKey = $saved['secret_key'];
        self::$cfgRegion = $saved['region'];
        self::$cfgBucket = $saved['bucket'];
        self::$cfgPublicUrl = $saved['public_url'];
        self::$configLoaded = true;
        return $buckets;
    }

    private static function parseS3Xml($body) {
        $xml = simplexml_load_string($body);
        if (!$xml) return null;
        $ns = $xml->getNamespaces(true);
        if (!empty($ns[''])) {
            return $xml->children($ns['']);
        }
        return $xml;
    }

    public static function listBuckets() {
        self::loadConfig();
        $result = self::exec('GET', '');
        if ($result['code'] !== 200) return [];
        $xml = self::parseS3Xml($result['body']);
        if (!$xml) return [];
        $buckets = [];
        foreach ($xml->Buckets->Bucket ?? [] as $b) {
            $buckets[] = [
                'name'    => (string)$b->Name,
                'created' => (string)$b->CreationDate,
            ];
        }
        if (empty($buckets)) {
            error_log("S3 listBuckets: 200 OK but no buckets. Raw body: " . substr($result['body'], 0, 2000));
        }
        return $buckets;
    }

    public static function listFiles($prefix = '') {
        $uri = self::$cfgBucket;
        if ($prefix !== '') $uri .= '?prefix=' . urlencode($prefix);
        $result = self::exec('GET', $uri);
        if ($result['code'] !== 200) return [];
        $xml = self::parseS3Xml($result['body']);
        if (!$xml) return [];
        $files = [];
        foreach ($xml->Contents ?? [] as $c) {
            $files[] = [
                'key'           => (string)$c->Key,
                'size'          => (int)$c->Size,
                'etag'          => (string)$c->ETag,
                'last_modified' => (string)$c->LastModified,
            ];
        }
        return $files;
    }

    public static function getUrlFromB2Name($b2Name) {
        return self::getUrl($b2Name);
    }
}
