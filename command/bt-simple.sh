#!/usr/bin/bash
# bt-sink.sh
# Runeaudio
# shell script to switch off mpd so that a connected phone or otehr player
# will play through alsa system to the default output if set
# this is called by the bluetooth source rules in 99-runeaudio.rules
case $1 in
"start")
<<<<<<< HEAD:command/bt-simple.sh
   /usr/bin/mpc stop
;;
"stop")
   /usr/bin/mpc play
;;
=======
   mpc stop 
   systemctl restart bluealsa-aplay ;;
"stop")
   systemctl stop bluealsa-aplay
   mpc play ;;
>>>>>>> 8eeee1b5988ec330bc445abf6873e293022bea2c:command/bt-sink.sh
esac
