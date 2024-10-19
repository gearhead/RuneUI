#!/usr/bin/sh
# bt-connect.sh
# shell script to switch off mpd so that a source connected
# will play through alsa system to the default output.
# also if a speaker/headset is added, it will set it as the default
# output in mpd.conf
# update disconnect works, connect does not.
file="/etc/mpd.conf"
case "$1" in
add)
    # wait for PCM(s) to be created, with absolute timeout of 5 seconds.
    timeout 5 stdbuf -oL bluealsactl -q monitor | grep -q -m 1 ^PCMAdded || exit 0
    case "$(/usr/bin/bluealsactl -q list-pcms | grep a2dp | grep -o -E '(sink|source)$')" in
    *sink*) # attached speaker(s)
        mpc stop
        systemctl stop mpd
        sed -i '/audio_output/,/}/ s/enabled/#&/ ' $file
        mapfile -t bts < <( aplay -L )
        lines=$(aplay -L | wc -l )
        let lines='lines-1'
        if [ $lines -ne 0 ]; then
            for i in $( eval echo {0..$lines..2});do
                if echo "${bts[$i]}" | grep -q a2dp ; then
                    name=$(echo ${bts[$i+1]} | cut -d "," -f 1)
                    device=$(echo ${bts[$i]})
                    echo "audio_output {" >> $file
                    echo -ne "        name            \"" >> $file
                    echo -ne $name >> $file
                    echo "\"" >> $file
                    echo "        type            \"alsa\"" >> $file
                    echo -ne "        device          \"" >> $file
                    echo -ne $device >> $file
                    echo "\"" >> $file
                    echo "        mixer_type      \"software\"" >> $file
                    echo "        auto_resample   \"no\"" >> $file
                    echo "        auto_format     \"no\"" >> $file
                    echo "        enabled         \"yes\"" >> $file
                    echo "}" >> $file
                fi
            done
        fi
        systemctl start mpd.socket
        # set default output in mpd.conf to BT
        ;;
    *source*) # attached phone
        # switch to bluetooth playback mode
        id=$( uuidgen | md5sum | cut -d ' ' -f 1 )
        # switch player by starting a system worker background job (rune_SY_wrk > switchplayer)
        #   by writing the command to the worker redis hash and fifo queue
        redis-cli hset w_queue "$id" '{"wrkcmd":"switchplayer","action":null,"args":"Bluetooth"}'
        redis-cli lpush w_queue_fifo "$id"
        # this will also stop any other streaming players and/or pause MPD
        ;;
    esac
    ;;
remove)
    mpc stop
    blue=$(grep -n bluealsa $file | cut -d ":" -f 1)
    # line where we found "bluealsa" if found
    if [ $blue -ne 0 ]; then
        systemctl stop mpd
        mapfile -t ao < <( grep -n audio_output $file | cut -d ":" -f 1 )
        numouts=${#ao[@]}
        mapfile -t bracket < <( grep -n } $file | cut -d ":" -f 1 )
        numbrkts=${#bracket[@]}
        let numbrkts='numbrkts-1'
        let numouts='numouts-1'
        # find which bracket line number is greater than bluealsa and which ao is less than
        let i=0
        while [ $blue -gt ${bracket[$i]} ]; do
              (( i++))
        done
        # i is the index of the bracket past bluealsa
        end=${bracket[$i]}
        # now lets find the ao which is just greater than bluealsa
        let k=0
        # if blue is gt this ao and less then brkt, we found it.
        for j in $(eval echo {0..$numouts});do
             if test $blue -gt ${ao[$j]}; then
             (( k++ ))
             fi
        done
        begin=${ao[$k-1]}
        #echo $begin
        #echo $end
        sed -i "$begin,$end d" $file
        systemctl start mpd.socket
        # remove the BT default if it was added to mpd.conf
    fi
    player=$( redis-cli get activePlayer | xargs )
    if [ "$player" == "Bluetooth" ]; then
        # switch to previous playback mode
        id=$( uuidgen | md5sum | cut -d ' ' -f 1 )
        # switch player by starting a system worker background job (rune_SY_wrk > stopplayer)
        #   by writing the command to the worker redis hash and fifo queue
        redis-cli hset w_queue "$id" '{"wrkcmd":"stopplayer","action":null,"args":null}'
        redis-cli lpush w_queue_fifo "$id"
        # this will stop the current player, switch to the previous player and if it was MPD then reset its previous play status (i.e stop/play/pause)
    fi
   ;;
esac
