<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class ServiceWorkerController
{
    public function __invoke(): Response
    {
        $version = File::exists(base_path('RELEASE')) ? trim(File::get(base_path('RELEASE'))) : 'dev';

        $precache = [
            '/offline',
            '/manifest.webmanifest',
            '/icons/logo.svg',
            '/icons/icon-192.png',
            ...$this->viteAssets(),
        ];

        $script = str_replace(
            ['__VERSION__', '__PRECACHE__'],
            [$version, json_encode(array_values(array_unique($precache)), JSON_UNESCAPED_SLASHES)],
            File::get(resource_path('js/sw.js')),
        );

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /** @return list<string> */
    private function viteAssets(): array
    {
        $manifestPath = public_path('build/manifest.json');

        if (! File::exists($manifestPath)) {
            return [];
        }

        /** @var array<string, array{file: string, css?: list<string>}> $manifest */
        $manifest = json_decode(File::get($manifestPath), true) ?: [];

        $files = [];
        foreach ($manifest as $entry) {
            $files[] = '/build/'.$entry['file'];
            foreach ($entry['css'] ?? [] as $css) {
                $files[] = '/build/'.$css;
            }
        }

        return $files;
    }
}
