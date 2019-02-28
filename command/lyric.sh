#!/bin/bash

artist_name=$( redis-cli hget lyrics currentartist )
title=$( redis-cli hget lyrics currentsong )

artist=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$artist_name"`
title=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$title"`

echo $artist
echo $title

lyric=$( curl -sf "https://makeitpersonal.co/lyrics?artist=$artist&title=$title" | sed ':a;N;$!ba;s/\n/<\/br>/g' | xargs -0 )

if [[ $lyric == *"We're sorry, but something went wrong."* ]];
then
  echo "No lyrics server available"
elif [ "$lyric" == "" ];
then
  echo "No lyrics server available"
else
  echo $lyric
fi
