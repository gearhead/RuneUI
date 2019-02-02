#!/bin/bash
#
# internet_available_async
# ------------------------
# Test to see if an internet connection is available. Disable the artistinfo & lyrics functions when no internet is available
# This will resolve UI freezing problems
#
# see if we can see google.com, this command will give up after +/-20 seconds (= timeout x retries)
wget --force-html --spider --timeout=10 --tries=2 https://www.google.com/ > /dev/null 2>&1
if [ $? -eq 0 ]; then
#	internet connection is available, so make the artist info & lyric files executable
	chown http.http /srv/http/command/lyric.sh
	chown http.http /srv/http/command/artist_info.sh
	chmod 755 /srv/http/command/lyric.sh
	chmod 755 /srv/http/command/artist_info.sh
else
#	internet connection not available, so make the artist info & lyric files non-executable
	chown root.root /srv/http/command/lyric.sh
	chown root.root /srv/http/command/artist_info.sh
	chmod 600 /srv/http/command/lyric.sh
	chmod 600 /srv/http/command/artist_info.sh
fi
#---
#End script
