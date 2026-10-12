APP_NAME=Stayflow
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_SUPPORTED_LOCALES=id,en,zh

APP_MAINTENANCE_DRIVER=file

PHP_CLI_SERVER_WORKERS=4

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# --- Database ----------------------------------------------------------------
# Inside the Sail network the MySQL service is reachable as `mysql`.
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=stayflow
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database

MEMCACHED_HOST=memcached

# --- Redis -----------------------------------------------------------------
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# --- Mail ------------------------------------------------------------------
# Mailpit captures all outbound mail; UI at http://localhost:8025.
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@stayflow.local"
MAIL_FROM_NAME="${APP_NAME}"

# --- Object storage ---------------------------------------------------------
# MinIO is the local S3 stand-in; dashboard at http://localhost:9001.
AWS_ACCESS_KEY_ID=stayflow
AWS_SECRET_ACCESS_KEY=stayflow-dev-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=stayflow-local
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_ENDPOINT=http://minio:9000

VITE_APP_NAME="${APP_NAME}"

# --- Mailpit UI -------------------------------------------------------------
MAILPIT_DASHBOARD_URL=http://localhost:8025

# --- MinIO UI -------------------------------------------------------------
MINIO_DASHBOARD_URL=http://localhost:9001