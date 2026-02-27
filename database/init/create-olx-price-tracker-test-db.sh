#!/usr/bin/env bash

mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS olx_price_tracker_test;
    GRANT ALL PRIVILEGES ON \`olx_price_tracker_test\`.* TO '$MYSQL_USER'@'%';
    FLUSH PRIVILEGES;
EOSQL
