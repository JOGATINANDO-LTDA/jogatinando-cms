<?php

require_once __DIR__ . '/s3.php';

class Storage {
    private static function base() {
        return UPLOAD_PATH;
    }

    private static function baseUrl() {
        return UPLOAD_URL;
    }

    public static function upload($sourcePath, $destRelPath) {
        $destPath = self::base() . '/' . ltrim($destRelPath, '/');
        $dir = dirname($destPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (is_uploaded_file($sourcePath)) {
            return move_uploaded_file($sourcePath, $destPath);
        }
        if (rename($sourcePath, $destPath)) {
            return true;
        }
        if (copy($sourcePath, $destPath)) {
            unlink($sourcePath);
            return true;
        }
        return false;
    }

    public static function delete($relPath) {
        $fullPath = self::base() . '/' . ltrim($relPath, '/');
        if (is_dir($fullPath)) {
            $items = scandir($fullPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                self::delete($relPath . '/' . $item);
            }
            return @rmdir($fullPath);
        }
        if (file_exists($fullPath)) {
            return @unlink($fullPath);
        }
        return true;
    }

    public static function url($relPath) {
        return self::baseUrl() . '/' . ltrim($relPath, '/');
    }

    public static function exists($relPath) {
        return file_exists(self::base() . '/' . ltrim($relPath, '/'));
    }

    public static function extractZip($relZipPath, $relDestDir) {
        $zipFile = self::base() . '/' . ltrim($relZipPath, '/');
        $destDir = self::base() . '/' . ltrim($relDestDir, '/');
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $zip = new ZipArchive();
        $res = $zip->open($zipFile);
        if ($res !== true) {
            return false;
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, '..') !== false || strpos($name, '/') === 0) {
                $zip->close();
                return false;
            }
        }
        $zip->extractTo($destDir);
        $zip->close();
        return true;
    }

    public static function uploadAndExtract($sourcePath, $destRelDir) {
        $tmpPath = rtrim($destRelDir, '/') . '/_upload.zip';
        if (!self::upload($sourcePath, $tmpPath)) {
            return false;
        }
        if (!self::extractZip($tmpPath, $destRelDir)) {
            self::delete($tmpPath);
            return false;
        }
        self::delete($tmpPath);
        return true;
    }

    // === S3 Mirror Methods ===

    public static function isS3Configured() {
        return S3::isConfigured();
    }

    public static function mirrorToS3($localPath, $s3Name) {
        if (!self::isS3Configured()) return false;
        return S3::upload($localPath, $s3Name);
    }

    public static function deleteFromS3($s3Name) {
        if (!self::isS3Configured()) return false;
        return S3::delete($s3Name);
    }

    public static function getS3Url($s3Name) {
        if (!self::isS3Configured()) return false;
        return S3::getUrl($s3Name);
    }

    public static function downloadFromS3($s3Name, $localPath) {
        if (!self::isS3Configured()) return false;
        return S3::download($s3Name, $localPath);
    }

    public static function extractFromS3Zip($zipS3Name, $destRelDir) {
        if (!self::isS3Configured()) return false;

        $tmpDir = self::base() . '/_b2tmp';
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);

        $zipName = basename($zipS3Name);
        $tmpZip = $tmpDir . '/' . $zipName;

        $content = @file_get_contents(S3::getUrl($zipS3Name));
        if ($content === false) return false;
        file_put_contents($tmpZip, $content);

        $destDir = self::base() . '/' . ltrim($destRelDir, '/');
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);

        $zip = new ZipArchive();
        $res = $zip->open($tmpZip);
        if ($res !== true) { unlink($tmpZip); return false; }

        $zip->extractTo($destDir);
        $zip->close();
        unlink($tmpZip);
        return true;
    }

    public static function s3FileExists($s3Name) {
        if (!self::isS3Configured()) return false;
        return S3::fileExists($s3Name);
    }

    public static function listS3Files($prefix = '') {
        if (!self::isS3Configured()) return [];
        return S3::listFiles($prefix);
    }

    public static function configureS3CORS() {
        if (!self::isS3Configured()) return false;
        return false; // R2 CORS configured via dashboard
    }
}
