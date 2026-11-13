<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Minishlink\WebPush\VAPID;

/**
 * Chaves VAPID do Web Push. Se não estiverem no .env (VAPID_PUBLIC_KEY /
 * VAPID_PRIVATE_KEY), são geradas uma única vez e guardadas no storage
 * (em produção: ~/financee-data/storage, fora do deploy). Nada a configurar.
 */
final class VapidKeys
{
    public static function ensure(): void
    {
        if (config('webpush.vapid.public_key') && config('webpush.vapid.private_key')) {
            return;
        }

        $path = storage_path('app/private/vapid.json');

        if (File::exists($path)) {
            /** @var array{publicKey: string, privateKey: string} $keys */
            $keys = json_decode(File::get($path), true);
        } else {
            $keys = self::generate();
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($keys));
            @chmod($path, 0600);
        }

        config([
            'webpush.vapid.public_key' => $keys['publicKey'],
            'webpush.vapid.private_key' => $keys['privateKey'],
            'webpush.vapid.subject' => config('webpush.vapid.subject') ?: config('app.url'),
        ]);
    }

    /**
     * Gera o par de chaves (curva P-256). Se o OpenSSL do PHP não encontrar o
     * arquivo de configuração (comum em PHP "portátil"), usa uma configuração mínima.
     *
     * @return array{publicKey: string, privateKey: string}
     */
    private static function generate(): array
    {
        try {
            return VAPID::createVapidKeys();
        } catch (\Throwable) {
            $cnf = tempnam(sys_get_temp_dir(), 'ossl');
            file_put_contents($cnf, "[req]\ndefault_bits = 2048\ndistinguished_name = dn\n[dn]\n");

            try {
                $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC, 'config' => $cnf]);
                $details = $key ? openssl_pkey_get_details($key) : false;

                if (! $details || ! isset($details['ec'])) {
                    throw new \RuntimeException('Não foi possível gerar as chaves VAPID.');
                }

                $pad = fn (string $v) => str_pad($v, 32, chr(0), STR_PAD_LEFT);
                $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');

                return [
                    'publicKey' => $b64("\x04".$pad($details['ec']['x']).$pad($details['ec']['y'])),
                    'privateKey' => $b64($pad($details['ec']['d'])),
                ];
            } finally {
                @unlink($cnf);
            }
        }
    }

    public static function publicKey(): string
    {
        self::ensure();

        return (string) config('webpush.vapid.public_key');
    }
}
