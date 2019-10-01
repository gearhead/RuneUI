#!/bin/bash

artist_name=$( redis-cli hget lyrics currentartist )
if [ -z "$artist_name" ];
then
  artist_name=`mpc -f %name% | head -n 1`
fi

artist=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$artist_name"`

echo $artist

artistinfo=$( curl -s -f --connect-timeout 1 -m 10 --retry 2 "http://ws.audioscrobbler.com/2.0/?method=artist.getinfo&autocorrect=1&artist=$artist&api_key=ba8ad00468a50732a3860832eaed0882&format=json" | sed ':a;N;$!ba;s/\n/<\/br>/g' | xargs -0 )

if [[ $artistinfo == *"png"* ]];
then
  echo $artistinfo
else
  colon=":"
  spacecolon=" :"
  artist_name=${artist_name//$spacecolon/$colon}
  spaceampersand=" &"
  artist_name=${artist_name//$spaceampersand/$colon}
  ampersand="&"
  artist_name=${artist_name//$ampersand/$colon}
  spacedash=" -"
  artist_name=${artist_name//$spacedash/$colon}
  dash="-"
  artist_name=${artist_name//$dash/$colon}
  case $artist_name in
     *:*)
          artist_name=$( echo "$artist_name" | cut -d ":" -f 1 )
          artist=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$artist_name"`
          curl -s -f --connect-timeout 1 -m 10 --retry 2 "http://ws.audioscrobbler.com/2.0/?method=artist.getinfo&autocorrect=1&artist=$artist&api_key=ba8ad00468a50732a3860832eaed0882&format=json" | sed ':a;N;$!ba;s/\n/<\/br>/g' | xargs -0
          ;;
     *)
          echo $artistinfo
          ;;
  esac
fi
