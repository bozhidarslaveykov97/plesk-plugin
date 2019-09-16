#!/bin/bash

downloadUrl=$(echo "$1" | base64 -d)

downloadCacheFolder='/usr/share/'$2'-download-cache'

if [ ! -d "$downloadCacheFolder" ]; then
	mkdir "$downloadCacheFolder"
fi

cd "$downloadCacheFolder" || exit

zipDownloadedFile=$2'-app-cache.zip';

echo 'Download from url...'
wget "$downloadUrl" -O "$zipDownloadedFile"

# Unzip selected version
echo 'Unzip file...'
unzip "$zipDownloadedFile" -d latest > unziping.log

if [ ! -d '/usr/share/'"$2" ]; then
	echo 'Make dir /usr/share/'"$2"
	mkdir '/usr/share/'"$2"
fi

echo 'Delete files from /usr/share/'"$2"'/latest'
rm -rf '/usr/share/'"$2"'/latest'

echo 'Move file to /usr/share/'"$2"
mv latest /usr/share/"$2"

chmod 755 -R /usr/share/"$2"/latest

echo "Done!"
