FROM php:8.5-cli

# Install zip for Composer
RUN apt-get update && apt-get install -y libzip-dev zip && docker-php-ext-install zip

RUN docker-php-ext-install pcntl

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /usr/src/app

# Copy mapping and code
COPY composer.json ./

COPY ./src ./src
COPY ./index.php ./
COPY ./indexAce.php ./
COPY ./indexFeed.php ./

RUN groupadd --gid 1000 phpgroup && \
    useradd --uid 1000 -g phpgroup -s /bin/bash -m phpuser

RUN mkdir -p /usr/src/app/sessions && \
    composer dump-autoload --optimize && \
    chown -R phpuser:phpgroup /usr/src/app

# Switch to the non-root user
USER phpuser

# Generate the autoloader
RUN composer install --optimize-autoloader

CMD []