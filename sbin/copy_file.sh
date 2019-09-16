#!/bin/bash -e

username=`whoami`
if [ "$username" != "root" ]
then
    echo "Please run this script as root";
    exit 1
fi

if [[ -d $1 ]]; then
    echo "$1 is a directory"
    cp -r $1 $2
elif [[ -f $1 ]]; then
    echo "$1 is a file"
    cp -r $1 $2
fi