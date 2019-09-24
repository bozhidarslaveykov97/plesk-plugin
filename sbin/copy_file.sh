#!/bin/bash -e

username=`whoami`
if [ "$username" != "root" ]
then
    echo "Please run this script as root";
    exit 1
fi

if [[ -d $1 ]]; then
	rm -rf $2
    echo "$1 is a directory"
	cp -fr $1 $2
elif [[ -f $1 ]]; then
    echo "$1 is a file"
    rm -rf $2
	cp -f $1 $2
fi