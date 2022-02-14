#!/usr/bin/bash
# add BT output to MPD
# first disable any existing audio output by commenting it
# could not figure out how to delete the line in sed
file="/etc/mpd.conf"
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
