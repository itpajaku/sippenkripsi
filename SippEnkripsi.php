<?php

/**
 * SippEnkripsi
 *
 * Standalone entry point file for SippEnkripsi.
 * This file provides backward compatibility for legacy non-composer projects
 * or scripts that directly include SippEnkripsi.php.
 */

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/src/SippEnkripsi.php';

if (!class_exists('Legacyen', false)) {
    class_alias(\SippEnkripsi\SippEnkripsi::class, 'Legacyen');
}

if (!class_exists('SippEnkripsi', false)) {
    class_alias(\SippEnkripsi\SippEnkripsi::class, 'SippEnkripsi');
}
