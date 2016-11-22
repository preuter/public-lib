#!/bin/bash

##
# Perform a sequence of operations to keep data up-to-date.
# 
#
# @file mkdocs.sh
# @date 2016-11-21 19:20 EST
# @author Paul Reuter
# @version 1.0.0
#
# @modifications
# 1.0.0 - 2016-11-21 - Created from template: up2date.sh
##


ABSPATH="$(cd "${0%/*}" 2>/dev/null; echo "$PWD"/"${0##*/}")"
CWD=`dirname "$ABSPATH"`
cd $CWD/../

rm -rf ./docs

phpdoc project:run --target ./docs/html --cache-folder ./docs/cache --directory ./src --title "Public Core Libraries" --visibility public,protected --sourcecode

rm ./docs/html/.htaccess

# EOF -- mkdocs.sh
