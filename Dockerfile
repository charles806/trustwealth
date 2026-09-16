# ---- Runtime stage: PHP CLI ----
FROM php:8.2-cli

# Extensions the app actually uses: PDO/MySQL for the DB,
# curl for fetch_btc_rate()/_http_get_json() in app/helpers.php.
RUN docker-php-ext-install pdo pdo_mysql mysqli

# AWS RDS CA bundle — lets PDO verify the RDS TLS certificate. Point DB_SSL_CA
# at this path (the global bundle covers all regions, including eu-north-1).
RUN apt-get update \
 && apt-get install -y --no-install-recommends ca-certificates curl \
 && curl -fsSL https://truststore.pki.rds.amazonaws.com/global/global-bundle.pem \
      -o /etc/ssl/certs/rds-global-bundle.pem \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .

# No packagist dependencies: composer.json require is empty and the app
# never loads vendor/autoload.php, so we skip the Composer stage entirely.

EXPOSE 8080

# Pxxl injects PORT; default to 8080 like pxxl.toml.
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t ."]