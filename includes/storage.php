<?php

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
}
