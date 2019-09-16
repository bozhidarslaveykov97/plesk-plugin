#!/bin/bash -e

username=`whoami`
if [ "$username" != "root" ]; then
    echo "Please run this script as root";
    exit 1
fi

if [[ -f /opt/plesk/php/7.1/bin/php ]]; then
	/opt/plesk/php/7.1/bin/php $1
elif [[ -f /opt/plesk/php/7.2/bin/php ]]; then
	/opt/plesk/php/7.2/bin/php $1
elif [[ -f /opt/plesk/php/7.3/bin/php ]]; then
	/opt/plesk/php/7.3/bin/php $1
elif [[ -f /opt/plesk/php/5.6/bin/php ]]; then
	/opt/plesk/php/5.6/bin/php $1
fi