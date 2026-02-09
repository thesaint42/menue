#!/bin/bash
# deploy.sh - Lokales Deployment via FTPS
# Nutzt lftp für FTPS-Verbindung mit Passive Mode
# Begrenzt Verbindungen auf 3 (von 8 verfügbaren) um Limits nicht zu überschreiten

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
    ".git*"
    "*.md"
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

LFTP_CMDS="$LFTP_CMDS bye"

# Führe aus
if lftp -c "$LFTP_CMDS"; then
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
