#!/bin/bash
#
#  Copyright (C) 2013-2014 RuneAudio Team
#  http://www.runeaudio.com
#
#  RuneUI
#  copyright (C) 2013-2014 – Andrea Coiutti (aka ACX) & Simone De Gregori (aka Orion)
#
#  RuneOS
#  copyright (C) 2013-2014 – Simone De Gregori (aka Orion) & Carmelo San Giovanni (aka Um3ggh1U)
#
#  RuneAudio website and logo
#  copyright (C) 2013-2014 – ACX webdesign (Andrea Coiutti)
#
#  This Program is free software; you can redistribute it and/or modify
#  it under the terms of the GNU General Public License as published by
#  the Free Software Foundation; either version 3, or (at your option)
#  any later version.
#
#  This Program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
#  GNU General Public License for more details.
#
#  You should have received a copy of the GNU General Public License
#  along with RuneAudio; see the file COPYING.  If not, see
#  <http://www.gnu.org/licenses/gpl-3.0.txt>.
#
#  file: command/orion_optimize.sh
#  version: 1.3
#  coder: Simone De Gregori
#
#####################################
ver="1.3"
set +x # echo no commands to cli
set +e # continue on errors

####################
# common functions #
####################
# adjust the mtu for all connected nics with an ip-address
modmtu () {
    # parameter 1 ($1) is the mtu value
    nics=$( ip -br add | grep ' UP ' | cut -d " " -f 1 | xargs )
    for i in $nics; do
        ifconfig $i mtu $1
    done
}

# adjust the txqueuelen for all connected nics with an ip-address
modtxqueuelen () {
    # parameter 1 ($1) is the mtu value
    nics=$( ip -br add | grep ' UP ' | cut -d " " -f 1 | xargs )
    for i in $nics; do
        ifconfig $i txqueuelen $1
    done
}

# adjust Kernel scheduler latency based on Architecture
modKschedLatency () {
    local "${@}"
    if (($((10#${hw})) == "1"))
    # RaspberryPi
    then
        echo "RaspberryPi"
        echo ${s01} > /proc/sys/kernel/sched_latency_ns
        echo -n "sched_latency_ns = "${s01}
        echo -n performance > /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor
    elif (($((10#${hw})) == "2"))
    # CuBox
    then
        echo "CuBox"
        echo -n ${s02} > /proc/sys/kernel/sched_latency_ns
        echo "sched_latency_ns = "${s02}
    elif (($((10#${hw})) == "3"))
    # UDOO
    then
        echo "UDOO"
        echo -n ${s03} > /proc/sys/kernel/sched_latency_ns
        echo "sched_latency_ns = "${s03}
    elif (($((10#${hw})) == "4"))
    # BeagleBoneBlack
    then
        echo "BeagleBoneBlack"
        echo -n ${s04} > /proc/sys/kernel/sched_latency_ns
        echo "sched_latency_ns = "${s04}
    elif (($((10#${hw})) == "5"))
    # Compulab Utilite
    then
        echo "Utilite"
        echo -n ${s04} > /proc/sys/kernel/sched_latency_ns
        echo "sched_latency_ns = "${s04}
    elif (($((10#${hw})) == "6"))
    # Cubietruck
    then
        echo "Cubietruck"
        echo -n ${s06} > /proc/sys/kernel/sched_latency_ns
        echo "sched_latency_ns = "${s06}
    elif (($((10#${hw})) == "7"))
    # Cubox-i
    then
        echo "Cubox-i"
        echo -n ${s07} > /proc/sys/kernel/sched_latency_ns
        echo "sched_latency_ns = "${s07}
    elif (($((10#${hw})) == "8"))
    # RaspberryPi2/3/4
    then
        echo "RaspberryPi2/3/4"
        echo -n ${s08} > /proc/sys/kernel/sched_latency_ns
        echo "sched_latency_ns = "${s08}
    elif (($((10#${hw})) == "9"))
    # ODROID C1
    then
        echo "ODROIDC1"
        echo -n ${s09} > /proc/sys/kernel/sched_latency_ns
        echo "sched_latency_ns = "${s09}
    elif (($((10#${hw})) == "10"))
    # ODROID C2
    then
        echo "ODROIDC2"
        echo -n ${s10} > /proc/sys/kernel/sched_latency_ns
        echo "sched_latency_ns = "${s10}
    fi
}


##################
# common startup #
##################

# when it is a Pi 01 (archv6 - single processor) or Pi 08 (multiprocessors) and it is the first time save the initial values, otherwise restore the saved values
# these values are reset on boot
if [ "$2" == "01" ] || [ "$2" == "08" ]; then
    # its a Pi 01 (archv6 - single processor) or Pi 08 (multiprocessors)
    declare -a par_arr=(/sys/devices/system/cpu/cpu0/cpufreq/scaling_governor /sys/devices/system/cpu/cpu1/cpufreq/scaling_governor /sys/devices/system/cpu/cpu2/cpufreq/scaling_governor /sys/devices/system/cpu/cpu3/cpufreq/scaling_governor /proc/sys/vm/swappiness /sys/block/mmcblk0/queue/scheduler /proc/sys/kernel/sched_latency_ns /proc/sys/kernel/sched_rt_period_us /proc/sys/kernel/sched_rt_runtime_us /proc/sys/kernel/sched_autogroup_enabled /proc/sys/kernel/sched_rr_timeslice_ms /proc/sys/kernel/sched_min_granularity_ns /proc/sys/kernel/sched_wakeup_granularity_ns /proc/sys/kernel/hung_task_check_count /proc/sys/vm/stat_interval /proc/sys/vm/dirty_background_ratio)
    if [ -f "/tmp/orion_reset.firsttime" ]; then
        # the orion_reset file exists, reset the values from the redis store
        # first remove any files which we created
        for i in "${par_arr[@]}"; do
            exists=$( redis-cli hexists orion_reset "$i" )
            if [ -f "$i" ] && [ "$exists" == "0" ]; then
                # the file exists, we created the file as it did not exist before, delete it
                rm "$i"
            fi
        done
        # now reset the values of existing files which we modified
        keys=$( redis-cli hkeys orion_reset | xargs )
        for i in $keys; do
            val=$( redis-cli hget orion_reset "$i" )
            echo -n "$val" > "$i"
        done
    else
        # the orion_reset file does not exist, save the values in redis and create the file
        # delete the current saved values
        redis-cli del orion_reset
        # save the values which are set
        for i in "${par_arr[@]}"; do
            if [ -f "$i" ]; then
                val=$( cat "$i" | cut -d '[' -f 2 | cut -d ']' -f 1 | xargs )
                redis-cli hset orion_reset "$i" "$val"
                # echo "$i $val"
            fi
        done
        # create the orion_reset file
        touch "/tmp/orion_reset.firsttime"
    fi
fi

##################
# sound profiles #
##################

if [ "$1" == "default" ]; then
    modmtu 1500
    modtxqueuelen 1000
    echo "Linux DEFAULT sound signature profile"
elif [ "$1" == "RuneAudio" ]; then
    if [ "$2" == "08" ]; then
        modmtu 9000
        modtxqueuelen 4000
        echo -n performance > /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor
        echo -n performance > /sys/devices/system/cpu/cpu1/cpufreq/scaling_governor
        echo -n performance > /sys/devices/system/cpu/cpu2/cpufreq/scaling_governor
        echo -n performance > /sys/devices/system/cpu/cpu3/cpufreq/scaling_governor
        echo -n bfq > /sys/block/mmcblk0/queue/scheduler # bfq has focus on low latency, its complex, has higher overhead
        echo -n 1000000 > /proc/sys/kernel/sched_latency_ns
        echo -n 100000 > /proc/sys/kernel/sched_min_granularity_ns
        echo -n 25000 > /proc/sys/kernel/sched_wakeup_granularity_ns
        echo -n 1 > /proc/sys/kernel/hung_task_check_count
        echo -n 20 > /proc/sys/vm/stat_interval
        echo -n -1 > /proc/sys/kernel/sched_rt_runtime_us
        echo -n 5 > /proc/sys/vm/dirty_background_ratio
    else
        modmtu 1500
        modtxqueuelen 1000
        echo -n 0 > /proc/sys/vm/swappiness
        modKschedLatency hw=$2 s01=1500000 s02=4500000 s03=4500000 s04=4500000 s05=4500000 s06=4500000 s07=4500000 s08=4500000 s09=4500000 s10=4500000 u01=3 u02=3 u03=3 u04=3 u05=3 u06=3 u07=3 u08=3 u09=3 u10=3
    fi
    sleep 2
    echo "(RuneAudio) sound signature profile"
elif [ "$1" == "ACX" ]; then
    modmtu 1500
    modtxqueuelen 4000
    echo -n 0 > /proc/sys/vm/swappiness
    modKschedLatency hw=$2 s01=850000 s02=3500075 s03=3500075 s04=3500075 s05=3500075 s06=3500075 s07=3500075 s08=3500075 s09=3500075 s10=3500075 u01=2 u02=2 u03=2 u04=2 u05=2 u06=2 u07=2 u08=2 u09=2 u10=2
    echo "(ACX) sound signature profile"
elif [ "$1" == "Orion" ]; then
    modmtu 1000
    modtxqueuelen 1000
    echo -n 20 > /proc/sys/vm/swappiness
    modKschedLatency hw=$2 s01=500000 s02=500000 s03=500000 s04=1000000 s05=1000000 s06=1000000 s07=100000 s08=1000000 s09=1000000 s10=1000000 u01=1 u02=1 u03=1 u04=1 u05=1 u06=1 u07=1 u08=1 u09=1 u10=1
    echo "(Orion) sound signature profile"
elif [ "$1" == "OrionV2" ]; then
    modmtu 1000
    modtxqueuelen 4000
    echo -n 0 > /proc/sys/vm/swappiness
    modKschedLatency hw=$2 s01=120000 s02=2000000 s03=2000000 s04=2000000 s05=2000000 s06=2000000 s07=2000000 s08=2000000 s09=2000000 s10=2000000 u01=2 u02=2 u03=2 u04=2 u05=2 u06=2 u07=2 u08=2 u09=2 u10=2
    sleep 2
    echo "(OrionV2) sound signature profile"
elif [ "$1" == "OrionV3_iqaudio" ]; then
    if [ "$2" == "01" ]; then
        modmtu 1000
        modtxqueuelen 4000
        echo -n 1500000 > /proc/sys/kernel/sched_latency_ns
        echo -n 950000 > /proc/sys/kernel/sched_rt_period_us
        echo -n 950000 > /proc/sys/kernel/sched_rt_runtime_us
        echo -n 0 > /proc/sys/kernel/sched_autogroup_enabled
        echo -n 1 > /proc/sys/kernel/sched_rr_timeslice_ms
        echo -n 950000 > /proc/sys/kernel/sched_min_granularity_ns
        echo -n 1000000 > /proc/sys/kernel/sched_wakeup_granularity_ns
    else
        modmtu 1000
        modtxqueuelen 4000
        echo -n 0 > /proc/sys/vm/swappiness
        modKschedLatency hw=$2 s01=139950 s02=2000000 s03=2000000 s04=2000000 s05=2000000 s06=2000000 s07=2000000 s08=2000000 s09=2000000 s10=2000000 u01=2 u02=2 u03=2 u04=2 u05=2 u06=2 u07=2 u08=2 u09=2 u10=2
    fi
    sleep 2
    echo "(OrionV3 optimized for IQaudio Pi-DAC) sound signature profile"
elif [ "$1" == "OrionV3_berrynosmini" ]; then
    if [ "$2" == "01" ]; then
        modmtu 1000
        modtxqueuelen 4000
        echo -n 60 > /proc/sys/vm/swappiness
        echo -n 145655 > /proc/sys/kernel/sched_latency_ns
        echo -n 1 > /proc/sys/kernel/sched_rt_period_us
        echo -n 1 > /proc/sys/kernel/sched_rt_runtime_us
        echo -n 0 > /proc/sys/kernel/sched_autogroup_enabled
        echo -n 100 > /proc/sys/kernel/sched_rr_timeslice_ms
        echo -n 400000 > /proc/sys/kernel/sched_min_granularity_ns
        echo -n 1 > /proc/sys/kernel/sched_wakeup_granularity_ns
    else
        modmtu 1000
        modtxqueuelen 4000
        echo -n 0 > /proc/sys/vm/swappiness
        modKschedLatency hw=$2 s01=139950 s02=2000000 s03=2000000 s04=2000000 s05=2000000 s06=2000000 s07=2000000 s08=2000000 s09=2000000 s10=2000000 u01=2 u02=2 u03=2 u04=2 u05=2 u06=2 u07=2 u08=2 u09=2 u10=2
    fi
    sleep 2
    echo "(OrionV3 optimized for BerryNOS-mini I2S DAC) sound signature profile"
elif [ "$1" == "ACX" ]; then
    modmtu 1500
    modtxqueuelen 4000
    echo -n 0 > /proc/sys/vm/swappiness
    modKschedLatency hw=$2 s01=850000 s02=3500075 s03=3500075 s04=3500075 s05=3500075 s06=3500075 s07=3500075 s08=3500075 s09=3500075 s10=3500075 u01=2 u02=2 u03=2 u04=2 u05=2 u06=2 u07=2 u08=2 u09=2 u10=2
    echo "(ACX) sound signature profile"
elif [ "$1" == "Dynobot" ]; then
    if [ "$2" == "08" ]; then
        modmtu 9000
        modtxqueuelen 4000
        echo -n performance > /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor
        echo -n performance > /sys/devices/system/cpu/cpu1/cpufreq/scaling_governor
        echo -n performance > /sys/devices/system/cpu/cpu2/cpufreq/scaling_governor
        echo -n performance > /sys/devices/system/cpu/cpu3/cpufreq/scaling_governor
        echo -n kyber > /sys/block/mmcblk0/queue/scheduler # was previously noop (depreciated), kyber is a simple, low overhead, low latency scheduler, similar to noop but has completely different algorithm
        echo -n 1000000 > /proc/sys/kernel/sched_latency_ns
        echo -n 100000 > /proc/sys/kernel/sched_min_granularity_ns
        echo -n 25000 > /proc/sys/kernel/sched_wakeup_granularity_ns
        echo -n 1 > /proc/sys/kernel/hung_task_check_count
        echo -n 20 > /proc/sys/vm/stat_interval
        echo -n -1 > /proc/sys/kernel/sched_rt_runtime_us
        echo -n 5 > /proc/sys/vm/dirty_background_ratio
    else
        modmtu 1500
        modtxqueuelen 1000
        echo -n 0 > /proc/sys/vm/swappiness
        modKschedLatency hw=$2 s01=500000 s02=3700000 s03=3700000 s04=3700000 s05=3700000 s06=3700000 s07=3700000 s08=3700000 s09=3700000 s10=3700000 u01=3 u02=3 u03=3 u04=3 u05=3 u06=3 u07=3 u08=3 u09=3 u10=3
    fi
    sleep 2
    echo "(Dynobot for Pi2, 3 and 4) sound signature profile"
elif [ "$1" == "Frost_dk" ]; then
    if [ "$2" == "08" ]; then
        modmtu 9000
        modtxqueuelen 4000
        echo -n performance > /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor
        echo -n performance > /sys/devices/system/cpu/cpu1/cpufreq/scaling_governor
        echo -n performance > /sys/devices/system/cpu/cpu2/cpufreq/scaling_governor
        echo -n performance > /sys/devices/system/cpu/cpu3/cpufreq/scaling_governor
        echo -n mq-deadline > /sys/block/mmcblk0/queue/scheduler # mq-deadline is the current default scheduler, balanced approach (throughput vs. latency), this line could be removed
        echo -n 1000000 > /proc/sys/kernel/sched_latency_ns
        echo -n 100000 > /proc/sys/kernel/sched_min_granularity_ns
        echo -n 25000 > /proc/sys/kernel/sched_wakeup_granularity_ns
        echo -n 1 > /proc/sys/kernel/hung_task_check_count
        echo -n 20 > /proc/sys/vm/stat_interval
        echo -n -1 > /proc/sys/kernel/sched_rt_runtime_us
        echo -n 5 > /proc/sys/vm/dirty_background_ratio
    else
        modmtu 1000
        modtxqueuelen 4000
        echo -n 0 > /proc/sys/vm/swappiness
        modKschedLatency hw=$2 s01=120000 s02=2000000 s03=2000000 s04=2000000 s05=2000000 s06=2000000 s07=2000000 s08=2000000 s09=2000000 s10=2000000 u01=2 u02=2 u03=2 u04=2 u05=2 u06=2 u07=2 u08=2 u09=2 u10=2
    fi
    sleep 2
    echo "(Frost_dk for Pi2, 3 and 4) sound signature profile"
elif [ "$1" == "janui" ]; then
    if [ "$2" == "08" ]; then
        modmtu 9000
        modtxqueuelen 4000
        echo -n performance > /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor
        echo -n performance > /sys/devices/system/cpu/cpu1/cpufreq/scaling_governor
        echo -n performance > /sys/devices/system/cpu/cpu2/cpufreq/scaling_governor
        echo -n performance > /sys/devices/system/cpu/cpu3/cpufreq/scaling_governor
        echo -n bfq > /sys/block/mmcblk0/queue/scheduler # bfq has focus on low latency, its complex, has higher overhead
        echo -n 1000000 > /proc/sys/kernel/sched_latency_ns
        echo -n 100000 > /proc/sys/kernel/sched_min_granularity_ns
        echo -n 25000 > /proc/sys/kernel/sched_wakeup_granularity_ns
        echo -n 1 > /proc/sys/kernel/hung_task_check_count
        echo -n 20 > /proc/sys/vm/stat_interval
        echo -n -1 > /proc/sys/kernel/sched_rt_runtime_us
        echo -n 5 > /proc/sys/vm/dirty_background_ratio
    else
        modmtu 1500
        modtxqueuelen 1000
        echo -n performance > /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor
        echo -n kyber > /sys/block/mmcblk0/queue/scheduler # kyber is a simple, low overhead, low latency scheduler
        echo -n 0 > /proc/sys/vm/swappiness
        modKschedLatency hw=$2 s01=1500000 s02=4500000 s03=4500000 s04=4500000 s05=4500000 s06=4500000 s07=4500000 s08=4500000 s09=4500000 s10=4500000 u01=3 u02=3 u03=3 u04=3 u05=3 u06=3 u07=3 u08=3 u09=3 u10=3
    fi
    sleep 2
    echo "(RuneAudio) sound signature profile"
fi

# dev
if [ "$1" == "dev" ]; then
echo "flush DEV sound profile 'fake'"
fi

if [ "$1" == "" ]; then
echo "Orion Optimize Script v$ver"
echo "Usage: $0 {default|RuneAudio|ACX|Orion|OrionV2|OrionV3_iqaudio|OrionV3_berrynosmini|Um3ggh1U|Dynobot|Frost_dk|janui} {architectureID}"
exit 1
fi
