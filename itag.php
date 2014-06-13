<?php

/*
 * iTag
 *
 * Automatically tag a geographical footprint against every kind of things
 * (i.e. Land Cover, OSM data, population count, etc.)
 * Copyright 2013 Jérôme Gasperi <https://github.com/jjrom>
 * 
 * jerome[dot]gasperi[at]gmail[dot]com
 * 
 * 
 * This software is governed by the CeCILL-B license under French law and
 * abiding by the rules of distribution of free software.  You can  use,
 * modify and/ or redistribute the software under the terms of the CeCILL-B
 * license as circulated by CEA, CNRS and INRIA at the following URL
 * "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and  rights to copy,
 * modify and redistribute granted by the license, users are provided only
 * with a limited warranty  and the software's author,  the holder of the
 * economic rights,  and the successive licensors  have only  limited
 * liability.
 *
 * In this respect, the user's attention is drawn to the risks associated
 * with loading,  using,  modifying and/or developing or reproducing the
 * software by the user in light of its specific status of free software,
 * that may mean  that it is complicated to manipulate,  and  that  also
 * therefore means  that it is reserved for developers  and  experienced
 * professionals having in-depth computer knowledge. Users are therefore
 * encouraged to load and test the software's suitability as regards their
 * requirements in conditions enabling the security of their systems and/or
 * data to be ensured and,  more generally, to use and operate it in the
 * same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL-B license and that you accept its terms.
 * 
 */

// Remove PHP NOTICE
error_reporting(E_PARSE);

// Includes
include_once 'config/config.php';
include_once 'functions.php';

// This application can be called either from a shell or from an HTTP GET/POST request
$isShell = !empty($_SERVER['SHELL']);

// Output format
$output = 'json';

$footprint = null;
$dbInfos = null;
$dbLimit = null;

// What to compute
$keywords = array(
    'countries' => false,
    'continents' => false,
    'cities' => null,
    'geophysical' => false,
    'population' => false,
    'landcover' => false,
    'regions' => false,
	'french' => false
);

// Options
$modifiers = array(
    'hierarchical' => false,
    'ordered' => false
);


// Case 1 - Shell command line parameters
if ($isShell) {
    $help  = "\nUSAGE : php itag.php [options] -f <footprint in WKT> (or -d <db connection info>)\n";
    $help .= "OPTIONS:\n";
    $help .= "   -o [type] : output (json|pretty|insert|copy|hstore) - Note : if -d is choosen only 'hstore', 'insert' and 'copy' are used \n";
    $help .= "   -H : display hierarchical continents/countries/regions/cities (otherwise keywords are \"flat\") \n";
    $help .= "   -O : compute and order result by area of intersection\n";
    $help .= "   -c : Countries\n";
    $help .= "   -x : Continents\n";
    $help .= "   -C : Cities (main|all)\n";
    $help .= "   -R : Administrative level 1 (i.e. Regions and departements for France, USA states, etc.)\n";
    $help .= "   -F : Use French IGN data (will replace cities, administrative level 1)\n";
    $help .= "   -p : Population\n";
    $help .= "   -g : Geophysical information (i.e. plates, volcanoes)\n";
    $help .= "   -l : Land Cover (i.e. Thematical content - forest, water, urban, etc.\n";
    $help .= "   -t : (For DB connection only) Format is 'modifieddate,2014-10-10' : limit update to date'modifieddate' greater than 2014-10-10.\n";
    $help .= "   -d : DB connection info - dbhost:dbname:dbschema:dbuser:dbpassword:dbport:tableName:identifierColumnName:geometryColumnName\n";
    $help .= "\n\n";
    $options = getopt("cxC:Rpgld:f:o:OHt:h");
    foreach ($options as $option => $value) {
        if ($option === "f") {
            $footprint = $value;
        }
        if ($option === "d") {
            $dbInfos = split(':', $value);
        }
        if ($option === "t") {
            $dbLimit = split(',', $value);
        }
        if ($option === "o") {
            $output = $value;
        }
        if ($option === "c") {
            $keywords['countries'] = true;
        }
        if ($option === "x") {
            $keywords['continents'] = true;
        }
        if ($option === "C") {
            $keywords['cities'] = $value;
        }
        if ($option === "R") {
            $keywords['regions'] = true;
        }
        if ($option === "F") {
            $keywords['french'] = true;
        }
        if ($option === "g") {
            $keywords['geophysical'] = true;
        }
        if ($option === "l") {
            $keywords['landcover'] = true;
        }
        if ($option === "p") {
            $keywords['population'] = true;
        }
        if ($option === "H") {
            $modifiers['hierarchical'] = true;
        }
        if ($option === "O") {
            $modifiers['ordered'] = true;
        }
        if ($option === "h") {
            echo $help;
            exit;
        }
    }

    // Footprint is mandatory
    if (!$footprint && !$dbInfos) {
        echo $help;
        exit;
    }
}
/*
 *  Case 2 - Webservice parameters
 * 
 *  Note : -d option is not possible from Webservice
 */
else {
			
	if($_SERVER['REQUEST_METHOD'] == 'GET') {
		$http_param = $_REQUEST;
	} elseif($_SERVER['REQUEST_METHOD'] == 'POST') {
		parse_str(file_get_contents("php://input"),$http_param);
	}
    
    $keywords = array(
        'countries' => trueOrFalse($http_param['countries']),
        'continents' => trueOrFalse($http_param['continents']),
        'cities' => isset($http_param['cities']) ? $http_param['cities'] : null,
        'geophysical' => trueOrFalse($http_param['geophysical']),
        'population' => trueOrFalse($http_param['population']),
        'landcover' => trueOrFalse($http_param['landcover']),
        'regions' => trueOrFalse($http_param['regions']),
    	'french' => trueOrFalse($http_param['french'])
    );

    // Options
    $modifiers = array(
        'hierarchical' => trueOrFalse($http_param['hierarchical']),
        'ordered' => trueOrFalse($http_param['ordered']),
    );
    
    $footprint = isset($http_param['footprint']) ? $http_param['footprint'] : null;

    $output = isset($http_param['output']) ? $http_param['output'] : $output;
	    	
    if (!$footprint) {
        echo "footprint is mandatory";
        exit;
    }
}

// Connect to database
$dbh = getPgDB("host=" . DB_HOST . " dbname=" . DB_NAME . " user=" . DB_USER . " password=" . DB_PASSWORD);
pg_set_client_encoding($dbh, "UTF8");
if (!$dbh) {
    error($dbh, $isShell, "\nFATAL : No connection to database\n\n");
}

/*
 * Case 1 : User entries of DB
 *   'dbhost':'dbname':'dbuser':'dbpassword':'dbport':'table':'identifier column name':'geometry column name'
 */
if ($dbInfos) {
            
    if (count($dbInfos) !== 9) {
        error($dbh, $isShell, "\nFATAL : -d option format is dbhost:dbname:dbschema:dbuser:dbpassword:dbport:tableName:identifierColumnName:geometryColumnName\n\n");
    }

    $dbhSource = getPgDB("host=" . $dbInfos[0] . " dbname=" . $dbInfos[1] . " user=" . $dbInfos[3] . " password=" . $dbInfos[4]. " port=" . $dbInfos[5]);
    if (!$dbhSource) {
        error($dbhSource, $isShell, "\nFATAL : No connection to database $dbInfos[1]\n\n");
    }
  
    /*
     * Usefull constants !
     */
    $tableName = $dbInfos[2] . '.' . $dbInfos[6];
    $identifierColumn = $dbInfos[7];
    $geometryColumn = $dbInfos[8];
    $hstoreColumn = "keywords";
    // hstore is the default output !
    if (!isset($output) || $output !== 'copy' || $output !== 'insert') {
       $output = 'hstore';
    }
            
    /*
     * HSTORE
     */
    if (isset($output) && $output === 'hstore') {
        echo "-- Enable hstore in database \n";
        echo "CREATE EXTENSION hstore;\n\n";
        echo "-- Add keywords column to table " . $tableName . " \n";
        echo "ALTER TABLE " . $tableName . " ADD COLUMN keywords hstore DEFAULT '';\n";
        echo "\n";
    }
    
    /*
     * Count number of elements to process
     */
    $where = "";
    if ($dbLimit) {
        $where .= pg_escape_string($dbLimit[0]) . " > '" . pg_escape_string($dbLimit[1]) . "'";
    }
    $query = "SELECT count(*) as total FROM " . $tableName . ($where ? " WHERE " . $where : "");
    $results = pg_query($dbhSource, $query);
    if (!$results) {
        error($dbhSource, $isShell, "\nFATAL : $dbInfos[1] database connection error\n\n");
    }
    $result = pg_fetch_assoc($results);
    $total = $result['total'];
    $limit = 200;
    $pages = ceil($total / $limit);
    echo '-- Total number of elements to be processed : ' . $total . "\n";
 
    /*
     * COPY
     */
    if (isset($output) && $output === 'copy') {
        echo "COPY keywords (identifier, keyword, type) FROM stdin;\n";
    }
    
    /*
     * Seems like pagination is quicker !
     */
    $baseQuery = "SELECT " . $identifierColumn . " as identifier, st_AsText(" . $geometryColumn . ") as footprint FROM " . $tableName . " WHERE ST_IsValid(". $geometryColumn .") = 't'" . ($where ? " AND " . $where : "") . " ORDER BY " . $identifierColumn . " LIMIT " . $limit;
    for ($j = 0; $j < $pages; $j++) {
        $offset = ($j * $limit);
        $query = $baseQuery . " OFFSET " . $offset;
        
        if (!isset($output) || $output !== 'copy') {
            echo "\n";
            echo '-- Process elements from ' . $offset . ' to ' . ($offset + $limit - 1);
            echo '-- ' . $query;
            echo "\n";
        }
        
        $results = pg_query($dbhSource, $query);
        if (!$results) {
            error($dbhSource, $isShell, "\nFATAL : $dbInfos[1] database connection error\n\n");
        }

        while ($result = pg_fetch_assoc($results)) {
            if ($keywords['countries'] || $keywords['cities'] || $keywords['regions'] || $keywords['continents']) {
               
                $arr = getPolitical($dbh, $isShell, $result["footprint"], $keywords, $modifiers);
                
                if ($arr) {
                    // Continents
                    tostdin($result["identifier"], $arr["continents"], "continent", $tableName, $identifierColumn, $hstoreColumn, $output);

                    // Countries
                    tostdin($result["identifier"], $arr["countries"], "country", $tableName, $identifierColumn, $hstoreColumn, $output);

                    // Cities
                    if (isset($arr["cities"])) {
                        tostdin($result["identifier"], $arr["cities"], "city", $tableName, $identifierColumn, $hstoreColumn, $output);
                    }
                }
            }
            
            if ($keywords['geophysical']) {
                $arr = getGeophysical($dbh, $isShell, $result["footprint"]);
                if ($arr) {
                    tostdin($result["identifier"], $arr["volcanoes"], "volcano", $tableName, $identifierColumn, $hstoreColumn, $output);
                }
            }

            if ($keywords['landcover']) {
                $arr = getLandCover($dbh, $isShell, $result["footprint"], $modifiers);
                if ($arr) {
                    tostdin($result["identifier"], $arr["landUse"], "landuse", $tableName, $identifierColumn, $hstoreColumn, $output);
                }
            }
        }
        
    }
    
    pg_close($dbhSource);
    
    // Close COPY
    if (isset($output) && $output === 'copy') {
        echo "\.\n";
    }
    
    /*
     * HSTORE
     */
    if (isset($output) && $output === 'hstore') {
        list($schema, $table) = explode('.', $tableName);
        echo "-- Create GIN index in database \n";
        echo "CREATE INDEX " . $table . "_" . $hstoreColumn . "_idx ON " . $tableName . " USING GIN (" . $hstoreColumn . ");\n";
        echo "\n";
    }
    
    
}
/*
 * Case 2 : use footprint
 */
else {
    
    // Initialize GeoJSON output
    $geojson = array(
        'type' => 'FeatureCollection',
        'features' => array()
    );

    // Initialize Feature
    $feature = array(
        'type' => 'Feature',
        'geometry' => wktPolygon2GeoJSONGeometry($footprint),
        'properties' => array()
    );
    
    if ($keywords['french']) {
    	$feature['properties']['political'] = getFrenchPolitical($dbh, $isShell, $footprint, $keywords, $modifiers);
    } elseif ($keywords['countries'] || $keywords['cities'] || $keywords['regions'] || $keywords['continents']) {
        $feature['properties']['political'] = getPolitical($dbh, $isShell, $footprint, $keywords, $modifiers);
    }

    if ($keywords['geophysical']) {
        $feature['properties']['geophysical'] = getGeophysical($dbh, $isShell, $footprint);
    }

    if ($keywords['landcover']) {
        $feature['properties']['landCover'] = getLandCover($dbh, $isShell, $footprint, $modifiers);
    }

    if ($keywords['population'] && GPW2PGSQL_URL) {
        $gpwResult = getRemoteData(GPW2PGSQL_URL . urlencode($footprint), null);
        if ($gpwResult !== "") {
            $feature['properties']['population'] = trim($gpwResult);
        }

    }

    // Add feature array to feature collection array
    array_push($geojson['features'], $feature);

    // Set HTTP header if no shell
    if (!$isShell) {
        header("Pragma: no-cache");
        header("Expires: 0");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-cache, must-revalidate");
        header("Content-type: application/json; charset=utf-8");
    }

    if ($output === 'pretty') {
        echo json_format($geojson, true);
    }
    else if ($output === 'sql') {
        echo "SQL output is not yet implemented ! Use 'pretty' or 'json' instead\n";
    }
    else {
        echo json_encode($geojson);
    }
    if ($isShell) {
        echo "\n";
    }
}

// Clean exit
if ($dbh) {
    pg_close($dbh);
}

exit(0);