#!/bin/bash
#
# Automatisches Setup Script für PHP curl.cainfo und openssl Einstellungen
# Für Froxlor/Ubuntu 22.04 Umgebungen
#

echo "=== PHP curl/OpenSSL CA-Bundle Setup ==="
echo ""

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. PHP Version finden
echo "1. Suche PHP Versionen..."
PHP_VERSIONS=$(ls -d /etc/php/*/ 2>/dev/null | grep -oP '\d+\.\d+' | sort -u)

if [ -z "$PHP_VERSIONS" ]; then
    echo -e "${RED}Keine PHP Installation gefunden!${NC}"
    exit 1
fi

echo -e "${GREEN}Gefundene PHP Versionen:${NC}"
echo "$PHP_VERSIONS"
echo ""

# 2. System CA-Bundle prüfen
echo "2. Prüfe System CA-Bundle..."
CA_BUNDLE="/etc/ssl/certs/ca-certificates.crt"

if [ ! -f "$CA_BUNDLE" ]; then
    echo -e "${RED}System CA-Bundle nicht gefunden: $CA_BUNDLE${NC}"
    echo "Installiere ca-certificates..."
    apt-get update && apt-get install -y ca-certificates
    update-ca-certificates
fi

if [ -f "$CA_BUNDLE" ]; then
    echo -e "${GREEN}✓ CA-Bundle gefunden: $CA_BUNDLE${NC}"
    echo "  Größe: $(stat -f%z "$CA_BUNDLE" 2>/dev/null || stat -c%s "$CA_BUNDLE") bytes"
    echo "  Datum: $(stat -f%Sm -t '%Y-%m-%d %H:%M:%S' "$CA_BUNDLE" 2>/dev/null || stat -c%y "$CA_BUNDLE" | cut -d' ' -f1,2)"
else
    echo -e "${RED}✗ CA-Bundle konnte nicht installiert werden!${NC}"
    exit 1
fi
echo ""

# 3. Für jede PHP Version konfigurieren
for VERSION in $PHP_VERSIONS; do
    echo -e "${YELLOW}=== PHP $VERSION ===${NC}"

    # FPM php.ini
    FPM_INI="/etc/php/$VERSION/fpm/php.ini"
    if [ -f "$FPM_INI" ]; then
        echo "Konfiguriere FPM: $FPM_INI"

        # Backup erstellen
        if [ ! -f "$FPM_INI.backup-before-algolia-fix" ]; then
            cp "$FPM_INI" "$FPM_INI.backup-before-algolia-fix"
            echo "  ✓ Backup erstellt: $FPM_INI.backup-before-algolia-fix"
        fi

        # Prüfe ob bereits gesetzt
        if grep -q "^curl.cainfo.*ca-certificates.crt" "$FPM_INI"; then
            echo "  ℹ curl.cainfo bereits gesetzt"
        else
            # Finde [curl] Section oder füge am Ende hinzu
            if grep -q "^\[curl\]" "$FPM_INI"; then
                # [curl] Section existiert - füge danach ein
                sed -i '/^\[curl\]/a curl.cainfo = "/etc/ssl/certs/ca-certificates.crt"' "$FPM_INI"
            else
                # [curl] Section existiert nicht - füge am Ende hinzu
                echo "" >> "$FPM_INI"
                echo "[curl]" >> "$FPM_INI"
                echo "curl.cainfo = \"/etc/ssl/certs/ca-certificates.crt\"" >> "$FPM_INI"
            fi
            echo -e "  ${GREEN}✓ curl.cainfo gesetzt${NC}"
        fi

        # openssl.cafile
        if grep -q "^openssl.cafile.*ca-certificates.crt" "$FPM_INI"; then
            echo "  ℹ openssl.cafile bereits gesetzt"
        else
            if grep -q "^\[openssl\]" "$FPM_INI"; then
                sed -i '/^\[openssl\]/a openssl.cafile = "/etc/ssl/certs/ca-certificates.crt"' "$FPM_INI"
            else
                echo "" >> "$FPM_INI"
                echo "[openssl]" >> "$FPM_INI"
                echo "openssl.cafile = \"/etc/ssl/certs/ca-certificates.crt\"" >> "$FPM_INI"
            fi
            echo -e "  ${GREEN}✓ openssl.cafile gesetzt${NC}"
        fi

        # openssl.capath
        if grep -q "^openssl.capath.*certs" "$FPM_INI"; then
            echo "  ℹ openssl.capath bereits gesetzt"
        else
            sed -i '/^openssl.cafile/a openssl.capath = "/etc/ssl/certs/"' "$FPM_INI"
            echo -e "  ${GREEN}✓ openssl.capath gesetzt${NC}"
        fi
    else
        echo -e "  ${YELLOW}⚠ FPM php.ini nicht gefunden: $FPM_INI${NC}"
    fi

    # CLI php.ini
    CLI_INI="/etc/php/$VERSION/cli/php.ini"
    if [ -f "$CLI_INI" ]; then
        echo "Konfiguriere CLI: $CLI_INI"

        # Backup erstellen
        if [ ! -f "$CLI_INI.backup-before-algolia-fix" ]; then
            cp "$CLI_INI" "$CLI_INI.backup-before-algolia-fix"
            echo "  ✓ Backup erstellt"
        fi

        # Gleiche Änderungen wie FPM
        grep -q "^curl.cainfo.*ca-certificates.crt" "$CLI_INI" || {
            if grep -q "^\[curl\]" "$CLI_INI"; then
                sed -i '/^\[curl\]/a curl.cainfo = "/etc/ssl/certs/ca-certificates.crt"' "$CLI_INI"
            else
                echo "" >> "$CLI_INI"
                echo "[curl]" >> "$CLI_INI"
                echo "curl.cainfo = \"/etc/ssl/certs/ca-certificates.crt\"" >> "$CLI_INI"
            fi
            echo -e "  ${GREEN}✓ curl.cainfo gesetzt${NC}"
        }

        grep -q "^openssl.cafile.*ca-certificates.crt" "$CLI_INI" || {
            if grep -q "^\[openssl\]" "$CLI_INI"; then
                sed -i '/^\[openssl\]/a openssl.cafile = "/etc/ssl/certs/ca-certificates.crt"' "$CLI_INI"
            else
                echo "" >> "$CLI_INI"
                echo "[openssl]" >> "$CLI_INI"
                echo "openssl.cafile = \"/etc/ssl/certs/ca-certificates.crt\"" >> "$CLI_INI"
            fi
            echo -e "  ${GREEN}✓ openssl.cafile gesetzt${NC}"
        }

        grep -q "^openssl.capath.*certs" "$CLI_INI" || {
            sed -i '/^openssl.cafile/a openssl.capath = "/etc/ssl/certs/"' "$CLI_INI"
            echo -e "  ${GREEN}✓ openssl.capath gesetzt${NC}"
        }
    else
        echo -e "  ${YELLOW}⚠ CLI php.ini nicht gefunden: $CLI_INI${NC}"
    fi

    echo ""
done

# 4. PHP-FPM Services neu starten
echo "4. Starte PHP-FPM Services neu..."
for VERSION in $PHP_VERSIONS; do
    SERVICE="php${VERSION}-fpm"
    if systemctl list-units --type=service --all | grep -q "$SERVICE"; then
        echo "  Restarte $SERVICE..."
        systemctl restart "$SERVICE"
        if systemctl is-active --quiet "$SERVICE"; then
            echo -e "  ${GREEN}✓ $SERVICE läuft${NC}"
        else
            echo -e "  ${RED}✗ $SERVICE Fehler!${NC}"
            systemctl status "$SERVICE" --no-pager -l
        fi
    fi
done
echo ""

# 5. Verifikation
echo "5. Verifikation..."
for VERSION in $PHP_VERSIONS; do
    echo -e "${YELLOW}PHP $VERSION:${NC}"

    # CLI Test
    if [ -f "/usr/bin/php$VERSION" ]; then
        CURL_CAINFO=$(/usr/bin/php$VERSION -i 2>/dev/null | grep "curl.cainfo" | head -1 | awk '{print $3}')
        OPENSSL_CAFILE=$(/usr/bin/php$VERSION -i 2>/dev/null | grep "openssl.cafile" | head -1 | awk '{print $3}')

        echo "  CLI:"
        if [ -n "$CURL_CAINFO" ] && [ "$CURL_CAINFO" != "no" ]; then
            echo -e "    ${GREEN}✓ curl.cainfo = $CURL_CAINFO${NC}"
        else
            echo -e "    ${RED}✗ curl.cainfo nicht gesetzt${NC}"
        fi

        if [ -n "$OPENSSL_CAFILE" ] && [ "$OPENSSL_CAFILE" != "no" ]; then
            echo -e "    ${GREEN}✓ openssl.cafile = $OPENSSL_CAFILE${NC}"
        else
            echo -e "    ${RED}✗ openssl.cafile nicht gesetzt${NC}"
        fi
    fi
    echo ""
done

echo -e "${GREEN}=== Setup abgeschlossen ===${NC}"
echo ""
echo "Nächste Schritte:"
echo "1. Laden Sie phpinfo_curl.php auf Ihren Webserver hoch"
echo "2. Rufen Sie es im Browser auf: https://ihre-domain.de/phpinfo_curl.php"
echo "3. Prüfen Sie ob curl.cainfo und openssl.cafile jetzt gesetzt sind"
echo "4. Testen Sie die Algolia-Verbindung"
echo "5. Löschen Sie phpinfo_curl.php wieder!"
echo ""
echo "Bei Problemen: Backups sind verfügbar als *.backup-before-algolia-fix"
