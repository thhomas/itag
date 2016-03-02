#!/bin/bash
#
# Copyright 2013 Jérôme Gasperi
#
# Licensed under the Apache License, version 2.0 (the "License");
# You may not use this file except in compliance with the License.
# You may obtain a copy of the License at:
#
#   http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
# WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
# License for the specific language governing permissions and limitations
# under the License.
#

# Paths are mandatory from command line
SUPERUSER=postgres
DB=itag
USER=itag
HOSTNAME=localhost
usage="## iTag French data sources installation\n\n  Usage $0 -D <data directory> [-d <database name> -s <database SUPERUSER> -u <database USER> -F -H <server HOSTNAME>]\n\n  -D : absolute path to the data directory containing french datasources\n  -s : database SUPERUSER (default "postgres")\n  -u : database USER (default "itag")\n  -d : database name (default "itag")\n  -H : postgres server hostname (default localhost)\n  -F : drop schema datasources first\n"
while getopts "D:d:s:u:H:hF" options; do
    case $options in
        D ) DATADIR=`echo $OPTARG`;;
        d ) DB=`echo $OPTARG`;;
        u ) USER=`echo $OPTARG`;;
        s ) SUPERUSER=`echo $OPTARG`;;
        H ) HOSTNAME=`echo "-h "$OPTARG`;;
        h ) echo -e $usage;;
        F ) DROPFIRST=YES;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done
if [ "$DATADIR" = "" ]
then
    echo -e $usage
    exit 1
fi

##### DROP SCHEMA FIRST ######
if [ "$DROPFIRST" = "YES" ]
then
psql -d $DB -U $SUPERUSER -h $HOSTNAME << EOF
DROP SCHEMA IF EXISTS france CASCADE;
EOF
fi

psql -d $DB -U $SUPERUSER -h $HOSTNAME << EOF
CREATE SCHEMA france;
EOF

# ================== France =====================

## French communes
shp2pgsql -g geom -W LATIN1 -s 2154:4326 -I $DATADIR/GEOFLA_2-1_COMMUNE_SHP_LAMB93_FXX_2015-12-01/GEOFLA/1_DONNEES_LIVRAISON_2015/GEOFLA_2-1_SHP_LAMB93_FR-ED152/COMMUNE/COMMUNE.shp france.commune | psql -d $DB -U $SUPERUSER -h $HOSTNAME
shp2pgsql -g geom -a -W LATIN1 -s 4471:4326 -I $DATADIR/GEOFLA_2-1_COMMUNE_SHP_RGM04UTM38S_D976_2015-12-01/GEOFLA/1_DONNEES_LIVRAISON_2015/GEOFLA_2-1_SHP_RGM04UTM38S_D976-ED152/COMMUNE/COMMUNE.shp france.commune | psql -d $DB -U $SUPERUSER -h $HOSTNAME
shp2pgsql -g geom -a -W LATIN1 -s 2975:4326 -I $DATADIR/GEOFLA_2-1_COMMUNE_SHP_RGR92UTM40S_D974_2015-12-01/GEOFLA/1_DONNEES_LIVRAISON_2015/GEOFLA_2-1_SHP_RGR92UTM40S_D974-ED152/COMMUNE/COMMUNE.shp france.commune | psql -d $DB -U $SUPERUSER -h $HOSTNAME
shp2pgsql -g geom -a -W LATIN1 -s 2970:4326 -I $DATADIR/GEOFLA_2-1_COMMUNE_SHP_UTM20W84GUAD_D971_2015-12-01/GEOFLA/1_DONNEES_LIVRAISON_2015/GEOFLA_2-1_SHP_UTM20W84GUAD_D971-ED152/COMMUNE/COMMUNE.shp france.commune | psql -d $DB -U $SUPERUSER -h $HOSTNAME
shp2pgsql -g geom -a -W LATIN1 -s 2973:4326 -I $DATADIR/GEOFLA_2-1_COMMUNE_SHP_UTM20W84MART_D972_2015-12-01/GEOFLA/1_DONNEES_LIVRAISON_2015/GEOFLA_2-1_SHP_UTM20W84MART_D972-ED152/COMMUNE/COMMUNE.shp france.commune | psql -d $DB -U $SUPERUSER -h $HOSTNAME
shp2pgsql -g geom -a -W LATIN1 -s 2972:4326 -I $DATADIR/GEOFLA_2-1_COMMUNE_SHP_UTM22RGFG95_D973_2015-12-01/GEOFLA/1_DONNEES_LIVRAISON_2015/GEOFLA_2-1_SHP_UTM22RGFG95_D973-ED152/COMMUNE/COMMUNE.shp france.commune | psql -d $DB -U $SUPERUSER -h $HOSTNAME
psql -d $DB -U $SUPERUSER -h $HOSTNAME << EOF
CREATE INDEX idx_communefrance_commune ON france.commune (nom_com);
CREATE INDEX idx_communefrance_dept ON france.commune (nom_dept);
CREATE INDEX idx_communefranc_region ON france.commune (nom_reg);
EOF

# GRANT RIGHTS TO itag USER
psql -U $SUPERUSER -d $DB -h $HOSTNAME << EOF
GRANT ALL on SCHEMA france to $USER;
GRANT SELECT on france.commune to $USER;
GRANT SELECT on france.commune_gid_seq to $USER;
EOF
