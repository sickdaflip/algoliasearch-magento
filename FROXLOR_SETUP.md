# Algolia Fix für Froxlor-Umgebungen

## Problem
Nach Ubuntu 22.04 Upgrade funktioniert Algolia nicht mehr. Bei Froxlor-Setups muss die PHP-Konfiguration speziell angepasst werden.

## Diagnose durchführen

### 1. phpinfo_curl.php hochladen
Laden Sie die Datei `phpinfo_curl.php` in Ihr Magento-Root-Verzeichnis hoch und rufen Sie sie im Browser auf:

```
https://ihre-domain.de/phpinfo_curl.php
```

Diese Seite zeigt:
- Welche php.ini tatsächlich verwendet wird
- Ob curl.cainfo gesetzt ist
- Ob die Verbindung zu Algolia funktioniert

**WICHTIG:** Löschen Sie die Datei nach der Prüfung!

## Lösung: php.ini konfigurieren

Bei Froxlor gibt es **3 Möglichkeiten**, die curl.cainfo Einstellung zu setzen:

---

### ✅ Option 1: Global für alle Domains (EMPFOHLEN)

**Vorteil:** Einmalige Konfiguration, gilt für alle vhosts

**Schritt 1:** php.ini bearbeiten
```bash
sudo nano /etc/php/7.2/fpm/php.ini
```

**Schritt 2:** Diese Zeilen hinzufügen oder ändern (suchen Sie nach `[curl]` und `[openssl]`):
```ini
[curl]
; CA-Bundle für curl
curl.cainfo = "/etc/ssl/certs/ca-certificates.crt"

[openssl]
; CA-Bundle für OpenSSL
openssl.cafile = "/etc/ssl/certs/ca-certificates.crt"
openssl.capath = "/etc/ssl/certs/"
```

**Schritt 3:** PHP-FPM neu starten
```bash
sudo systemctl restart php7.2-fpm
```

**Schritt 4:** Testen
Rufen Sie `phpinfo_curl.php` erneut auf und prüfen Sie, ob curl.cainfo jetzt gesetzt ist.

---

### Option 2: Per PHP-FPM Version in Froxlor

**Vorteil:** Über Froxlor-Webinterface konfigurierbar

**Schritt 1:** In Froxlor einloggen als Admin

**Schritt 2:** Navigieren zu:
```
Einstellungen → PHP Konfiguration → PHP-FPM Versionen
```

**Schritt 3:** Ihre PHP 7.2 Version bearbeiten

**Schritt 4:** Im Feld "Zusätzliche php.ini Einstellungen" folgendes eintragen:
```ini
curl.cainfo = /etc/ssl/certs/ca-certificates.crt
openssl.cafile = /etc/ssl/certs/ca-certificates.crt
openssl.capath = /etc/ssl/certs/
```

**Schritt 5:** Speichern und PHP-FPM neu laden
```bash
sudo systemctl restart php7.2-fpm
```

---

### Option 3: Per Domain/vhost-spezifische Pool-Konfiguration

**Vorteil:** Nur für eine spezifische Domain

**Schritt 1:** Pool-Konfiguration finden
```bash
# Froxlor speichert Pool-Configs meist hier:
ls -la /etc/php/7.2/fpm/pool.d/

# Oder:
ls -la /var/customers/webs/web*/php-fpm.conf
```

**Schritt 2:** Die Pool-Config Ihrer Domain bearbeiten
```bash
# Beispiel für Domain:
sudo nano /etc/php/7.2/fpm/pool.d/web1-ihre-domain.conf
```

**Schritt 3:** Am Ende der Datei hinzufügen:
```ini
php_admin_value[curl.cainfo] = /etc/ssl/certs/ca-certificates.crt
php_admin_value[openssl.cafile] = /etc/ssl/certs/ca-certificates.crt
php_admin_value[openssl.capath] = /etc/ssl/certs/
```

**Schritt 4:** PHP-FPM neu starten
```bash
sudo systemctl restart php7.2-fpm
```

---

## Wichtige Froxlor-spezifische Hinweise

### 1. Froxlor überschreibt Konfigurationen
Wenn Sie manuell Pool-Dateien bearbeiten, kann Froxlor diese beim nächsten "Rebuild" überschreiben. Daher ist **Option 1 oder 2** empfohlen.

### 2. Mehrere PHP-Versionen
Falls Sie mehrere PHP-Versionen haben (7.2, 7.4, 8.0, etc.), müssen Sie die Einstellung für **jede verwendete Version** setzen:
```bash
sudo nano /etc/php/7.2/fpm/php.ini
sudo nano /etc/php/7.4/fpm/php.ini
sudo nano /etc/php/8.0/fpm/php.ini
# etc.
```

### 3. Pool-Status prüfen
```bash
# Aktive PHP-FPM Pools anzeigen
sudo systemctl status php7.2-fpm

# Pool-Konfigurationen testen
sudo php-fpm7.2 -t
```

### 4. Nach Froxlor-Updates
Nach Froxlor-Updates oder "Configuration Rebuild":
- Prüfen Sie ob Ihre Einstellungen noch vorhanden sind
- Falls nicht: Option 1 (globale php.ini) verwenden

---

## Checkliste

- [ ] 1. `phpinfo_curl.php` hochgeladen und aufgerufen
- [ ] 2. Aktuelle php.ini-Datei identifiziert
- [ ] 3. `curl.cainfo` und `openssl.cafile` gesetzt (Option 1, 2 oder 3)
- [ ] 4. PHP-FPM neu gestartet
- [ ] 5. `phpinfo_curl.php` erneut geprüft - Einstellungen sichtbar?
- [ ] 6. Verbindungstest zu Algolia erfolgreich?
- [ ] 7. Magento Cache geleert: `php bin/magento cache:flush`
- [ ] 8. Algolia Reindex getestet
- [ ] 9. `phpinfo_curl.php` gelöscht (Sicherheit!)

---

## Weitere Updates durchführen

### CA-Bundle aktualisieren
```bash
# 1. Git-Änderungen ziehen
cd /pfad/zu/magento
git pull origin claude/fix-algolia-ubuntu-0bN9v

# 2. Oder manuell das CA-Bundle ersetzen:
sudo cp /etc/ssl/certs/ca-certificates.crt lib/AlgoliaSearch/resources/ca-bundle.crt
sudo chown www-data:www-data lib/AlgoliaSearch/resources/ca-bundle.crt
```

### System CA-Zertifikate aktualisieren
```bash
sudo apt-get update
sudo apt-get install --reinstall ca-certificates
sudo update-ca-certificates
```

---

## Fehlerbehebung

### "curl.cainfo zeigt immer noch 'nicht gesetzt'"
1. Prüfen Sie, ob Sie die richtige php.ini bearbeitet haben (CLI vs FPM)
2. PHP-FPM wirklich neu gestartet? `sudo systemctl status php7.2-fpm`
3. Syntax korrekt? Kein Semikolon am Anfang der Zeile
4. Bei Froxlor: Cache leeren und Konfiguration neu erstellen

### "Verbindung schlägt immer noch fehl"
1. Testen Sie curl auf System-Ebene: `curl -v https://www.algolia.com`
2. Prüfen Sie Firewall: `sudo ufw status`
3. Prüfen Sie PHP-FPM Logs: `sudo tail -50 /var/log/php7.2-fpm.log`
4. Prüfen Sie ob der richtige Pool läuft: `ps aux | grep php-fpm`

### "Nach Froxlor-Rebuild sind Einstellungen weg"
- Verwenden Sie **Option 1** (globale php.ini)
- Diese wird von Froxlor nicht überschrieben

---

## Kontakt & Support

Bei weiteren Problemen:
1. Führen Sie `test_algolia_connection.php` via CLI aus
2. Prüfen Sie die Logs in `var/log/exception.log`
3. Dokumentation: `PHP_CURL_CONFIG.md`

**Wichtig:** Nach erfolgreicher Konfiguration `phpinfo_curl.php` löschen!
