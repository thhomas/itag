<?php
/*
 * Copyright 2013 Jérôme Gasperi
 *
 * Licensed under the Apache License, version 2.0 (the "License");
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at:
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

class Tagger_French extends Tagger {

    /*
     * Data references
     */
    public $references = array(
        array(
            'dataset' => 'GEOFLA® Communes 2015 v2.1',
            'author' => 'IGN',
            'license' => 'Free of Charge',
            'url' => 'http://professionnels.ign.fr/geofla#tab-3'
        )
    );
    
    
    /**
     * Constructor
     * 
     * @param DatabaseHandler $dbh
     * @param array $config
     */
    public function __construct($dbh, $config) {
        parent::__construct($dbh, $config);
    }
    
    /**
     * TODO Tag metadata
     * 
     * @param array $metadata
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function tag($metadata, $options = array()) {
        parent::tag($metadata, $options);
        return $this->process($metadata['footprint'], $options);
        
    }
    
    /**
     * Compute intersected information from input WKT footprint
     * 
     * @param string $footprint
     * @param array $options
     * 
     */
    private function process($footprint, $options) {

        /*
         * Toponyms
         */
        if (isset($options['toponyms'])) {
            $this->addToponyms = $options['toponyms'];
        }
        
        /*
         * Initialize empty array
         */
        $continents = array();
                
        /*
         * Add continents and countries
         */
        $this->add($continents, $footprint);
        
        return array(
            'political' => array(
                'continents' => $continents
            )
        );
        
    }
    
    /**
     * Add continents/countries and regions/depts to political array
     *
     * @param array $continents
     * @param string $footprint
     *
     */
    private function add(&$continents, $footprint) {
      $this->addContinents($continents, $footprint);
    }
    
    /**
     * 
     */
    private function addContinents(&$continents, $footprint) {
      $geom = $this->postgisGeomFromText($footprint);
      $query = 'SELECT name as name, normalize(name) as id, continent as continent, normalize(continent) as continentid, ' . $this->postgisArea($this->postgisIntersection('geom', $geom)) . ' as area, ' . $this->postgisArea('geom') . ' as entityarea FROM datasources.countries WHERE st_intersects(geom, ' . $geom . ') ORDER BY area DESC';
      $results = $this->query($query);
      while ($element = pg_fetch_assoc($results)) {
        $continent = array(
          'name' => $element['continent'],
          'id' => 'continent:' . $element['continentid'],
          'countries' => array()
        );
        array_push($continents, $continent);
        $indexContinents = count($continents) - 1;

        $area = $this->toSquareKm($element['area']);
        array_push($continents[$indexContinents]['countries'], array(
            'name' => $element['name'],
            'id' => 'country:' . $element['id'],
            'pcover' => $this->percentage($area, $this->area),
            'gcover' => $this->percentage($area, $this->toSquareKm($element['entityarea'])),
            'regions' => array()
        ));
        $indexCountries = count($continents[$indexContinents]['countries']) - 1;
        
        $query = 'SELECT nom_com as name, normalize(nom_com) as id, nom_reg, code_reg, nom_dept, ' . $this->postgisArea($this->postgisIntersection('geom', $geom)) . ' as area, ' . $this->postgisArea('geom') . ' as entityarea FROM france.commune WHERE st_intersects(geom, ' . $geom . ') ORDER BY area DESC';
        $results = $this->query($query);
        while ($element = pg_fetch_assoc($results)) {
          $foundRegion = Null;
          for($r=count($continents[$indexContinents]['countries'][$indexCountries]['regions']);$r--;) {
            if($continents[$indexContinents]['countries'][$indexCountries]['regions'][$r]['id'] == 'regions:' . $element['code_reg']) {
              $foundRegion = $continents[$indexContinents]['countries'][$indexCountries]['regions'][$r];
            }
          }
          if(!$foundRegion) {
            $foundRegion = array(
                'name' => $element['nom_reg'],
                'id' => 'regions:' . $element['code_reg'],
                'states' => array()
            );
            array_push($continents[$indexContinents]['countries'][$indexCountries]['regions'], $foundRegion);
          }
        }
      }
    }
}
