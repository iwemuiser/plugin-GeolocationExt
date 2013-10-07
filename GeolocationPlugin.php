<?php

define('GOOGLE_MAPS_API_VERSION', '3.x');
define('GEOLOCATION_MAX_LOCATIONS_PER_PAGE', 1000);
define('GEOLOCATION_DEFAULT_LOCATIONS_PER_PAGE', 50);
define('GEOLOCATION_PLUGIN_DIR', PLUGIN_DIR . '/Geolocation');

class GeolocationPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
            'install',
            'initialize',
            'uninstall',
            'config_form',
            'config',
            'define_acl',
            'define_routes',
            'after_save_item',
            'admin_items_show_sidebar',
            'public_items_show',
            'admin_items_search',
            'public_items_search',
            'items_browse_sql',
            'public_head',
            'admin_head'
            );
    
    protected $_filters = array(
            'admin_navigation_main',
            'response_contexts',
            'action_contexts',
            'admin_items_form_tabs',
            'public_navigation_items',
            'api_resources',
            'api_extend_items',
            );
    
    public $_all_geo_fields = array("point_of_interest",
                                "route",
                                "street_number",
                                "sublocality",
                                "locality",
                                "administrative_area_level_3",
                                "administrative_area_level_2",
                                "administrative_area_level_1",
                                "natural_feature",
                                "establishment",
                                "postal_code",
                                "postal_code_prefix",
                                "country",
                                "continent",
                                "planetary_body");

    public function hookInitialize(){
        $key = get_option('geolocation_gmaps_key');// ? get_option('geolocation_gmaps_key') : 'AIzaSyD6zj4P4YxltcYJZsRVUvTqG_bT1nny30o';
        $lang = "nl";
        queue_js_url("https://maps.googleapis.com/maps/api/js?sensor=false&libraries=places&key=$key&language=$lang");
        add_translation_source(dirname(__FILE__) . '/languages');
    }

    public function setUp(){
        if(plugin_is_active('Contribution')) {
#            $this->_hooks[] = 'contribution_append_to_type_form';
#            $this->_hooks[] = 'contribution_save_form';
        }
        parent::setUp();
    }
        
    public function hookAdminHead($args)
    {
#        $key = get_option('geolocation_gmaps_key');// ? get_option('geolocation_gmaps_key') : 'AIzaSyD6zj4P4YxltcYJZsRVUvTqG_bT1nny30o';
#        $lang = "nl";
        $view = $args['view'];
        $view->addHelperPath(GEOLOCATION_PLUGIN_DIR . '/helpers', 'Geolocation_View_Helper_');
        $request = Zend_Controller_Front::getInstance()->getRequest();
        $module = $request->getModuleName();
        $controller = $request->getControllerName();
        $action = $request->getActionName();        
        if ( ($module == 'geolocation' && $controller == 'map')
                    || ($module == 'contribution' 
                        && $controller == 'contribution' 
                        && $action == 'contribute' 
                        && get_option('geolocation_add_map_to_contribution_form') == '1')
                     || ($controller == 'items') )  {
            queue_css_file('geolocation-items-map');
            queue_css_file('geolocation-marker');
#            queue_js_url("http://maps.google.com/maps/api/js?sensor=false");
#            queue_js_url("https://maps.googleapis.com/maps/api/js?sensor=false&libraries=places&key=$key&language=$lang");
            queue_js_file('map');
        }
    }
        
    public function hookInstall()
    {
        $db = get_db();
        $sql = "
        CREATE TABLE IF NOT EXISTS $db->Location (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
        `item_id` BIGINT UNSIGNED NOT NULL ,
        `latitude` DOUBLE NOT NULL ,
        `longitude` DOUBLE NOT NULL ,
        `zoom_level` INT NOT NULL ,
        `map_type` VARCHAR( 255 ) NOT NULL ,
        
        `point_of_interest` VARCHAR( 255 ) NULL ,
        `route` VARCHAR( 255 ) NULL ,
        `street_number` VARCHAR( 255 ) NULL ,
        `sublocality` VARCHAR( 255 ) NULL ,
        `locality` VARCHAR( 255 ) NULL ,
        `administrative_area_level_1` VARCHAR( 255 ) NULL ,
        `administrative_area_level_2` VARCHAR( 255 ) NULL ,
        `administrative_area_level_3` VARCHAR( 255 ) NULL ,
        `natural_feature` VARCHAR( 255 ) NULL ,
        `establishment` VARCHAR( 255 ) NULL ,
        `postal_code` VARCHAR( 255 ) NULL ,
        `postal_code_prefix` VARCHAR( 255 ) NULL ,
        `country` VARCHAR( 255 ) NOT NULL ,
        `continent` VARCHAR( 255 ) NULL ,
        `planetary_body` VARCHAR( 255 ) NULL ,

        `address` TEXT NOT NULL ,
        INDEX (`item_id`)) ENGINE = MYISAM";
        $db->query($sql);
        
        set_option('geolocation_default_latitude', '38');
        set_option('geolocation_default_longitude', '-77');
        set_option('geolocation_default_zoom_level', '5');
        set_option('geolocation_per_page', GEOLOCATION_DEFAULT_LOCATIONS_PER_PAGE);
        set_option('geolocation_add_map_to_contribution_form', '1');   
        set_option('geolocation_use_metric_distances', '1'); 
        
        set_option('geolocation_gmaps_key', "AIzaSyD6zj4P4YxltcYJZsRVUvTqG_bT1nny30o");
        
        set_option('geolocation_public_search_fields', implode(",",$this->_all_geo_fields));
    }
    
    public function hookUninstall()
    {
        // Delete the plugin options
        delete_option('geolocation_default_latitude');
        delete_option('geolocation_default_longitude');
        delete_option('geolocation_default_zoom_level');
        delete_option('geolocation_per_page');
        delete_option('geolocation_add_map_to_contribution_form');
        delete_option('geolocation_use_metric_distances');
        // This is for older versions of Geolocation, which used to store a Google Map API key.
        delete_option('geolocation_gmaps_key');
        delete_option('geolocation_public_search_fields');
        
        // Drop the Location table
        $db = get_db();
        $db->query("DROP TABLE $db->Location");        
    }
    
    public function hookConfigForm()
    {
        #update the available search fields
        if (get_option("geolocation_public_search_fields") == ""){set_option("geolocation_public_search_fields", implode("\n",$this->_all_geo_fields));}
        include 'config_form.php';        
    }
    
    public function hookConfig($args)
    {
        // Use the form to set a bunch of default options in the db
        set_option('geolocation_default_latitude', $_POST['default_latitude']);
        set_option('geolocation_default_longitude', $_POST['default_longitude']);
        set_option('geolocation_default_zoom_level', $_POST['default_zoomlevel']);
        set_option('geolocation_item_map_width', $_POST['item_map_width']);
        set_option('geolocation_item_map_height', $_POST['item_map_height']);
        set_option('geolocation_gmaps_key', $_POST['geolocation_gmaps_key']);
        $perPage = (int)$_POST['per_page'];
        if ($perPage <= 0) {
            $perPage = GEOLOCATION_DEFAULT_LOCATIONS_PER_PAGE;
        } else if ($perPage > GEOLOCATION_MAX_LOCATIONS_PER_PAGE) {
            $perPage = GEOLOCATION_MAX_LOCATIONS_PER_PAGE;
        }
        set_option('geolocation_per_page', $perPage);
        set_option('geolocation_add_map_to_contribution_form', $_POST['geolocation_add_map_to_contribution_form']);
        set_option('geolocation_link_to_nav', $_POST['geolocation_link_to_nav']);        
        set_option('geolocation_public_search_fields', $_POST['geolocation_public_search_fields']);
        set_option('geolocation_use_metric_distances', $_POST['geolocation_use_metric_distances']); 
    }
    
    public function hookDefineAcl($args)
    {   
        $acl = $args['acl'];
        $acl->allow(null, 'Items', 'modifyPerPage');
        $acl->addResource('Locations');
        $acl->allow(null, 'Locations');
    }
    
    public function hookDefineRoutes($args)
    {
        $router = $args['router'];
        $mapRoute = new Zend_Controller_Router_Route('items/map/:page',
                        array('controller' => 'map',
                                'action'     => 'browse',
                                'module'     => 'geolocation',
                                'page'       => '1'),
                        array('page' => '\d+'));
        $router->addRoute('items_map', $mapRoute);
        
        // Trying to make the route look like a KML file so google will eat it.
        // @todo Include page parameter if this works.
        $kmlRoute = new Zend_Controller_Router_Route_Regex('geolocation/map\.kml',
                        array('controller' => 'map',
                                'action' => 'browse',
                                'module' => 'geolocation',
                                'output' => 'kml'));
        $router->addRoute('map_kml', $kmlRoute);        
    }
    
    public function hookAfterSaveItem($args)
    {
        $post = $args['post'];
        $item = $args['record'];   

        // If we don't have the geolocation form on the page, don't do anything!
        if (!$post['geolocation']) {
            return;
        }
        
        // Find the location object for the item
        $location = $this->_db->getTable('Location')->findLocationByItem($item, true);
        
        // If we have filled out info for the geolocation, then submit to the db
        $geolocationPost = $post['geolocation'];
        if (!empty($geolocationPost) &&
                        (((string)$geolocationPost['latitude']) != '') &&
                        (((string)$geolocationPost['longitude']) != '')) {
            if (!$location) {
                $location = new Location;
                $location->item_id = $item->id;
            }
            $location->setPostData($geolocationPost);
            $location->save();
            // If the form is empty, then we want to delete whatever location is
            // currently stored
        } else {
            if ($location) {
                $location->delete();
            }
        }
    }
    
    public function hookAdminItemsShowSidebar($args)
    {
        $view = $args['view'];
        $item = $args['item'];
        $location = $this->_db->getTable('Location')->findLocationByItem($item, true);
        if ($location) {
            $data_list = $location->get_locationdata_for_public_viewing();
            $html = '';
            $html .= "<div id='geolocation' class='info-panel panel'>";
            $html .= $view->itemGoogleMap($item, '224px', '270px' );
#            $html .= $location->get_locationdata_for_public_viewing();   
            foreach($data_list as $loc_type => $loc_data){
                if ($loc_data){
                    $uri = url(array('module'=>'items','controller'=>'browse'), 'default', 
                                    array("search" => "",
                                        "submit_search" => "Zoeken",
                                        "collection" => 1,
                                        "geolocation-$loc_type" => "$loc_data"));
                    $html .= "<a href='" . $uri . "'>".$loc_data."</a><br>";
                }
            }         
            $html .= "</div>";
            echo $html;
        }        
    }
    
    public function hookPublicHead($args){
#        $key = get_option('geolocation_gmaps_key');// ? get_option('geolocation_gmaps_key') : 'AIzaSyD6zj4P4YxltcYJZsRVUvTqG_bT1nny30o';
#        $lang = "nl";
        $view = $args['view'];
        $view->addHelperPath(GEOLOCATION_PLUGIN_DIR . '/helpers', 'Geolocation_View_Helper_');
        $request = Zend_Controller_Front::getInstance()->getRequest();
        $module = $request->getModuleName();
        $controller = $request->getControllerName();
        $action = $request->getActionName();
        if ( ($module == 'geolocation' && $controller == 'map')
                        || ($module == 'contribution' 
                            && $controller == 'contribution' 
                            && $action == 'contribute' 
                            && get_option('geolocation_add_map_to_contribution_form') == '1')
                         || ($controller == 'items') )  {
            queue_css_file('geolocation-items-map');
            queue_css_file('geolocation-marker');
#            queue_js_url("http://maps.google.com/maps/api/js?sensor=false");
#            queue_js_url("https://maps.googleapis.com/maps/api/js?sensor=false&libraries=places&key=$key&language=$lang");
            queue_js_file('map');
        }        
    }
    
    public function hookPublicItemsShow($args)
    {
        $view = $args['view'];
        $item = $args['item'];
        $location = $this->_db->getTable('Location')->findLocationByItem($item, true);

        if ($location) {
            $data_list = $location->get_locationdata_for_public_viewing();
            $width = get_option('geolocation_item_map_width') ? get_option('geolocation_item_map_width') : '100%';
            $height = get_option('geolocation_item_map_height') ? get_option('geolocation_item_map_height') : '300px';            
            $html = "<div id='geolocation'>";
            $html .= '<h2>' . __("Place of narration") . '</h2>';
            $html .= $view->itemGoogleMap($item, $width, $height);
            foreach($data_list as $loc_type => $loc_data){
                if ($loc_data){
                    $uri = url(array('module'=>'items','controller'=>'browse'), 'default', 
                                    array("search" => "",
                                        "submit_search" => "Zoeken",
                                        "collection" => 1,
                                        "geolocation-$loc_type" => $loc_data));
                    $html .= "<a href='" . $uri . "'>".$loc_data."</a><br>";
                }
            }
            $html .= "</div>";
            echo $html;
        }
    }
    
    public function hookAdminItemsSearch($args)
    {
        // Get the request object
        $request = Zend_Controller_Front::getInstance()->getRequest();
        $view = $args['view'];
        if ($request->getControllerName() == 'map' && $request->getActionName() == 'browse') {
            echo $view->partial('advanced-search-partial.php', array('searchFormId'=>'search', 'searchButtonId'=>'submit_search_advanced'));
        } else if ($request->getControllerName() == 'items' && $request->getActionName() == 'advanced-search') {
            echo $view->partial('advanced-search-partial.php', array('searchFormId'=>'advanced-search-form', 'searchButtonId'=>'submit_search_advanced'));
        }
    }
    
    public function hookPublicItemsSearch($args)
    {
        $view = $args['view'];                
        echo $view->partial('advanced-search-partial.php', array('searchFormId'=>'advanced-search-form', 'searchButtonId'=>'submit_search_advanced'));
    }
    
    public function hookItemsBrowseSql($args)
    {
        $db = $this->_db;
        $select = $args['select'];
        $alias = $this->_db->getTable('Location')->getTableAlias();
        $specific_geo_search = False;
        // go through all searchable fields
        foreach(explode("\n", get_option("geolocation_public_search_fields")) as $geo_field){
            $geo_field = trim($geo_field);
            if(isset($args['params']['geolocation-'.$geo_field])) {
                $search_value = trim($args['params']['geolocation-'.$geo_field]);
                if ( $search_value != ''){
                    //fire only once
                    if ($specific_geo_search == False){
                        $select->joinInner(array($alias => $db->Location), "$alias.item_id = items.id", array('latitude', 'longitude', 'address')); 
                    }
                        $field_type = trim($args['params']["geolocation-".$geo_field]);
                        $select->where($geo_field . ' LIKE "%' . $field_type . '%"');
                        $specific_geo_search = True;
                }
            }
        }
        //fires when a specific place is searched for
        if($specific_geo_search){ 
            $select->order('id');
        }
        else if(isset($args['params']['geolocation-address'])) {
            
            // Get the address, latitude, longitude, and the radius from parameters
            $address = trim($args['params']['geolocation-address']);
            $currentLat = trim($args['params']['geolocation-latitude']);
            $currentLng = trim($args['params']['geolocation-longitude']);
            $radius = trim($args['params']['geolocation-radius']);
          
            if ( (isset($args['params']['only_map_items']) && $args['params']['only_map_items'] ) || $address != '') {
                //INNER JOIN the locations table
                $select->joinInner(array($alias => $db->Location), "$alias.item_id = items.id", array('latitude', 'longitude', 'address'));                    
            }
            // Limit items to those that exist within a geographic radius if an address and radius are provided
            if ($address != '' && is_numeric($currentLat) && is_numeric($currentLng) && is_numeric($radius)) {
                // SELECT distance based upon haversine forumula
                $select->columns('3956 * 2 * ASIN(SQRT(  POWER(SIN(('.$currentLat.' - locations.latitude) * pi()/180 / 2), 2) + COS('.$currentLat.' * pi()/180) *  COS(locations.latitude * pi()/180) *  POWER(SIN(('.$currentLng.' -locations.longitude) * pi()/180 / 2), 2)  )) as distance');
                // WHERE the distance is within radius miles/kilometers of the specified lat & long
                if (get_option('geolocation_use_metric_distances')) {
                    $denominator = 111;
                } else {
                    $denominator = 69;
                }
                $select->where('(latitude BETWEEN '.$currentLat.' - ' . $radius . '/'.$denominator.' AND ' . $currentLat . ' + ' . $radius .  '/'.$denominator.')
             AND (longitude BETWEEN ' . $currentLng . ' - ' . $radius . '/'.$denominator.' AND ' . $currentLng  . ' + ' . $radius .  '/'.$denominator.')');
                //ORDER by the closest distances
                $select->order('distance');
            }
        } else if( isset($args['params']['only_map_items'])) {
            $select->joinInner(array($alias => $db->Location), "$alias.item_id = items.id", array());
        }
    }
    
    public function filterAdminNavigationMain($navArray)
    {
        $navArray['Geolocation'] = array('label'=>'Vertelplaatsen', 'uri'=>url('geolocation/map/browse'));
        return $navArray;        
    }
    
    public function filterResponseContexts($contexts)
    {
        $contexts['kml'] = array('suffix'  => 'kml',
                'headers' => array('Content-Type' => 'text/xml'));
        return $contexts;        
    }
    
    public function filterActionContexts($contexts, $args)
    {
        $controller = $args['controller'];
        if ($controller instanceof Geolocation_MapController) {
            $contexts['browse'] = array('kml');
        }
        return $contexts;        
    }
    
    public function filterAdminItemsFormTabs($tabs, $args)
    {
        // insert the map tab before the Miscellaneous tab
        $item = $args['item'];
        $tabs['Vertelplaats'] = $this->_mapForm($item);
        
        return $tabs;     
    }
    
    /**
     * Return HTML for a link to the same search visualized on a map
     *
     * @return string
     */
    function link_to_map_search()
    {
        $uri = 'geolocation/map/browse';
        $props = $uri . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
        return $props;
    }

    /**
     * Return HTML for a link to the same search visualized on a map
     *
     * @return string
     */
    function link_to_list_search()
    {
        $uri = 'items/browse';
        $props = $uri . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
        return $props;
    }

    
    public function filterPublicNavigationItems($navArray){
#        if (get_option('geolocation_see_results_on_map')) {
#        print "<pre>MAP SEARCH: " . $this->link_to_map_search()."</pre>";
        $navArray['Search results'] = array(
                                        'label'=>__('Resultaten lijst'),
                                        'uri' => url($this->link_to_list_search())
                                        );

        $navArray['Results on map'] = array(
                                        'label'=>__('Results on map'),
                                        'uri' => url($this->link_to_map_search())
                                        );
#        }
        if (get_option('geolocation_link_to_nav')) {
            $navArray['Browse Map'] = array(
                                            'label'=>__('Browse Map'),
                                            'uri' => url('items/map') 
                                            );
        }
        return $navArray;        
    }
    
    protected function _autocomplete_js_code(){
        return "
        <script type=\"text/javascript\">
            if (google){
                var options = {
            	    types: []
                };
            	var input = document.getElementById('geolocation-address');
            	var autocomplete = new google.maps.places.Autocomplete(input, options);

                jQuery(document).ready(function() {
            	    jQuery('#<?php echo $searchButtonId; ?>').click(function(event) {

            	        // Find the geolocation for the address
            	        var address = jQuery('#geolocation-address').val();
                        if (jQuery.trim(address).length > 0) {
                            var geocoder = new google.maps.Geocoder();	        
                            geocoder.geocode({'address': address}, function(results, status) {
                                // If the point was found, then put the marker on that spot
                        		if (status == google.maps.GeocoderStatus.OK) {
                        			var gLatLng = results[0].geometry.location;
                        	        // Set the latitude and longitude hidden inputs
                        	        jQuery('#geolocation-latitude').val(gLatLng.lat());
                        	        jQuery('#geolocation-longitude').val(gLatLng.lng());
                                    jQuery('#<?php echo $searchFormId; ?>').submit();
                        		} else {
                        		  	// If no point was found, give us an alert
                        		    alert('Error: \"' + address + '\" was not found!');
                        		}
                            });

                            event.stopImmediatePropagation();
                	        return false;
                        }                
            	    });
                });
            };
        </script>";
    }
    
    /**
     * Returns the form code for geographically searching for items
     * @param Item $item
     * @param int $width
     * @param int $height
     * @return string
     **/    
    protected function _mapForm($item, $label = 'Find a Location by Address:', $confirmLocationChange = true,  $post = null)
    {
        $html = '';
        
        $center = $this->_getCenter();
        $center['show'] = false;
        
        $location = $this->_db->getTable('Location')->findLocationByItem($item, true);
                
        if ($post === null) {
            $post = $_POST;
        }
        
        $usePost = !empty($post) && !empty($post['geolocation']);
        if ($usePost) {
            $lng  = (double) @$post['geolocation']['longitude'];
            $lat  = (double) @$post['geolocation']['latitude'];
            $zoom = (int) @$post['geolocation']['zoom_level'];
            $addr = @$post['geolocation']['address'];
	        $planetary_body = @$post['geolocation']['planetary_body'];
	        $continent = @$post['geolocation']['continent'];
	        $country = @$post['geolocation']['country'];
	        $administrative_area_level_1 = @$post['geolocation']['administrative_area_level_1'];
	        $administrative_area_level_2 = @$post['geolocation']['administrative_area_level_2'];
	        $locality = @$post['geolocation']['locality'];
	        $natural_feature = @$post['geolocation']['natural_feature'];
	        $sublocality = @$post['geolocation']['sublocality'];
	        $route = @$post['geolocation']['route'];
	        $point_of_interest = @$post['geolocation']['point_of_interest'];
	        $establishment = @$post['geolocation']['establishment'];
	        $street_number = @$post['geolocation']['street_number'];
	        $postal_code = @$post['geolocation']['postal_code'];
	        $postal_code_prefix = @$post['geolocation']['postal_code_prefix'];
        } else {
            if ($location) {
                $lng  = (double) $location['longitude'];
                $lat  = (double) $location['latitude'];
                $zoom = (int) $location['zoom_level'];
                $addr = $location['address'];
		        $planetary_body = $location['planetary_body'];
		        $continent = $location['continent'];
		        $country = $location['country'];
		        $administrative_area_level_1 = $location['administrative_area_level_1'];
		        $administrative_area_level_2 = $location['administrative_area_level_2'];
		        $locality = $location['locality'];
		        $natural_feature = $location['natural_feature'];
		        $sublocality = $location['sublocality'];
		        $route = $location['route'];
		        $point_of_interest = $location['point_of_interest'];
		        $establishment = $location['establishment'];
		        $street_number = $location['street_number'];
		        $postal_code = $location['postal_code'];
		        $postal_code_prefix = $location['postal_code_prefix'];
            } else {
                $lng = $lat = $zoom = $addr = $planetary_body = $natural_feature = $continent = $country = $administrative_area_level_1 = $administrative_area_level_2 = $locality = $sublocality = $route = $point_of_interest = $establishment = $street_number = $postal_code = $postal_code_prefix = '';
            }
        }
        
        $html .= '<div class="field">';
        $html .=     '<div id="location_form" class="two columns alpha">';
        $html .=         '<label>' . html_escape($label) . '</label>';
        $html .=     '</div>';
        $html .=     '<div class="inputs five columns omega">';
        $html .=          '<div class="input-block">';
        $html .=            '<input type="text" name="geolocation[address]" id="geolocation_address" value="' . $addr . '" class="textinput" onKeypress="resetTextareas();"/>';
        $html .=            '<button type="button" style="margin-bottom: 18px; float:none;" name="geolocation_find_location_by_address" id="geolocation_find_location_by_address">Find</button>';        
        $html .=          '</div>';
        $html .=     '</div>';
        $html .= '</div>';
        $html .= '<div  id="omeka-map-form" style="width: 100%; height: 300px"></div>';
        
        #site for auto filled geo location information:
        $html .=      '<div class="input-block">';
        $html .=         '<label>' . html_escape("Latitude") . '</label>';
        $html .=         '<input name="geolocation[latitude]" value="' . $lat . '" class="coordinput"/>';
        $html .=         '<label>' . html_escape("Longitude") . '</label>';
        $html .=         '<input name="geolocation[longitude]" value="' . $lng . '" class="coordinput"/>';
        $html .=         '<label>' . html_escape("Zoom") . '</label>';
        $html .=         '<input name="geolocation[zoom_level]" value="' . $zoom . '" size="2" class="coordinput"/>';
        $html .=         '<input type="hidden" name="geolocation[map_type]" value="Google Maps v' . GOOGLE_MAPS_API_VERSION . '" /><br>';
        $html .=     '</div>';

        $html .= '<div class="field">';
        $html .=     '<div id="location_form" class="two columns alpha">';
        $html .=         '<label>Address</label>';
        $html .=         '<label>Streetnumber</label>';
    	$html .=         '<label>Postal code</label>';
    	$html .=         '<label>Postal code prefix</label>';
    	$html .=         '<label>Sublocality</label>';
    	$html .=         '<label>Place</label>';
    	$html .=         '<label>Natural feature</label>';
    	$html .=         '<label>Establishment</label>';
    	$html .=         '<label>County (adm2)</label>';
    	$html .=         '<label>Province (adm1)</label>';
    	$html .=         '<label>Country</label>';
    	$html .=         '<label>Planetary body</label>';
        $html .=     '</div>';
        $html .=     '<div class="inputs five columns omega">';
        $html .=       '<div class="input-block">';
        $html .=         '<input type="text" name="geolocation[route]" id="route" rows="1" size="20" value="'.$route.'" class="geotextinput"/>';
        $html .=         '<input type="text" name="geolocation[street_number]" id="street_number" rows="1" size="4" value="'.$street_number.'" class="geotextinput"/><br>';
    	$html .=         '<input type="text" name="geolocation[postal_code]" id="postal_code" rows="1" size="12" value="'.$postal_code.'" class="geotextinput"/>';
    	$html .=         '<input type="text" name="geolocation[postal_code_prefix]" id="postal_code_prefix" rows="1" size="12" value="'.$postal_code_prefix.'" class="geotextinput"/><br>';
        $html .=         '<input type="text" name="geolocation[sublocality]" id="sublocality" rows="1" size="28" value="'.$sublocality.'" class="geotextinput"/><br>';
        $html .=         '<input type="text" name="geolocation[locality]" id="locality" rows="1" size="28" value="'.$locality.'" class="geotextinput"/><br>';
        $html .=         '<input type="text" name="geolocation[natural_feature]" id="natural_feature" rows="1" size="28" value="'.$natural_feature.'" class="geotextinput"/><br>';
        $html .=         '<input type="text" name="geolocation[establishment]" id="establishment" rows="1" size="28" value="'.$establishment.'" class="geotextinput"/><br>';
        $html .=         '<input type="text" name="geolocation[administrative_area_level_2]" id="administrative_area_level_2" rows="1" size="28" value="'.$administrative_area_level_2.'" class="geotextinput"/><br>';
        $html .=         '<input type="text" name="geolocation[administrative_area_level_1]" id="administrative_area_level_1" rows="1" size="28" value="'.$administrative_area_level_1.'" class="geotextinput"/><br>';
        $html .=         '<input type="text" name="geolocation[country]" id="country" rows="1" size="28" value="'.$country.'" class="geotextinput"/><br>';
        $html .=         '<input type="text" name="geolocation[planetary_body]" id="planetary_body" size="28" value="'.$planetary_body.'" class="geotextinput"/><br>';
        $html .=       '</div>';
        $html .=    '</div>';
        $html .= '</div>';
        $options = array();
        $options['form'] = array('id' => 'location_form',
                'posted' => $usePost);
        if ($location or $usePost) {
            $options['point'] = array('latitude' => $lat,
                    'longitude' => $lng,
                    'zoomLevel' => $zoom);
        }
        
        $options['confirmLocationChange'] = $confirmLocationChange;
        
        $center = js_escape($center);
        $options = js_escape($options);        

        $js = "var anOmekaMapForm = new OmekaMapForm(" . js_escape('omeka-map-form') . ", $center, $options);";
        $js .= "
            jQuery(document).bind('omeka:tabselected', function () {
                anOmekaMapForm.resize();
            });                        
        ";
        
        $html .= "<script type='text/javascript'>" . $js . "</script>";
        return $html;
    }
    
    protected function _getCenter()
    {
        return array(
                'latitude'=>  (double) get_option('geolocation_default_latitude'),
                'longitude'=> (double) get_option('geolocation_default_longitude'),
                'zoomLevel'=> (double) get_option('geolocation_default_zoom_level'));        
        
    }
    
    /**
     * Register the geolocations API resource.
     * 
     * @param array $apiResources
     * @return array
     */
    public function filterApiResources($apiResources)
    {
        $apiResources['geolocations'] = array(
            'record_type' => 'Location',
            'actions' => array('get', 'index', 'post', 'put', 'delete'), 
        );
        return $apiResources;
    }
    
    /**
     * Add geolocations to item API representations.
     * 
     * @param array $extend
     * @param array $args
     * @return array
     */
    public function filterApiExtendItems($extend, $args)
    {
        $item = $args['record'];
        $location[] = $this->_db->getTable('Location')->findLocationByItem($item, true);
        if (!$location) {
            return $extend;
        }
        $locationId = $location[0]['id'];
#        print_r($locationId);
#        print Omeka_Record_Api_AbstractRecordAdapter::getResourceUrl("/geolocations/$locationId");
        $extend['geolocations'] = array(
            'id' => "1", 
            'url' => "bla", 
            'resource' => 'geolocations',
        );
/*
        $extend['geolocations'] = array(
            'id' => $locationId, 
            'url' => Omeka_Record_Api_AbstractRecordAdapter::getResourceUrl("/geolocations/$locationId"), 
            'resource' => 'geolocations',
        );*/
        return $extend;
    }
    
}