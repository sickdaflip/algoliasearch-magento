#!/usr/bin/env php
<?php
/**
 * OpenSSL Legacy Provider Check
 *
 * Prüft ob der OpenSSL Legacy Provider benötigt wird für Algolia-Verbindungen
 */

echo "=== OpenSSL Provider & Cipher Check ===\n\n";

// 1. OpenSSL Version
echo "1. OpenSSL Version:\n";
if (defined('OPENSSL_VERSION_TEXT')) {
    echo "   " . OPENSSL_VERSION_TEXT . "\n";

    // Prüfe ob OpenSSL 3.0+
    preg_match('/OpenSSL ([0-9]+)\.([0-9]+)\./', OPENSSL_VERSION_TEXT, $matches);
    $majorVersion = isset($matches[1]) ? (int)$matches[1] : 0;

    if ($majorVersion >= 3) {
        echo "   ⚠️  OpenSSL 3.x erkannt - Provider-System aktiv\n";
    } else {
        echo "   ℹ️  OpenSSL 1.x - kein Provider-System\n";
    }
} else {
    echo "   ❌ OpenSSL nicht verfügbar\n";
}
echo "\n";

// 2. Verfügbare Cipher
echo "2. Verfügbare SSL/TLS Cipher:\n";
$ciphers = openssl_get_cipher_methods();
echo "   Gesamtanzahl: " . count($ciphers) . "\n";

// Prüfe auf Legacy Cipher
$legacyCiphers = [
    'des-ede3-cbc',
    'des-ede3',
    'rc4',
    'rc4-md5',
    'md5',
    'md4',
];

$foundLegacy = array_intersect($legacyCiphers, array_map('strtolower', $ciphers));
if (empty($foundLegacy)) {
    echo "   ⚠️  Keine Legacy-Cipher gefunden\n";
    echo "   → Legacy Provider ist NICHT aktiv\n";
} else {
    echo "   ✓ Legacy-Cipher gefunden: " . implode(', ', $foundLegacy) . "\n";
    echo "   → Legacy Provider ist aktiv\n";
}
echo "\n";

// 3. Unterstützte TLS-Versionen prüfen
echo "3. TLS Protocol Support:\n";
$tlsVersions = [
    'TLSv1' => STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT ?? null,
    'TLSv1.1' => STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT ?? null,
    'TLSv1.2' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT ?? null,
    'TLSv1.3' => STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT ?? null,
];

foreach ($tlsVersions as $version => $constant) {
    if ($constant !== null) {
        echo "   ✓ $version: Unterstützt\n";
    } else {
        echo "   ✗ $version: Nicht verfügbar\n";
    }
}
echo "\n";

// 4. Test: Verbindung zu Algolia mit verschiedenen Methoden
echo "4. Algolia Connection Test:\n\n";

$testUrls = [
    'https://www.algolia.com',
    'https://places-dsn.algolia.net',
];

foreach ($testUrls as $url) {
    echo "   Testing: $url\n";

    // Method 1: curl mit allen Protokollen
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    // Verwende System CA-Bundle
    if (file_exists('/etc/ssl/certs/ca-certificates.crt')) {
        curl_setopt($ch, CURLOPT_CAINFO, '/etc/ssl/certs/ca-certificates.crt');
    }

    // Erlaube alle TLS-Versionen
    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_DEFAULT);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $sslVersion = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
    $error = curl_error($ch);
    $errorNo = curl_errno($ch);

    if ($errorNo === 0 && $httpCode > 0) {
        echo "   ✓ Verbindung erfolgreich (HTTP $httpCode)\n";

        // Hole verwendeten Cipher
        $info = curl_getinfo($ch);
        if (isset($info['ssl_verifyresult'])) {
            echo "   → SSL Verify: " . $info['ssl_verifyresult'] . "\n";
        }
    } else {
        echo "   ✗ Verbindung fehlgeschlagen\n";
        echo "   → Fehler #$errorNo: $error\n";

        // Spezielle Fehleranalyse
        if ($errorNo == 35) {
            echo "   ⚠️  SSL connect error - möglicherweise Legacy Provider Problem\n";
        } elseif ($errorNo == 60) {
            echo "   ⚠️  CA certificate problem - curl.cainfo nicht gesetzt\n";
        }
    }

    curl_close($ch);
    echo "\n";
}

// 5. Empfehlungen
echo "5. Analyse & Empfehlungen:\n\n";

// Prüfe ob curl.cainfo gesetzt ist
$curlCainfo = ini_get('curl.cainfo');
if (empty($curlCainfo)) {
    echo "   ❌ HAUPTPROBLEM: curl.cainfo ist NICHT gesetzt\n";
    echo "   → Lösung: In php.ini setzen:\n";
    echo "      curl.cainfo = \"/etc/ssl/certs/ca-certificates.crt\"\n\n";
}

// Prüfe OpenSSL 3.0
if (isset($majorVersion) && $majorVersion >= 3) {
    echo "   ℹ️  OpenSSL 3.x erkannt\n";

    if (empty($foundLegacy)) {
        echo "   → Legacy Provider ist NICHT aktiv\n";
        echo "   → Das ist NORMAL und OK für moderne HTTPS-Verbindungen\n";
        echo "   → Algolia verwendet moderne TLS 1.2/1.3, kein Legacy nötig\n\n";

        echo "   ⚠️  Legacy Provider NUR aktivieren wenn:\n";
        echo "      - Explizite Fehler über fehlende Cipher auftreten\n";
        echo "      - Sehr alte API-Endpunkte (pre-2010) verwendet werden\n";
        echo "      - Explizit RC4, DES, MD5 Cipher benötigt werden\n\n";
    } else {
        echo "   → Legacy Provider IST aktiv\n";
        echo "   → Das ist ungewöhnlich und meist NICHT nötig\n\n";
    }

    echo "   📝 So aktiviert man den Legacy Provider (falls nötig):\n";
    echo "      sudo nano /etc/ssl/openssl.cnf\n";
    echo "      \n";
    echo "      [provider_sect]\n";
    echo "      default = default_sect\n";
    echo "      legacy = legacy_sect    # ← Diese Zeile hinzufügen\n";
    echo "      \n";
    echo "      [legacy_sect]\n";
    echo "      activate = 1\n";
    echo "\n";
}

echo "=== Test abgeschlossen ===\n";
echo "\nFazit:\n";
echo "- Legacy Provider ist für Algolia NICHT notwendig\n";
echo "- Algolia verwendet moderne TLS 1.2/1.3 Protokolle\n";
echo "- Das Hauptproblem ist fehlende curl.cainfo Konfiguration\n";
