FROM php:8.3-fpm

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN pecl install redis && docker-php-ext-enable redis
RUN apt-get update && apt-get install -y cron

WORKDIR /var/cmd

COPY src/ /var/cmd
COPY crontab /etc/cron.d/cron-job

RUN chmod 0644 /etc/cron.d/cron-job && crontab /etc/cron.d/cron-job

CMD cron -f