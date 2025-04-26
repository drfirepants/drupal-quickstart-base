#!/usr/bin/env bash
set -e

# Wait for the DB to be ready
echo "Checking database connection..."
MAX_RETRIES=30
COUNTER=0

until [ $COUNTER -ge $MAX_RETRIES ]
do
  # Attempt a simple DB connection with PDO
  if php -r "new PDO('mysql:host='.getenv('DRUPAL_DB_HOST').';dbname='.getenv('DRUPAL_DB_NAME'), getenv('DRUPAL_DB_USER'), getenv('DRUPAL_DB_PASSWORD'));" 2>/dev/null; then
    echo "Database is ready!"
    break
  fi
  COUNTER=$((COUNTER+1))
  echo "Database is not ready. Waiting... Attempt $COUNTER of $MAX_RETRIES"
  sleep 3
done

if [ $COUNTER -ge $MAX_RETRIES ]; then
  echo "Could not connect to database after $MAX_RETRIES attempts. Exiting."
  exit 1
fi

# Check if web directory is empty (first run)
if [ ! -d "/var/www/html/web/core" ]; then
  echo "Initializing web directory with files from the build..."
  # Copy the built files from the temporary location
  cp -r /tmp/web/* /var/www/html/web/
  # Create necessary directories if they don't exist
  mkdir -p /var/www/html/web/sites/default/files
  mkdir -p /var/www/html/web/modules/custom
  mkdir -p /var/www/html/web/themes/custom
fi

# Ensure correct permissions
chown -R www-data:www-data /var/www/html/web/sites/default/files

# Check if Drupal is installed
DRUPAL_INSTALLED=$(php -r "include_once '/var/www/html/web/sites/default/settings.php'; echo isset(\$databases['default']['default']) ? 'yes' : 'no';")

if [ ! -f "/var/www/html/web/sites/default/settings.php" ] || [ "$DRUPAL_INSTALLED" = "no" ]; then
  echo "No existing Drupal site found. Installing..."

  # Make sites/default writable for installation
  mkdir -p /var/www/html/web/sites/default/files
  chmod -R 777 /var/www/html/web/sites/default

  # Install Drupal with Drush
  su - www-data -s /bin/bash -c "cd /var/www/html && \
    drush site:install standard \
    --db-url=mysql://${DRUPAL_DB_USER}:${DRUPAL_DB_PASSWORD}@${DRUPAL_DB_HOST}/${DRUPAL_DB_NAME} \
    --site-name='My Drupal Docker Site' \
    --account-name=admin \
    --account-pass=admin \
    --yes"

  # Install some common modules that we might want enabled by default
  su - www-data -s /bin/bash -c "cd /var/www/html && \
    drush en -y admin_toolbar admin_toolbar_tools pathauto metatag redirect jsonapi_extras"

  echo "Drupal site installed!"

  # Set proper permissions after install
  chmod 755 /var/www/html/web/sites/default
  chmod 644 /var/www/html/web/sites/default/settings.php
  chmod -R 775 /var/www/html/web/sites/default/files

else
  echo "Existing Drupal site detected. Running updates..."
  su - www-data -s /bin/bash -c "cd /var/www/html && drush updb -y"
  su - www-data -s /bin/bash -c "cd /var/www/html && drush cr"

  # Check if config_sync_directory is defined
  CONFIG_DIR=$(php -r "include_once '/var/www/html/web/sites/default/settings.php'; echo !empty(\$config_directories['sync']) ? \$config_directories['sync'] : (!empty(\$settings['config_sync_directory']) ? \$settings['config_sync_directory'] : '');")

  if [ -n "$CONFIG_DIR" ] && [ -d "/var/www/html/$CONFIG_DIR" ]; then
    echo "Config directory found at $CONFIG_DIR. Importing configuration..."
    su - www-data -s /bin/bash -c "cd /var/www/html && drush cim -y"
  else
    echo "No config directory found or defined. Skipping config import."
  fi
fi

# Finally, run the main CMD (Apache) passed from the Dockerfile
exec "$@"