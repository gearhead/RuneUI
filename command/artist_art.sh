#!/bin/bash
# if fanart.tv is not available just echo a default image
fanarttv=$( redis-cli hget service fanarttv )
if [ "$fanarttv" == 1 ];
then
  # fanart.tv active
  quote='"'
  fanarttvapikey=$( redis-cli get fanarttv_apikey )
  artistmbid=$( redis-cli hget lyrics artistmbid )
  if [ -z "$artistmbid" ] || [ "$artistmbid" = "null" ];
  then
    # no artist MusicBrainz-ID (mbid), so return default rune image
    echo '{"artistarturl": "localhost/assets/img/cover-default.png"}'
  else
    artistmbid=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$artistmbid"`
    artistinfo=$( curl -s -f --connect-timeout 1 -m 10 --retry 2 "https://webservice.fanart.tv/v3/music/$artistmbid/&?api_key=$fanarttvapikey" | xargs -0 )
    # try the favourite artist image in the returned information
    artistarturl=$( echo $artistinfo | jq -r '.artistbackground[0].url' )
    if [ -z "$artistarturl" ] || [ "$artistarturl" = "null" ];
    then
      # artist-art empty try the artist thumb image in the returned information
      artistarturl=$( echo $artistinfo | jq -r '.artistthumb[0].url' )
    fi
    if [ -z "$artistarturl" ] || [ "$artistarturl" = "null" ];
    then
      # artist-art url empty, so return default rune image
      echo '{"artistarturl": "localhost/assets/img/cover-default.png"}'
    else
      # artist-art url FOUND, so use it
	  artistarturl="$quote$artistarturl$quote"
      echo {"artistarturl": "$artistarturl"}
    fi
  fi
else
  # fanart.tv NOT active, so return default rune image
  echo '{"artistarturl": "localhost/assets/img/cover-default.png"}'
fi
