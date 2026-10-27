#!/bin/bash
set -e
echo "🚀 Deploying gcm.enterprises to VPS..."
cd /var/www/gcm.enterprises

# 1. Safeguard live dynamic data files
mkdir -p /var/www/gcm.enterprises/admin/data/backups/auto_deploy_safeguard
cp /var/www/gcm.enterprises/admin/data/*.json /var/www/gcm.enterprises/admin/data/backups/auto_deploy_safeguard/ 2>/dev/null || true

# 2. Fetch origin and sync code cleanly
git fetch origin main
git checkout -f main
git reset --hard origin/main

# 3. Ensure live JSON databases remain intact and permissions are correct
chown -R www-data:www-data /var/www/gcm.enterprises
chmod -R 755 /var/www/gcm.enterprises/admin/data
chmod -R 755 /var/www/gcm.enterprises/admin/uploads

# 4. Reload PHP and Nginx
systemctl reload nginx 2>/dev/null || true

echo "✅ gcm.enterprises successfully deployed and synced!"
