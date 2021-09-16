#!/bin/bash

echo "You have selected ${DB_NAME} database."
echo "You have selected ${SQL_FILE} file."


/usr/bin/mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} < /sql/${SQL_FILE}