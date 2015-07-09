#!/bin/bash

script_path=`dirname $0`
cd $script_path/../../../../

set -o errexit;

DESTINATION="../../$1"

# only copy Base and Metadata models to application models directory
cp modules/dnamodels/models/base/Base*.php $DESTINATION/base/
cp modules/dnamodels/models/metadata/Metadata*.php $DESTINATION/metadata/
rm modules/dnamodels/models/base/Base*.php
rm modules/dnamodels/models/metadata/Metadata*.php

#console/yiic qa-state process --verbose

# only remove models that already exist
for file in modules/dnamodels/models/*.php; do
    target="$DESTINATION/"$(basename "$file")
    if [ ! -e "$target" ]; then
        echo "New file $target available"
        mv $file $target
    else
        rm $file
        #echo "File $file already exists and was not overwritten"
        :
    fi
done
