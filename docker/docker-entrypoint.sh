#!/usr/bin/env bash
# Removed set -e intentionally - we handle errors manually

echo "Checking database connection..."
MAX_RETRIES=30
COUNTER=0

until [ $COUNTER -ge $MAX_RETRIES ]; do
  if php -r "new PDO('mysql:host='.getenv('DRUPAL_DB_HOST').';dbname='.getenv('DRUPAL_DB_NAME'), getenv('DRUPAL_DB_USER'), getenv('DRUPAL_DB_PASSWORD'));" 2>/dev/null; then
    echo "Database is ready!"
    break
  fi
  COUNTER=$((COUNTER+1))
  echo "Waiting for database... Attempt $COUNTER of $MAX_RETRIES"
  sleep 3
done

if [ $COUNTER -ge $MAX_RETRIES ]; then
  echo "Could not connect to database. Exiting."
  exit 1
fi

if [ ! -d "/var/www/html/web/core" ]; then
  echo "Initializing web directory..."
  cp -r /tmp/web/* /var/www/html/web/
  mkdir -p /var/www/html/web/sites/default/files
  mkdir -p /var/www/html/web/modules/custom
  mkdir -p /var/www/html/web/themes/custom
fi

chown -R www-data:www-data /var/www/html/web/sites/default/files

# Check if Drupal is actually installed by querying the database for core tables
DRUPAL_INSTALLED=$(php -r "
  try {
    \$pdo = new PDO('mysql:host='.getenv('DRUPAL_DB_HOST').';dbname='.getenv('DRUPAL_DB_NAME'), getenv('DRUPAL_DB_USER'), getenv('DRUPAL_DB_PASSWORD'));
    \$result = \$pdo->query(\"SHOW TABLES LIKE 'users'\");
    echo \$result && \$result->rowCount() > 0 ? 'yes' : 'no';
  } catch (Exception \$e) {
    echo 'no';
  }
")

echo "Drupal installed in DB: $DRUPAL_INSTALLED"

if [ "$DRUPAL_INSTALLED" = "no" ]; then
  echo "Fresh database detected. Installing Drupal..."

  mkdir -p /var/www/html/web/sites/default/files
  chmod -R 777 /var/www/html/web/sites/default

  su - www-data -s /bin/bash -c "cd /var/www/html && \
    drush site:install standard \
    --db-url=mysql://${DRUPAL_DB_USER}:${DRUPAL_DB_PASSWORD}@${DRUPAL_DB_HOST}/${DRUPAL_DB_NAME} \
    --site-name=\"${DRUPAL_SITE_NAME:-My Drupal Site}\" \
    --account-name=\"${DRUPAL_ACCOUNT_NAME:-admin}\" \
    --account-pass=\"${DRUPAL_ACCOUNT_PASS:-admin}\" \
    --yes" || echo "WARNING: Drupal install failed, continuing anyway"

  su - www-data -s /bin/bash -c "cd /var/www/html && \
    drush en -y admin_toolbar admin_toolbar_tools pathauto metatag redirect jsonapi_extras" || echo "WARNING: Module enable failed, continuing anyway"

  chmod 755 /var/www/html/web/sites/default
  chmod 644 /var/www/html/web/sites/default/settings.php
  chmod -R 775 /var/www/html/web/sites/default/files

else
  echo "Existing Drupal installation detected. Running updates..."
  su - www-data -s /bin/bash -c "cd /var/www/html && drush updb -y" || echo "WARNING: updb failed, continuing anyway"
  su - www-data -s /bin/bash -c "cd /var/www/html && drush cr" || echo "WARNING: cache rebuild failed, continuing anyway"

  CONFIG_DIR=$(php -r "include_once '/var/www/html/web/sites/default/settings.php'; echo !empty(\$settings['config_sync_directory']) ? \$settings['config_sync_directory'] : '';")
  if [ -n "$CONFIG_DIR" ] && [ -d "/var/www/html/$CONFIG_DIR" ]; then
    su - www-data -s /bin/bash -c "cd /var/www/html && drush cim -y" || echo "WARNING: config import failed, continuing anyway"
  fi
fi

exec "$@"
