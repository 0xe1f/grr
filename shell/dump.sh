#! /bin/bash

echo -n Password: >&2
read -s password
echo

mysqldump -u root --password=$password -d grr | sed -E -e 's/ +AUTO_INCREMENT=[0-9]+//g'
mysqldump -u root --password=$password grr -t --tables roles
