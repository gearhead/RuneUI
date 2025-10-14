#!/bin/bash

# stop services which ran under the http user
systemctl stop weston.service
systemctl stop amixer-webui.service
systemctl stop local-browser-w.service

# add the www-data user if it exists, first remove it
usercnt=$( grep -c "^www-data:" "/etc/passwd" )
if [ "$usercnt" != "0" ] ; then
    userdel -r "www-data"
fi
# recreate the www-data user with no password, locked, default directory /srv/http and pointing to the shell /usr/bin/nologin
useradd -U -c "www-data webserver user" -d /srv/http -s /usr/bin/nologin "www-data"
# and just to be safe modify the www-data account
usermod -c "www-data webserver user" -d /srv/http -s /usr/bin/nologin "www-data"

# remove the http user if it exists, http was previously the webserver user, superseded by www-data
usercnt=$( grep -c "^http:" "/etc/passwd" )
if [ "$usercnt" != "0" ] ; then
    userdel "http"
fi

# make sure that Audio-specific users are member of the audio group
declare -a audiousers=(www-data mpd spotifyd shairport-sync upmpdcli bluealsa mpdscribble owntone)
for i in "${audiousers[@]}" ; do
    audiocnt=$( groups $i | grep -c audio )
    if [ "$audiocnt" == "0" ] ; then
        usermod -a -G audio $i
    fi
done

# fix lines containing 'user=http' with 'user=www-data', retaining spaces and uppercase/lowercase in /srv/*
grep -rl --exclude=http2www-data.sh 'user\s*=\s*http\s*$' /srv | xargs -d '\n' sed -i '/user\s*=\s*http\s*$/s/http/www-data/'
grep -rl --exclude=http2www-data.sh 'User\s*=\s*http\s*$' /srv | xargs -d '\n' sed -i '/User\s*=\s*http\s*$/s/http/www-data/'
# fix lines containing 'group=http' with 'group=www-data', retaining spaces and uppercase/lowercase in /srv/*
grep -rl --exclude=http2www-data.sh 'group\s*=\s*http\s*$' /srv | xargs -d '\n' sed -i '/group\s*=\s*http\s*$/s/http/www-data/'
grep -rl --exclude=http2www-data.sh 'Group\s*=\s*http\s*$' /srv | xargs -d '\n' sed -i '/Group\s*=\s*http\s*$/s/http/www-data/'
# fix lines containing 'listen.owner=http' with 'listen.owner=www-data', retaining spaces and uppercase/lowercase in /srv/*
grep -rl --exclude=http2www-data.sh 'listen\.owner\s*=\s*http\s*$' /srv | xargs -d '\n' sed -i '/listen\.owner\s*=\s*http\s*$/s/http/www-data/'
grep -rl --exclude=http2www-data.sh 'Listen.owner\s*=\s*http\s*$' /srv | xargs -d '\n' sed -i '/Listen.owner\s*=\s*http\s*$/s/http/www-data/'
# fix lines containing 'listen.group=http' with 'listen.group=www-data', retaining spaces and uppercase/lowercase in /srv/*
grep -rl --exclude=http2www-data.sh 'listen\.group\s*=\s*http\s*$' /srv | xargs -d '\n' sed -i '/listen\.group\s*=\s*http\s*$/s/http/www-data/'
grep -rl --exclude=http2www-data.sh 'Listen\.group\s*=\s*http\s*$' /srv | xargs -d '\n' sed -i '/Listen\.group\s*=\s*http\s*$/s/http/www-data/'
# fix lines containing 'user http' with 'user www-data', retaining spaces and uppercase/lowercase in /srv/*
grep -rl --exclude=http2www-data.sh 'user\s*http' /srv | xargs -d '\n' sed -i '/user\s*http/s/http/www-data/'
grep -rl --exclude=http2www-data.sh 'User\s*http' /srv | xargs -d '\n' sed -i '/User\s*http/s/http/www-data/'
# fix lines containing 'group http' with 'group www-data', retaining spaces and uppercase/lowercase in /srv/*
grep -rl --exclude=http2www-data.sh 'group\s*http' /srv | xargs -d '\n' sed -i '/group\s*http/s/http/www-data/'
grep -rl --exclude=http2www-data.sh 'Group\s*http' /srv | xargs -d '\n' sed -i '/Group\s*http/s/http/www-data/'

# fix lines containing 'user=http' with 'user=www-data', retaining spaces and uppercase/lowercase in /etc/*
grep -rl 'user\s*=\s*http\s*$' /etc | xargs -d '\n' sed -i '/user\s*=\s*http\s*$/s/http/www-data/'
grep -rl 'User\s*=\s*http\s*$' /etc | xargs -d '\n' sed -i '/User\s*=\s*http\s*$/s/http/www-data/'
# fix lines containing 'group=http' with 'group=www-data', retaining spaces and uppercase/lowercase in /etc/*
grep -rl 'group\s*=\s*http\s*$' /etc | xargs -d '\n' sed -i '/group\s*=\s*http\s*$/s/http/www-data/'
grep -rl 'Group\s*=\s*http\s*$' /etc | xargs -d '\n' sed -i '/Group\s*=\s*http\s*$/s/http/www-data/'
# fix lines containing 'listen.owner=http' with 'listen.owner=www-data', retaining spaces and uppercase/lowercase in /srv/*
grep -rl 'listen\.owner\s*=\s*http\s*$' /etc | xargs -d '\n' sed -i '/listen\.owner\s*=\s*http\s*$/s/http/www-data/'
grep -rl 'Listen.owner\s*=\s*http\s*$' /etc | xargs -d '\n' sed -i '/Listen.owner\s*=\s*http\s*$/s/http/www-data/'
# fix lines containing 'listen.group=http' with 'listen.group=www-data', retaining spaces and uppercase/lowercase in /srv/*
grep -rl 'listen\.group\s*=\s*http\s*$' /etc | xargs -d '\n' sed -i '/listen\.group\s*=\s*http\s*$/s/http/www-data/'
grep -rl 'Listen\.group\s*=\s*http\s*$' /etc | xargs -d '\n' sed -i '/Listen\.group\s*=\s*http\s*$/s/http/www-data/'
# fix lines containing 'user http' with 'user www-data', retaining spaces and uppercase/lowercase in /etc/*
grep -rl 'user\s*http' /etc | xargs -d '\n' sed -i '/user\s*http/s/http/www-data/'
grep -rl 'User\s*http' /etc | xargs -d '\n' sed -i '/User\s*http/s/http/www-data/'
# fix lines containing 'group http' with 'group www-data', retaining spaces and uppercase/lowercase in /etc/*
grep -rl 'group\s*http' /etc | xargs -d '\n' sed -i '/group\s*http/s/http/www-data/'
grep -rl 'Group\s*http' /etc | xargs -d '\n' sed -i '/Group\s*http/s/http/www-data/'

# fix file protections and change ownerships
find /srv/http/command/ -type f \! -perm 755 -exec chmod 755 {} \;
/srv/http/command/convert_dos_files_to_unix_script.sh
