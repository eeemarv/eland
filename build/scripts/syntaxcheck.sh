#!/bin/bash

SYNTAX_RE='No syntax errors*'
for i in $(find ./ -type f -name '*.php' | grep -v "./contrib"); do
    SYNTAX_OUTPUT=$(php -l $i)
	if [[ "$SYNTAX_OUTPUT" != $SYNTAX_RE ]]; then
		echo "$SYNTAX_OUTPUT"
		exit 1
	fi
done
