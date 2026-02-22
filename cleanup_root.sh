#!/bin/bash
# cleanup_root.sh - Entfernt alle Schrott-Dateien aus Root des FTP

set -e
source .deploy.env

FILES_TO_DELETE=(
    "11_Menüwahl.code-workspace"
    "composer.json"
    "DEPLOY_LOCAL.md"
    "deploy.sh"
    ".gitignore"
    "dishes.php"
    "job_62610379893_logs.zip"
    "order_system.php"
    "UPGRADE.md"
    "UPGRADE_CHECKLIST.md"
    "roles.php"
    "run_21709861962_logs.zip"
    "users.php"
    "install.php"
)

# Baue lftp Kommandos
LFTP_CMDS="set ftp:ssl-force true;"
LFTP_CMDS="$LFTP_CMDS set ftp:ssl-protect-data true;"
LFTP_CMDS="$LFTP_CMDS set ssl:verify-certificate no;"
LFTP_CMDS="$LFTP_CMDS set ftp:passive-mode true;"
LFTP_CMDS="$LFTP_CMDS open -p $DEPLOY_PORT -u \"$DEPLOY_USER\",\"$DEPLOY_PASSWORD\" \"$DEPLOY_HOST\";"

echo "🧹 Lösche Schrott-Dateien vom FTP..."
for file in "${FILES_TO_DELETE[@]}"; do
    echo "   ✗ $file"
    LFTP_CMDS="$LFTP_CMDS rm -f \"$DEPLOY_PATH/$file\";"
done

LFTP_CMDS="$LFTP_CMDS quit;"

lftp -c "$LFTP_CMDS"

echo ""
echo "✅ Fertig!"
