#!/usr/bin/bash
# bt-sink.sh
# Runeaudio
# shell script to deal with bluetooth sources and sinks
# this is called by the bluetooth rule in 99-runeaudio.rules
case $1 in
"start")
   #mpc stop
   type=$(/usr/bin/bluealsa-aplay -L | /usr/bin/grep trusted | /usr/bin/cut -d ',' -f 3 | /usr/bin/tr -d -d " \t\n\r")
   case $type in
   "playback") # attached speaker
   /usr/bin/mpc stop
   ;;
   "capture") # attached a source
   /usr/bin/mpc stop
   ;;
   esac
;;
"stop")
   /usr/bin/mpc play
;;
esac
