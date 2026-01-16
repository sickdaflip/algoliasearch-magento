# PHP curl Konfiguration nach Ubuntu 22.04 Upgrade

## Problem
Nach dem Upgrade von Ubuntu 20.04 auf 22.04 kann Algolia nicht mehr mit dem Server kommunizieren.

## Hauptursachen

### 1. **Veraltetes CA-Zertifikat Bundle** (bereits behoben)
- ✓ Datei `lib/AlgoliaSearch/resources/ca-bundle.crt` wurde aktualisiert

### 2. **OpenSSL 3.0 Kryptographie-Richtlinien**
Ubuntu 22.04 kommt mit OpenSSL 3.0, das strengere Sicherheitsrichtlinien hat:

**Problem:** Alte/unsichere Cipher und TLS-Versionen sind standardmäßig deaktiviert

**Prüfen:**
```bash
openssl version
# Sollte zeigen: OpenSSL 3.0.x
```

**Lösung (falls TLS-Probleme auftreten):**
```bash
# /etc/ssl/openssl.cnf bearbeiten
sudo nano /etc/ssl/openssl.cnf

# Am Anfang der Datei (falls nicht vorhanden):
openssl_conf = openssl_init

# Am Ende der Datei hinzufügen:
[openssl_init]
ssl_conf = ssl_sect

[ssl_sect]
system_default = system_default_sect

[system_default_sect]
MinProtocol = TLSv1.2
CipherString = DEFAULT@SECLEVEL=1
```

**SECLEVEL Erklärung:**
- `SECLEVEL=2` (Ubuntu 22.04 Standard) = Sehr streng, blockiert SHA1, RSA < 2048 bit
- `SECLEVEL=1` = Weniger streng, erlaubt 2048-bit RSA und SHA1 (für Kompatibilität)

⚠️ **Nur auf SECLEVEL=1 setzen wenn SECLEVEL=2 Probleme macht!**

### 3. **PHP curl Extension Konfiguration**

**Prüfen ob curl installiert ist:**
```bash
php -m | grep curl
```

**Falls nicht installiert:**
```bash
# Für PHP 7.2
sudo apt-get install php7.2-curl

# Service neu starten
sudo systemctl restart php7.2-fpm   # Falls PHP-FPM
sudo systemctl restart apache2       # Falls Apache mit mod_php
```

### 4. **php.ini Einstellungen**

**php.ini Datei finden:**
```bash
# CLI version
php --ini

# FPM version (für Webserver)
php-fpm7.2 -i | grep "Loaded Configuration File"
```

**Wichtige Einstellungen in php.ini:**

```ini
; CA-Bundle für curl (WICHTIG!)
curl.cainfo = "/etc/ssl/certs/ca-certificates.crt"

; CA-Bundle für OpenSSL
openssl.cafile = "/etc/ssl/certs/ca-certificates.crt"
openssl.capath = "/etc/ssl/certs/"

; URL-Funktionen aktivieren
allow_url_fopen = On

; Maximale Ausführungszeit für API-Calls
max_execution_time = 300

; Memory Limit (für große Index-Operationen)
memory_limit = 512M
```

**Nach Änderungen:**
```bash
# PHP-FPM neu starten
sudo systemctl restart php7.2-fpm

# Apache neu starten
sudo systemctl restart apache2

# Nginx neu laden
sudo systemctl reload nginx
```

### 5. **System CA-Bundle aktuell halten**

```bash
# System CA-Zertifikate aktualisieren
sudo apt-get update
sudo apt-get install --reinstall ca-certificates

# Zertifikate neu generieren
sudo update-ca-certificates
```

### 6. **Firewall Regeln prüfen**

```bash
# Ubuntu Firewall Status
sudo ufw status

# Falls Algolia blockiert ist:
sudo ufw allow out 443/tcp comment 'HTTPS für Algolia'
```

### 7. **Test durchführen**

```bash
# Diagnose-Script ausführen
php test_algolia_connection.php

# Oder manueller curl-Test
curl -v https://places-dsn.algolia.net
```

## Häufige Fehler und Lösungen

### Fehler 60: SSL certificate problem
```
curl: (60) SSL certificate problem: unable to get local issuer certificate
```

**Ursache:** CA-Bundle fehlt oder ist veraltet

**Lösung:**
1. System CA-Bundle aktualisieren (siehe oben)
2. In php.ini setzen: `curl.cainfo = "/etc/ssl/certs/ca-certificates.crt"`
3. Magento ca-bundle.crt aktualisieren (bereits erledigt)

### Fehler 35: SSL connect error
```
curl: (35) error:1425F102:SSL routines:ssl_choose_client_version:unsupported protocol
```

**Ursache:** OpenSSL 3.0 blockiert alte TLS-Versionen

**Lösung:**
- OpenSSL Konfiguration anpassen (siehe Abschnitt 2 oben)
- SECLEVEL temporär auf 1 setzen

### Fehler 7: Failed to connect to host
```
curl: (7) Failed to connect to *.algolia.net port 443
```

**Ursache:** Firewall oder Netzwerk-Problem

**Lösung:**
1. Firewall prüfen: `sudo ufw status`
2. DNS prüfen: `nslookup places-dsn.algolia.net`
3. Ping testen: `ping -c 3 algolia.net`

## Debugging

### PHP curl Info ausgeben
```php
<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.algolia.com');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);

echo "HTTP Code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
echo "Error: " . curl_error($ch) . "\n";
echo "SSL Verify Result: " . curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT) . "\n";

curl_close($ch);
```

### Magento Logs prüfen
```bash
tail -f var/log/exception.log
tail -f var/log/system.log
```

### System Logs prüfen
```bash
# PHP-FPM Logs
tail -f /var/log/php7.2-fpm.log

# Apache Logs
tail -f /var/log/apache2/error.log

# Nginx Logs
tail -f /var/log/nginx/error.log

# System Journal
sudo journalctl -u php7.2-fpm -f
```

## Checkliste

- [ ] PHP curl Extension installiert (`php -m | grep curl`)
- [ ] System CA-Zertifikate aktuell (`sudo update-ca-certificates`)
- [ ] Magento ca-bundle.crt aktualisiert (✓ erledigt)
- [ ] php.ini curl.cainfo gesetzt
- [ ] OpenSSL Konfiguration geprüft
- [ ] Firewall erlaubt HTTPS ausgehend
- [ ] Test-Script ausgeführt: `php test_algolia_connection.php`
- [ ] PHP-FPM/Apache/Nginx neu gestartet

## Empfohlene Konfiguration

**Minimale php.ini für Algolia:**
```ini
curl.cainfo = "/etc/ssl/certs/ca-certificates.crt"
openssl.cafile = "/etc/ssl/certs/ca-certificates.crt"
allow_url_fopen = On
max_execution_time = 300
memory_limit = 512M
```

**Minimale OpenSSL Konfiguration (nur bei Problemen):**
```
MinProtocol = TLSv1.2
CipherString = DEFAULT@SECLEVEL=1
```
