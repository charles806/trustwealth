# ---- Composer stage: install PHP dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install \
      --no-dev \
      --no-interaction \
      --no-progress \
      --optimize-autoloader \
      --no-scripts \
    || true

# ---- Runtime stage: PHP CLI ----
FROM php:8.2-cli

# Install common extensions. Trim to what you actually need.
RUN docker-php-ext-install pdo pdo_mysql mysqli

WORKDIR /app

COPY . .

COPY --from=vendor /app/vendor ./vendor

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]