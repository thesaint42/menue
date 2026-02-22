#!/bin/bash
# deploy.sh - Lokales Deployment via FTPS
# Nutzt lftp für FTPS-Verbindung mit Passive Mode
# 
# Verwendung:
#   ./deploy.sh              - Deploy nur geänderte Dateien
#   ./deploy.sh --cleanup    - Lösche Development-Dateien vom Server
#   ./deploy.sh file1 file2  - Deploy spezifische Dateien

set -e  # Exit on error

# Lade Konfiguration
if [ ! -f .deploy.env ]; then
    echo "❌ .deploy.env nicht gefunden!"
    echo "Bitte .deploy.env erstellen mit FTP-Credentials."
    exit 1
fi

source .deploy.env

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Cleanup-Modus?
if [ "$1" = "--cleanup" ]; then
    echo -e "${BLUE}═══════════════════════════════════════════${NC}"
    echo -e "${BLUE}🧹 EMOS FTP Cleanup${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════${NC}"
    echo ""
    
    # Ordner/Dateien die gelöscht werden sollen
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
    
    exit 0
fi

echo -e "${BLUE}═══════════════════════════════════════════${NC}"
echo -e "${BLUE}🚀 EMOS Deployment via FTPS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════${NC}"
echo ""
echo "📊 Deployment-Konfiguration:"
echo "   Server: $DEPLOY_HOST"
echo "   User: $DEPLOY_USER"
echo "   Root: $DEPLOY_PATH"
echo "   Protokoll: $DEPLOY_PROTOCOL (Passive: $DEPLOY_PASSIVE)"
echo "   Max Connections: $MAX_CONNECTIONS/8"
echo ""

# Bestimme Dateien zum Deployen
if [ $# -eq 0 ]; then
    echo "📁 Modus: Nur geänderte Dateien seit letztem Deploy"
    
    # Lese letzten Deploy-Hash wenn vorhanden
    LAST_DEPLOY_HASH=""
    if [ -f ".last-deploy-hash" ]; then
        LAST_DEPLOY_HASH=$(cat .last-deploy-hash)
    fi
    
    # Wenn wir einen letzten Hash haben, deploye nur Änderungen seit damals
    if [ -z "$LAST_DEPLOY_HASH" ]; then
        # Kein Hash: Deploye alles seit HEAD~1
        FILES=$(git diff --name-only HEAD~1..HEAD 2>/dev/null || echo "")
        if [ -z "$FILES" ]; then
            # Falls nur 1 Commit: Deploye alles seit letzten Push
            FILES=$(git diff --name-only @{u}..HEAD 2>/dev/null || git diff --name-only)
        fi
    else
        # Deploye nur seit dem letzten Deploy
        FILES=$(git diff --name-only $LAST_DEPLOY_HASH..HEAD 2>/dev/null || echo "")
    fi
    
    # Auch neue (untracked) Dateien
    UNTRACKED=$(git ls-files --others --exclude-standard 2>/dev/null)
    if [ ! -z "$UNTRACKED" ]; then
        FILES="$FILES"$'\n'"$UNTRACKED"
    fi
else
    echo "📁 Modus: Spezifische Dateien"
    FILES="$@"
fi

# Filtere geschützte Dateien
EXCLUDED_FILES=(
    "db.php"
    "install.php"
    "script/config.yaml"
    "storage/*"
    ".deploy.env"
    "deploy.sh"
    ".git"
    ".github"
    ".git*"
    "*.md"
    "composer.json"
    "composer.lock"
)

echo ""
echo "📋 Dateien zum Deployen:"
echo ""

DEPLOY_FILES=""
SKIPPED_COUNT=0

for file in $FILES; do
    # Skip empty
    if [ -z "$file" ]; then
        continue
    fi
    
    # Skip wenn nicht existent
    if [ ! -e "$file" ]; then
        continue
    fi
    
    # Check ob geschützt
    SKIP=0
    for pattern in "${EXCLUDED_FILES[@]}"; do
        if [[ "$file" == "$pattern" ]] || [[ "$file" =~ ^${pattern}$ ]]; then
            echo -e "   ${YELLOW}⊘ $file${NC} (geschützt)"
            ((SKIPPED_COUNT++))
            SKIP=1
            break
        fi
    done
    
    if [ $SKIP -eq 0 ]; then
        echo -e "   ${GREEN}✓ $file${NC}"
        DEPLOY_FILES="$DEPLOY_FILES $file"
    fi
done

if [ -z "$DEPLOY_FILES" ]; then
    echo ""
    echo -e "${YELLOW}⚠️  Keine Dateien zum Deployen!${NC}"
    exit 0
fi

echo ""
echo -e "${BLUE}Connecting to FTPS...${NC}"
echo ""

# Baue lftp Kommandos
LFTP_CMDS="set ftp:ssl-force true;"
LFTP_CMDS="$LFTP_CMDS set ftp:ssl-protect-data true;"
LFTP_CMDS="$LFTP_CMDS set ssl:verify-certificate no;"
LFTP_CMDS="$LFTP_CMDS set ftp:passive-mode true;"
LFTP_CMDS="$LFTP_CMDS set net:timeout 60;"
LFTP_CMDS="$LFTP_CMDS set net:max-retries 10;"
LFTP_CMDS="$LFTP_CMDS set net:reconnect-interval-base 15;"
LFTP_CMDS="$LFTP_CMDS set mirror:parallel-transfer-count 1;"
LFTP_CMDS="$LFTP_CMDS set net:connection-limit 1;"
LFTP_CMDS="$LFTP_CMDS open -p $DEPLOY_PORT -u \"$DEPLOY_USER\",\"$DEPLOY_PASSWORD\" \"$DEPLOY_HOST\";"

# Für jede Datei: mkdir + upload
for file in $DEPLOY_FILES; do
    dir=$(dirname "$file")
    
    if [ "$dir" = "." ]; then
        dir=""
    fi
    
    if [ -d "$file" ]; then
        # Directory
        LFTP_CMDS="$LFTP_CMDS mkdir -p \"$DEPLOY_PATH/$file\";"
    else
        # File
        if [ ! -z "$dir" ]; then
            LFTP_CMDS="$LFTP_CMDS mkdir -p \"$DEPLOY_PATH/$dir\";"
        fi
        LFTP_CMDS="$LFTP_CMDS put -O \"$DEPLOY_PATH/$dir\" \"$file\";"
    fi
done

LFTP_CMDS="$LFTP_CMDS quit;"

# Führe aus
DEPLOY_EXIT_CODE=0
lftp -c "$LFTP_CMDS" || DEPLOY_EXIT_CODE=$?

# Cleanup: Stelle sicher dass Verbindungen geschlossen sind
echo "🧹 Cleanup FTP-Verbindungen..."
# Beende alle lftp-Prozesse falls noch vorhanden
pkill -f lftp 2>/dev/null || true
sleep 1

# Check Exit Code
if [ $DEPLOY_EXIT_CODE -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✅ Deployment erfolgreich!${NC}"
    echo ""
    DEPLOYED_COUNT=$(echo "$DEPLOY_FILES" | wc -w)
    echo "📊 Statistik:"
    echo "   Deployed: $DEPLOYED_COUNT Dateien"
    echo "   Übersprungen: $SKIPPED_COUNT Dateien"
    echo ""
    
    # Speichere Deploy-Hash für nächstes Mal
    CURRENT_HASH=$(git rev-parse HEAD)
    echo "$CURRENT_HASH" > .last-deploy-hash
    echo "💾 Deploy-Hash gespeichert für nächstesmal"
    echo ""
else
    echo ""
    echo -e "${RED}❌ Deployment fehlgeschlagen!${NC}"
    exit 1
fi
