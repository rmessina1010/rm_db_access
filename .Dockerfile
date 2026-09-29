FROM phpmyadmin

RUN docker-php-ext-install pdo_mysql

COPY src/ /var/www/html/

EXPOSE 80