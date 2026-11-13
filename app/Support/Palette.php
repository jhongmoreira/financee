<?php

namespace App\Support;

final class Palette
{
    /** Cores disponíveis para contas, cartões e categorias. */
    public const COLORS = [
        '#059669', '#10b981', '#0d9488', '#0284c7', '#2563eb', '#4f46e5',
        '#7c3aed', '#9333ea', '#c026d3', '#db2777', '#e11d48', '#dc2626',
        '#ea580c', '#d97706', '#ca8a04', '#65a30d', '#475569', '#1f2937',
    ];

    /** Ícones (Heroicons) disponíveis para categorias. */
    public const CATEGORY_ICONS = [
        'shopping-cart', 'shopping-bag', 'building-storefront', 'cake', 'home', 'home-modern',
        'bolt', 'fire', 'wifi', 'device-phone-mobile', 'tv', 'truck', 'wrench-screwdriver',
        'map-pin', 'paper-airplane', 'globe-americas', 'heart', 'beaker', 'lifebuoy',
        'academic-cap', 'book-open', 'film', 'musical-note', 'ticket', 'puzzle-piece',
        'gift', 'sparkles', 'scissors', 'face-smile', 'bug-ant', 'user-group', 'users',
        'receipt-percent', 'document-text', 'scale', 'credit-card', 'banknotes', 'wallet',
        'briefcase', 'building-office', 'computer-desktop', 'cloud', 'currency-dollar',
        'arrow-trending-up', 'chart-bar', 'trophy', 'light-bulb', 'camera', 'paint-brush',
        'key', 'shield-check', 'rocket-launch', 'sun', 'cube', 'tag', 'ellipsis-horizontal',
    ];

    /** Ícones de contas (tipos) que também podem aparecer em avatares. */
    public const ACCOUNT_ICONS = ['building-library', 'banknotes', 'wallet', 'square-3-stack-3d'];

    /** Garante um ícone existente (evita erro ao renderizar valor inválido). */
    public static function safeIcon(?string $icon): string
    {
        return in_array($icon, [...self::CATEGORY_ICONS, ...self::ACCOUNT_ICONS], true) ? $icon : 'tag';
    }
}
