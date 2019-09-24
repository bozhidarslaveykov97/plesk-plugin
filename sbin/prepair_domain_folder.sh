#!/bin/bash -e

username=`whoami`
if [ "$username" != "root" ]; then
    echo "Please run this script as root";
    exit 1
fi

if [[ -f "$1/index.html" ]]; then
    echo "$1 is a file"
    unlink "$1/index.html"
fi