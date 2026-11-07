#!/bin/sh
# Runs only when the MySQL volume is created for the first time.
# Creates the database used by the PHPUnit suite (phpunit.xml) so tests never
# touch the development database.
set -e

mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}_testing\`;
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}_testing\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
