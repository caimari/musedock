<?php

namespace Screenart\Musedock;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Aws\S3\S3Client;

class Storage
{
    protected static $instances = [];

    public static function disk($disk = null)
    {
        $config = require APP_ROOT . '/config/filesystems.php';
        $diskName = $disk ?: $config['default_disk'];

        if (isset(self::$instances[$diskName])) {
            return self::$instances[$diskName];
        }

        $diskConfig = $config['disks'][$diskName];

        switch ($diskConfig['driver']) {
            case 'local':
                $adapter = new LocalFilesystemAdapter($diskConfig['root']);
                $filesystem = new Filesystem($adapter);
                break;

            case 's3':
                $client = new S3Client([
                    'credentials' => [
                        'key' => $diskConfig['key'],
                        'secret' => $diskConfig['secret'],
                    ],
                    'region' => $diskConfig['region'],
                    'version' => 'latest',
                    'endpoint' => $diskConfig['endpoint'] ?? null,
                    'use_path_style_endpoint' => $diskConfig['use_path_style_endpoint'] ?? false,
                ]);

                $adapter = new AwsS3V3Adapter($client, $diskConfig['bucket']);
                $filesystem = new Filesystem($adapter);
                break;

            default:
                throw new \Exception("Driver de almacenamiento no soportado: {$diskConfig['driver']}");
        }

        self::$instances[$diskName] = $filesystem;
        return $filesystem;
    }

    public static function put($path, $contents, $disk = null)
    {
        return self::disk($disk)->write($path, $contents);
    }

    public static function putFile($path, $filePath, $disk = null)
    {
        return self::disk($disk)->writeStream($path, fopen($filePath, 'r'));
    }

    public static function delete($path, $disk = null)
    {
        return self::disk($disk)->delete($path);
    }

    public static function url($path, $disk = null)
    {
        $config = require APP_ROOT . '/config/filesystems.php';
        $diskName = $disk ?: $config['default_disk'];
        $diskConfig = $config['disks'][$diskName];

        return rtrim($diskConfig['url'], '/') . '/' . ltrim($path, '/');
    }

    public static function exists($path, $disk = null)
    {
        return self::disk($disk)->fileExists($path);
    }

    public static function get($path, $disk = null)
    {
        return self::disk($disk)->read($path);
    }
}
