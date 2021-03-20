#!/bin/bash
#
# kill_weston.sh
# ------------------------
# This file will cause the back-en to kill the westen job with the command 'pkill -15 weston'
#
# setup
set +e # continue on errors
#
id=$(uuidgen | md5sum | cut -d ' ' -f 1)
# echo $id
redis-cli hset w_queue $id '{"wrkcmd":"kill_weston","action":null,"args":null}'
# redis-cli hget w_queue $id
#---
#End script
