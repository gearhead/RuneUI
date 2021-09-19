#!/bin/bash

# variables
webradiodir="/mnt/MPD/Webradio"

# if the directory /boot/webradios contains *.pls files copy them to the webradio directory and delete them
find /boot/webradios -type f -name *.pls -exec cp -fn -- '{}' $webradiodir/ \;
find /boot/webradios -type f -name *.pls -delete

# create webradio files when they are defined in redis but the file does not exist
redis-cli hkeys webradios > /tmp/radios
while IFS= read -r webradio; do
    if [ ! -f "$webradiodir/$webradio.pls" ]; then
        # file is not there create it
        url=$( redis-cli hget webradios "$webradio" )
        echo -e "[playlist]\nNumberOfEntries=1\nFile1=$url\nTitle1=$webradio\n" > "$webradiodir/$webradio.pls"
    fi
done < "/tmp/radios"
rm /tmp/radios

# if sub directories
if ls -d $webradiodir/*/ &> /dev/null; then
    # -mindepth 2 = in sub directories && -type f = file
    find $webradiodir -mindepth 2 -type f -name *.pls -exec cp -f -- '{}' $webradiodir/ \;
    # * = all sub directory && .[^.] = not ..
    rm -rf $webradiodir/{*,.[^.]}/
fi

if ! ls $webradiodir/*.pls &> /dev/null; then
    exit
fi

# clear database
redis-cli del webradios &> /dev/null

# add data from files
for file in $webradiodir/*.pls; do
    name=$( basename "$file" )
    name=${name%.*}
    url=$( grep -h 'File1' "$file" | cut -d '=' -f2 )
    if [ "$name" != "" ] && [ "$url" != "" ]; then
        redis-cli hset webradios "$name" "$url" &> /dev/null
        echo "Added - Name: $name, URL: $url"
    else
        echo "Invalid content in file: $webradiodir/$name.pls"
    fi
done

# refresh list
mpc update Webradio &> /dev/null

