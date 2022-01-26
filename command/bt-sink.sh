#!/usr/bin/bash
# bt-sink.sh
# Runeaudio
# shell script to switch off mpd so that a connected phone or otehr player
# will play through alsa system to the default output if set
# this is called by the bluetooth source rules in 99-runeaudio.rules
case $1 in
"start")
   mpc stop ;;
"stop")
   mpc play ;;
esac
