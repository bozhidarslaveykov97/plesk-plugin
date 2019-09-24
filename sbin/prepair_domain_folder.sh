#!/bin/bash -e

username=`whoami`
if [ "$username" != "root" ]; then
    echo "Please run this script as root";
    exit 1
fi

customtime=`date +"%Y-%m-%d-%H-%M-%S"`

if [[ -f "$1/index.html" ]]; then
    echo "$1 is a file"
    mv "$1/index.html" "$1/index.html.$customtime"
fi

exit