#!/usr/bin/bash
# add BT output to MPD
# first disable any existing audio output by commenting it
# could not figure out how to delete the line in sed
echo "lets add a device"
file="/etc/mpd.conf"
# wait for PCM(s) to be created, with absolute timeout of 5 seconds.
#timeout 5 stdbuf -oL bluealsa-cli -q monitor | grep -q -m 1 ^PCMAdded || exit 0
#timeout 5 bluealsa-cli -q monitor | grep -q -m 1 ^PCMAdded || exit 0
echo "found one"
    case "$(/usr/bin/bluealsa-cli -q list-pcms | grep a2dp | grep -o -E '(sink|source)$')" in
    *sink*) # attached speaker(s)
    echo "sink"
        mpc stop
        systemctl stop mpd
        sed -i '/audio_output/,/}/ s/enabled/#&/ ' $file
        mapfile -t bts < <( aplay -L )
        lines=$(aplay -L | wc -l )
        let lines='lines-1'
        if [ $lines -ne 0 ]; then
            for i in $( eval echo {0..$lines});do
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
        echo "source"
        mpc stop
        # this is enough to allow a device to play through alsa
        ;;
    esac
