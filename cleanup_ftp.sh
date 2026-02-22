#!/bin/bash
# cleanup_ftp.sh - Entfernt nicht benötigte Dateien/Ordner vom FTP-Server
# Nutzt lftp für FTPS-Verbindung

set -e

# Lade Konfiguration
if [ ! -f .deploy.env ]; then
    echo "❌ .deploy.env nicht gefunden!"
    exit 1
fi

source .deploy.env

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}═══════════════════════════════════════════${NC}"
echo -e "${BLUE}🧹 EMOS FTP Cleanup${NC}"
echo -e "${BLUE}═══════════════════════════════════════════${NC}"
echo ""

# Ordner/Dateien die gelösch werden sollen
TO_DELETE=(
    ".git"
    ".github"
    "composer.json"
    "composer.lock"
    ".gitignore"
    "UPGRADE.md"
    "UPGRADE_CHECKLIST.md"
    "DEPLOY_LOCAL.md"
    "deploy.sh"
    ".last-deploy-hash"
)

# Baue lftp Kommandos
LFTP_CMDS="set ftp:ssl-force true;"
LFTP_CMDS="$LFTP_CMDS set ftp:ssl-protect-data true;"
LFTP_CMDS="$LFTP_CMDS set ssl:verify-certificate no;"
LFTP_CMDS="$LFTP_CMDS set ftp:passive-mode true;"
LFTP_CMDS="$LFTP_CMDS set net:timeout 60;"
LFTP_CMDS="$LFTP_CMDS open -p $DEPLOY_PORT -u \"$DEPLOY_USER\",\"$DEPLOY_PASSWORD\" \"$DEPLOY_HOST\";"

echo "📋 Zu löschende Elemente:"
for item in "${TO_DELETE[@]}"; do
    echo -e "   ${RED}✗ $item${NC}"
    LFTP_CMDS="$LFTP_CMDS rm -r -f \"$DEPLOY_PATH/$item\";"
done

LFTP_CMDS="$LFTP_CMDS quit;"

echo ""
echo "🔗 Verbinde zu FTP-Server..."
echo ""

# Führe aus
CLEANUP_EXIT_CODE=0
lftp -c "$LFTP_CMDS" || CLEANUP_EXIT_CODE=$?

# Cleanup: Stelle sicher dass Verbindungen geschlossen sind
pkill -f lftp 2>/dev/null || true
sleep 1

# Check Exit Code
if [ $CLEANUP_EXIT_CODE -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✅ Cleanup erfolgreich!${NC}"
    echo ""
else
    echo ""
    echo -e "${RED}❌ Cleanup fehlgeschlagen!${NC}"
    echo "   (Das ist OK wenn die Dateien nicht existieren)"
    echo ""
fi
