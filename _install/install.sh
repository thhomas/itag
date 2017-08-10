#!/bin/bash
set -e

ITAG_DATA=/itag/data

/itag-install/installDB.sh -F -p itag -H localhost
# General datasources
/itag-install/installDatasources.sh -F -D $ITAG_DATA

# Gazetteer
/itag-install/installGazetteerDB.sh -F -D $ITAG_DATA

# French datasources
/itag-install/installFrenchDatasources.sh -F -D $ITAG_DATA
