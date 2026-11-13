// Gera os ícones do PWA a partir de resources/icons/*.svg (rodar: node scripts/icons.mjs)
import sharp from 'sharp';
import { readFileSync, copyFileSync } from 'node:fs';

const logo = readFileSync('resources/icons/logo.svg');
const maskable = readFileSync('resources/icons/logo-maskable.svg');

const jobs = [
    [logo, 192, 'public/icons/icon-192.png'],
    [logo, 512, 'public/icons/icon-512.png'],
    [maskable, 512, 'public/icons/icon-maskable-512.png'],
    [maskable, 180, 'public/icons/apple-touch-icon.png'],
    [logo, 32, 'public/icons/favicon-32.png'],
];

for (const [svg, size, out] of jobs) {
    await sharp(svg, { density: 384 }).resize(size, size).png({ compressionLevel: 9 }).toFile(out);
    console.log('✓', out);
}
copyFileSync('resources/icons/logo.svg', 'public/icons/logo.svg');
