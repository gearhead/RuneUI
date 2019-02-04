#!/bin/bash

artist_name=$( redis-cli hget lyrics currentartist )
title=$( redis-cli hget lyrics currentsong )

artist=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$artist_name"`
title=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$title"`

echo $artist
echo $title

curl -sf "https://makeitpersonal.co/lyrics?artist=$artist&title=$title" |sed ':a;N;$!ba;s/\n/<\/br>/g'
