#!/usr/bin/sh
# bt-connect.sh
# shell script to switch off mpd so that a source connected
# will play through alsa system to the default output.
# also if a speaker/headset is added, it will set it as the default
# output in mpd.conf
case "$1" in
add)
# wait for PCM(s) to be created, with absolute timeout of 5 seconds.
timeout 5 stdbuf -oL bluealsa-cli -q monitor | grep -q -m 1 ^PCMAdded || exit 0
	case "$(/usr/bin/bluealsa-cli -q list-pcms | grep -o -E '(sink|source)$')" in
	*sink*) # attached speaker(s)
		mpc stop
		# set default output in mpd.conf to BT
		;;
	*source*) # attached phone
		mpc stop
		# this is enough to allow a device to play through alsa
		;;
	esac
	;;
remove)
	# remove the BT default if it was added to mpd.conf
	mpc play
	;;
esac
