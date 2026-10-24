<?php

namespace App\Support\Vault;

/** Cesty uvnitř složky webu. Agent posílá relativní cesty, `..` neprojde. */
final class VaultPath
{
    public static function normalize(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new VaultException('Neplatná cesta.');
        }

        $parts = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                throw new VaultException('Cesta nesmí vést ven ze složky webu (..).');
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    public static function join(string $root, string $path): string
    {
        $root = '/'.trim($root, '/');
        $path = self::normalize($path);

        return $path === '' ? $root : rtrim($root, '/').'/'.$path;
    }
}
