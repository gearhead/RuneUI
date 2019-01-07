#!/bin/bash
LINES=$(avahi-browse -lprt _snapcast._tcp |grep ^= | awk -F ";" '{printf ("{\42name\42:\42%s\42,\42ip\42:\42%s\42}\n", $7,$8)}')
OUTPUT=""
for l in $LINES; do
	if [ "$OUTPUT" != "" ]; then
		OUTPUT="$OUTPUT,$l"
	else
		OUTPUT="$l"
	fi
done
echo "[$OUTPUT]"
