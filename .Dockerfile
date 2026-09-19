FROM phpmyadmin

RUN docker-php-ext-install pdo_mysql

COPY . .

EXPOSE 80