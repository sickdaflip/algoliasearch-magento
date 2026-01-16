#!/usr/bin/env php
<?php
/**
 * Algolia Connection Diagnostic Test
 *
 * Testet die curl-Konfiguration und Verbindung zu Algolia nach Ubuntu 22.04 Upgrade
 * Verwendung: php test_algolia_connection.php
 */

echo "=== Algolia Connection Diagnostic Test ===\n\n";

// 1. PHP Version prüfen
echo "1. PHP Version:\n";
echo "   PHP " . phpversion() . "\n\n";

// 2. curl Extension prüfen
echo "2. curl Extension:\n";
if (!function_exists('curl_init')) {
    echo "   ❌ FEHLER: curl Extension ist NICHT installiert!\n";
    echo "   Lösung: sudo apt-get install php7.2-curl && sudo systemctl restart php7.2-fpm\n";
    exit(1);
} else {
    echo "   ✓ curl Extension ist installiert\n";
    $curlVersion = curl_version();
    echo "   curl Version: " . $curlVersion['version'] . "\n";
    echo "   SSL Version: " . $curlVersion['ssl_version'] . "\n";
    echo "   libz Version: " . $curlVersion['libz_version'] . "\n";
    echo "   Protocols: " . implode(', ', $curlVersion['protocols']) . "\n\n";
}

// 3. OpenSSL Version prüfen
echo "3. OpenSSL:\n";
if (defined('OPENSSL_VERSION_TEXT')) {
    echo "   ✓ " . OPENSSL_VERSION_TEXT . "\n\n";
} else {
    echo "   ❌ OpenSSL nicht verfügbar\n\n";
}

// 4. CA Bundle Pfad prüfen
echo "4. CA Certificate Bundle:\n";
$caInfoFromPhpIni = ini_get('curl.cainfo');
$caPathFromPhpIni = ini_get('openssl.cafile');
echo "   curl.cainfo (php.ini): " . ($caInfoFromPhpIni ?: 'nicht gesetzt') . "\n";
echo "   openssl.cafile (php.ini): " . ($caPathFromPhpIni ?: 'nicht gesetzt') . "\n";

// Standard System CA-Bundle Pfade
$systemCaBundles = [
    '/etc/ssl/certs/ca-certificates.crt',  // Debian/Ubuntu
    '/etc/pki/tls/certs/ca-bundle.crt',    // RedHat/CentOS
];
foreach ($systemCaBundles as $path) {
    if (file_exists($path)) {
        echo "   ✓ System CA-Bundle gefunden: $path\n";
        echo "     Größe: " . number_format(filesize($path)) . " bytes\n";
        echo "     Letzte Änderung: " . date('Y-m-d H:i:s', filemtime($path)) . "\n";
    }
}
echo "\n";

// 5. TLS/SSL Cipher Suites prüfen
echo "5. Verfügbare SSL/TLS Ciphers:\n";
$ciphers = openssl_get_cipher_methods();
echo "   Anzahl verfügbarer Cipher: " . count($ciphers) . "\n";
echo "   TLS 1.2 Support: " . (in_array('TLS1.2', $ciphers) ? '✓' : '❌') . "\n";
echo "   TLS 1.3 Support: " . (in_array('TLS1.3', $ciphers) ? '✓' : '❌') . "\n\n";

// 6. Test-Verbindung zu Algolia
echo "6. Verbindungstest zu Algolia API:\n";
$testUrls = [
    'https://www.algolia.com' => 'Algolia Website',
    'https://places-dsn.algolia.net' => 'Algolia Places DSN',
];

foreach ($testUrls as $url => $description) {
    echo "\n   Testing: $description ($url)\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    // Verwende System CA-Bundle falls php.ini keins definiert
    if (empty($caInfoFromPhpIni) && file_exists('/etc/ssl/certs/ca-certificates.crt')) {
        curl_setopt($ch, CURLOPT_CAINFO, '/etc/ssl/certs/ca-certificates.crt');
        echo "   → Verwende System CA-Bundle\n";
    }

    // Verbose output für Debugging
    curl_setopt($ch, CURLOPT_VERBOSE, false);

    $startTime = microtime(true);
    $response = curl_exec($ch);
    $duration = round((microtime(true) - $startTime) * 1000, 2);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errorNo = curl_errno($ch);

    if ($errorNo === 0 && $httpCode > 0) {
        echo "   ✓ Verbindung erfolgreich!\n";
        echo "   → HTTP Status: $httpCode\n";
        echo "   → Dauer: {$duration}ms\n";
        echo "   → SSL/TLS: " . curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT) . " (0 = OK)\n";
    } else {
        echo "   ❌ Verbindung FEHLGESCHLAGEN!\n";
        echo "   → Fehler #$errorNo: $error\n";
        echo "   → HTTP Code: $httpCode\n";

        // Häufige Fehler und Lösungen
        if ($errorNo == 60) {
            echo "\n   LÖSUNG für Fehler 60 (SSL certificate problem):\n";
            echo "   - CA-Bundle ist veraltet oder fehlt\n";
            echo "   - Lösung 1: lib/AlgoliaSearch/resources/ca-bundle.crt aktualisieren\n";
            echo "   - Lösung 2: In php.ini setzen: curl.cainfo=/etc/ssl/certs/ca-certificates.crt\n";
        } elseif ($errorNo == 35) {
            echo "\n   LÖSUNG für Fehler 35 (SSL connect error):\n";
            echo "   - OpenSSL Konfiguration Problem\n";
            echo "   - Ubuntu 22.04 blockiert alte TLS-Versionen\n";
            echo "   - Prüfen Sie: /etc/ssl/openssl.cnf\n";
        } elseif ($errorNo == 7) {
            echo "\n   LÖSUNG für Fehler 7 (Failed to connect):\n";
            echo "   - Firewall blockiert ausgehende Verbindungen\n";
            echo "   - Proxy-Einstellungen fehlen\n";
        }
    }

    curl_close($ch);
}

echo "\n\n=== Test abgeschlossen ===\n";
echo "\nFalls Verbindungsprobleme auftreten, prüfen Sie:\n";
echo "1. php.ini Einstellungen (siehe test_output unten)\n";
echo "2. OpenSSL Konfiguration: /etc/ssl/openssl.cnf\n";
echo "3. Firewall: sudo ufw status\n";
echo "4. System-Logs: journalctl -xe\n\n";

// 7. Relevante php.ini Einstellungen ausgeben
echo "7. Relevante php.ini Einstellungen:\n";
$relevantSettings = [
    'allow_url_fopen',
    'curl.cainfo',
    'openssl.cafile',
    'openssl.capath',
    'date.timezone',
];
foreach ($relevantSettings as $setting) {
    $value = ini_get($setting);
    echo "   $setting = " . ($value ?: '(leer)') . "\n";
}
echo "\n   Geladene php.ini: " . php_ini_loaded_file() . "\n";
echo "   Zusätzliche .ini Dateien: " . php_ini_scanned_files() . "\n";
