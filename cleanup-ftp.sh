#!/bin/bash
# cleanup-ftp.sh - Trennt offene FTP-Verbindungen

source .deploy.env

echo "🧹 Räume alte FTP-Verbindungen auf..."
echo ""

# Nutze lftp um zu connecten und sofort zu disconnecten
# Dies sollte Idle-Verbindungen auf dem Server beenden
for i in {1..8}; do
    (timeout 5 lftp -c "
        set ftp:ssl-force true;
        set ftp:ssl-protect-data true;
        set ssl:verify-certificate no;
        set ftp:passive-mode true;
        set net:timeout 3;
        open -p $DEPLOY_PORT -u \"$DEPLOY_USER\",\"$DEPLOY_PASSWORD\" \"$DEPLOY_HOST\";
        quit;
    " 2>/dev/null &)
done

sleep 2
wait

echo "✅ Cleanup abgeschlossen"
echo ""
echo "💡 Falls Problem bleibt: Warte 5 Minuten bis Server automatisch disconnectet"
