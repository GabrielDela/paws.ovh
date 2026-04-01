FROM php:8.3-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      zlib1g-dev libjpeg-dev libpng-dev libwebp-dev \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j$(nproc) gd

WORKDIR /var/www/html
COPY . .

# Ensure upload & thumbnail directories exist and are writable by the PHP process
RUN mkdir -p public/thumbnail public/awaiting/thumbnail \
 && chmod -R 777 public

EXPOSE 8080
CMD ["php", "-d", "memory_limit=512M", "-d", "upload_max_filesize=512M", "-d", "post_max_size=1024M", "-d", "max_file_uploads=1000", "-S", "0.0.0.0:8080", "-t", "."]