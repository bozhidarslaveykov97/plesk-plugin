#!/bin/bash

echo $1

exit

if [ -d "$1" ]; then
	echo 'Rsync' "$1" 'to' "$2"
	rsync -a $1 $2
fi