<?php
/**
 * PHP curl Info für Froxlor-basierte Setups
 *
 * Zeigt die tatsächlich verwendete php.ini und curl-Konfiguration
 * Upload diese Datei in Ihr Magento-Verzeichnis und rufen Sie sie im Browser auf
 */

echo "<h1>PHP curl Konfiguration</h1>";
echo "<p><strong>WICHTIG:</strong> Löschen Sie diese Datei nach der Prüfung!</p>";

echo "<h2>1. PHP Version & Setup</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><td>PHP Version</td><td>" . phpversion() . "</td></tr>";
echo "<tr><td>Server API</td><td>" . php_sapi_name() . "</td></tr>";
echo "<tr><td>Geladene php.ini</td><td>" . php_ini_loaded_file() . "</td></tr>";
echo "<tr><td>Zusätzliche .ini Dateien</td><td>" . str_replace(',', '<br>', php_ini_scanned_files()) . "</td></tr>";
echo "</table>";

echo "<h2>2. curl Extension</h2>";
echo "<table border='1' cellpadding='5'>";
if (function_exists('curl_init')) {
    echo "<tr><td>curl Extension</td><td style='color:green;'><strong>✓ Installiert</strong></td></tr>";
    $cv = curl_version();
    echo "<tr><td>curl Version</td><td>" . $cv['version'] . "</td></tr>";
    echo "<tr><td>SSL Version</td><td>" . $cv['ssl_version'] . "</td></tr>";
    echo "<tr><td>libz Version</td><td>" . $cv['libz_version'] . "</td></tr>";
} else {
    echo "<tr><td>curl Extension</td><td style='color:red;'><strong>✗ NICHT installiert</strong></td></tr>";
}
echo "</table>";

echo "<h2>3. CA Certificate Bundle Einstellungen</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><td>curl.cainfo</td><td>" . (ini_get('curl.cainfo') ?: '<span style=\"color:red;\">NICHT GESETZT</span>') . "</td></tr>";
echo "<tr><td>openssl.cafile</td><td>" . (ini_get('openssl.cafile') ?: '<span style=\"color:red;\">NICHT GESETZT</span>') . "</td></tr>";
echo "<tr><td>openssl.capath</td><td>" . (ini_get('openssl.capath') ?: '<span style=\"color:red;\">NICHT GESETZT</span>') . "</td></tr>";

// System CA-Bundle prüfen
$systemCA = '/etc/ssl/certs/ca-certificates.crt';
if (file_exists($systemCA)) {
    echo "<tr><td>System CA-Bundle</td><td style='color:green;'>✓ Vorhanden: $systemCA<br>Größe: " . number_format(filesize($systemCA)) . " bytes<br>Datum: " . date('Y-m-d H:i:s', filemtime($systemCA)) . "</td></tr>";
} else {
    echo "<tr><td>System CA-Bundle</td><td style='color:red;'>✗ Nicht gefunden: $systemCA</td></tr>";
}

// Magento CA-Bundle prüfen
$magentoCA = __DIR__ . '/lib/AlgoliaSearch/resources/ca-bundle.crt';
if (file_exists($magentoCA)) {
    $content = file_get_contents($magentoCA, false, null, 0, 500);
    preg_match('/Date: (.+)/', $content, $matches);
    $bundleDate = $matches[1] ?? 'unbekannt';
    echo "<tr><td>Magento Algolia CA-Bundle</td><td style='color:green;'>✓ Vorhanden: $magentoCA<br>Größe: " . number_format(filesize($magentoCA)) . " bytes<br>Bundle Datum: $bundleDate</td></tr>";
} else {
    echo "<tr><td>Magento Algolia CA-Bundle</td><td style='color:orange;'>? Nicht gefunden: $magentoCA</td></tr>";
}
echo "</table>";

echo "<h2>4. Verbindungstest zu Algolia</h2>";
$testUrl = 'https://www.algolia.com';
echo "<p>Teste: $testUrl</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

// Fallback zu System CA-Bundle wenn php.ini keins hat
if (empty(ini_get('curl.cainfo')) && file_exists($systemCA)) {
    curl_setopt($ch, CURLOPT_CAINFO, $systemCA);
}

$startTime = microtime(true);
$response = curl_exec($ch);
$duration = round((microtime(true) - $startTime) * 1000, 2);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$errorNo = curl_errno($ch);

echo "<table border='1' cellpadding='5'>";
if ($errorNo === 0 && $httpCode > 0) {
    echo "<tr><td>Status</td><td style='color:green;'><strong>✓ Verbindung erfolgreich!</strong></td></tr>";
    echo "<tr><td>HTTP Code</td><td>$httpCode</td></tr>";
    echo "<tr><td>Dauer</td><td>{$duration}ms</td></tr>";
    echo "<tr><td>SSL Verify</td><td>" . curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT) . " (0 = OK)</td></tr>";
} else {
    echo "<tr><td>Status</td><td style='color:red;'><strong>✗ Verbindung fehlgeschlagen!</strong></td></tr>";
    echo "<tr><td>Fehler #$errorNo</td><td>$error</td></tr>";
    echo "<tr><td>HTTP Code</td><td>$httpCode</td></tr>";

    if ($errorNo == 60) {
        echo "<tr><td colspan='2' style='background:#fff3cd;'><strong>Lösung:</strong> curl.cainfo in php.ini setzen (siehe unten)</td></tr>";
    }
}
echo "</table>";

curl_close($ch);

echo "<h2>5. Empfohlene Froxlor-Konfiguration</h2>";
echo "<div style='background:#f0f0f0; padding:10px; font-family:monospace;'>";
echo "<strong>Variante 1: Global für alle PHP-FPM Pools (empfohlen)</strong><br>";
echo "Datei: <code>/etc/php/7.2/fpm/php.ini</code><br><br>";
echo "[curl]<br>";
echo "curl.cainfo = \"/etc/ssl/certs/ca-certificates.crt\"<br><br>";
echo "[openssl]<br>";
echo "openssl.cafile = \"/etc/ssl/certs/ca-certificates.crt\"<br>";
echo "openssl.capath = \"/etc/ssl/certs/\"<br><br>";
echo "Danach: <code>sudo systemctl restart php7.2-fpm</code><br><br>";
echo "<hr>";
echo "<strong>Variante 2: Per Pool in Froxlor</strong><br>";
echo "Froxlor Admin → PHP → PHP-FPM Versionen → Ihre PHP 7.2 Version bearbeiten<br>";
echo "Unter 'php.ini Einstellungen' hinzufügen:<br><br>";
echo "curl.cainfo=/etc/ssl/certs/ca-certificates.crt<br>";
echo "openssl.cafile=/etc/ssl/certs/ca-certificates.crt<br>";
echo "openssl.capath=/etc/ssl/certs/<br><br>";
echo "Pool Konfiguration liegt meist in: <code>/etc/php/7.2/fpm/pool.d/</code>";
echo "</div>";

echo "<hr>";
echo "<p style='color:red;'><strong>WICHTIG: Löschen Sie diese Datei nach der Prüfung!</strong></p>";
echo "<p><small>Erstellt: " . date('Y-m-d H:i:s') . "</small></p>";
