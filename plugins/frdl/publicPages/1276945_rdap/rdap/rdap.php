<?php

/*
 * OIDplus 2.0 RDAP
 * Copyright 2019 - 2022 Daniel Marschall, ViaThinkSoft
 * Authors               Daniel Marschall, ViaThinkSoft
 *                       Till Wehowski, Frdlweb
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
//ini_set('display_errors', 'on');

require_once __DIR__ . '/../../../../../includes/oidplus.inc.php';
 

//https://data.registry.frdl.de/plugins/frdl/publicPages/1276945_rdap/rdap/rdap.php?query=1.3.6.1.4.1.37553.8.6&meta=source
if(isset($_GET['meta']) && 'source' === $_GET['meta'] && 'data.registry.frdl.de' === $_SERVER['SERVER_NAME']){
	highlight_file(__FILE__);
	exit;
}

if(OIDplus::baseConfig()->getValue('AUTOUPDATE_PLUGIN_OIDplusPagePublicRdap', true)
   && 'data.registry.frdl.de' !== $_SERVER['SERVER_NAME'] 
   && filemtime(__FILE__) < time() - 86400
   && !in_array(__FILE__, get_included_files())
   && !isset($_GET['meta'])
  ){
	file_put_contents(__FILE__,
		file_get_contents('https://data.registry.frdl.de/plugins/frdl/publicPages/1276945_rdap/rdap/rdap.php?'
						  .'query=1.3.6.1.4.1.37553.8.6&meta=source'));
//	return require __FILE__;
}


OIDplus::init(true);
set_exception_handler(array('OIDplusGui', 'html_exception_handler'));

if (OIDplus::baseConfig()->getValue('DISABLE_PLUGIN_OIDplusPagePublicRdap', false)) {
	throw new OIDplusException(_L('This plugin was disabled by the system administrator!'));
}



$rdapBaseUri = OIDplus::baseConfig()->getValue('RDAP_BASE_URI', OIDplus::webpath() );
$rdapCacheDir = OIDplus::baseConfig()->getValue('CACHE_DIRECTORY_OIDplusPagePublicRdap', \sys_get_temp_dir().\DIRECTORY_SEPARATOR );
$rdapCacheExpires = OIDplus::baseConfig()->getValue('CACHE_EXPIRES_OIDplusPagePublicRdap', 60  * 3 );




// Step 0: Get request parameter

if (PHP_SAPI == 'cli') {
	if ($_SERVER['argc'] != 2) {
		echo _L('Syntax').': '.$_SERVER['argv'][0].' <query>'."\n";
		exit(2);
	}
	$query = $_SERVER['argv'][1];
} else {
	if (!isset($_REQUEST['query'])) {
		http_response_code(400);
		die('<h1>'._L('Error').'</h1><p>'._L('Argument "%1" is missing','query').'<p>');
	}
	$query = $_REQUEST['query'];
}

// Split input into query, authTokens and serverCommands
$tokens = explode('$', $query);
$query = array_shift($tokens);

$query = str_replace('oid:.', 'oid:', $query); // allow leading dot
$n = explode(':', $query);
if(2>count($n)){
 array_unshift($n, 'oid');	
 $query = 'oid:'.$query;	
}
$ns = $n[0];


$cacheFile = $rdapCacheDir. 'oidplus-rdap-'
	.sha1(\get_current_user()
		  . $rdapBaseUri.__FILE__.$query
		  .OIDplus::baseConfig()->getValue('SERVER_SECRET', sha1(__FILE__.\get_current_user()) ) 
		 )
	.'.'
	.strlen( $rdapBaseUri.$query )
	.'.php'
	;

___rdap_read_cache($cacheFile, $rdapCacheExpires);



if(class_exists(\OIDplusPagePublicAltIds::class)){
 $res = OIDplus::db()->query("select * from ###alt_ids where alt = ? AND ns = ?", [$n[1], $ns]);
 $alt = $res ? $res->fetch_object() : null;
 if(null !== $alt){
	$query = $alt->id;
	$n = explode(':', $query);
   if(2>count($n)){
       array_unshift($n, 'oid');	
       $query = 'oid:'.$query;	
   }
   $ns = $n[0];
 }
}//AltIds Plugin


$out = [];


	try {
		$obj = OIDplusObject::findFitting($query);
		if (!$obj) $obj = OIDplusObject::parse($query); // in case we didn't find anything fitting, we take it as it is and later use getParent() to find something else
		$query = $obj->nodeId();
	} catch (Exception $e) {
		$obj = null;
	}

if(null === $obj){
	$out['error'] = 'Not found';
	___rdap_write_cache($out, $cacheFile);
	___rdap_out($out);
}

$res = OIDplus::db()->query("select * from ###objects where id = ?", [$query]);
$data = $res ? $res->fetch_object() : null;
if(null === $data){
	$out['error'] = 'Not found';
	___rdap_write_cache($out, $cacheFile);
	___rdap_out($out);
}

$obj = OIDplusObject::parse($data->id);



		//	if ($obj->isConfidential()) { // yes, we use isConfidential() instead of allowObjectView()!
				//$out[] = 'attribute: confidential'; // DO NOT TRANSLATE!
		//	}



$whois_server = '';
if (OIDplus::config()->getValue('individual_whois_server', '') != '') {
	$whois_server = OIDplus::config()->getValue('individual_whois_server', '');
}
else if (OIDplus::config()->getValue('vts_whois', '') != '') {
	// This config setting is set by the "Registration" plugin
	$whois_server = OIDplus::config()->getValue('vts_whois', '');
}
if (!empty($whois_server)) {
	list($whois_host, $whois_port) = explode(':',"$whois_server:43"); // Split $whois_server into host and port; set port to 43 if it does not exist
	if ($whois_port === '43') $out['port43'] = $whois_host;
}

//$out['ldhName'] = $obj->nodeId(false);
$out['name'] = $obj->nodeId(true);
$out['objectClassName'] = $ns;
$out['handle'] = $ns.':'.$n[1];
$out['parentHandle'] = $obj->one_up()->nodeId(true);

$out['rdapConformance'] = [
        "rdap_level_0", //https://datatracker.ietf.org/doc/html/rfc9083
      //  "oidplus_level_2",
     //   "frdlweb_level_2"
    ];
$out['links'] = [
          [
            "href"=> 'https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'],
            "type"=> "application/rdap+json",
            "title"=> sprintf("Information about the %s %s", $ns, $n[1]),
         //   "value"=> 'https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'],
			"value"=>  $rdapBaseUri.$ns.'/'.$n[1],  
            "rel"=> "self"
         ],
	     [
            "href"=> OIDplus::webpath()."?goto=".urlencode($query),
            "type"=> "text/html",
            "title"=> sprintf("Information about the %s %s in the online repository", $ns, $n[1]),
            "value"=> OIDplus::webpath()."?goto=".urlencode($query),
            "rel"=> "alternate"
         ]
	
    ];//OIDplus::webpath()."?goto=oid:".$this->raHasFreeWeid($email, true)
$out['remarks'] = [
                 [
            "title"=>"Availability",
					 /*
             //"type"=> "remark",
		  	 "type"=> "result set truncated due to unexplainable reasons",
				//There are "remarks" in the examples https://datatracker.ietf.org/doc/html/rfc9083#section-10.2.1
				//showing only the description field???
					 */
            "description"=> [
                sprintf("The %s %s is known.", strtoupper($ns), $n[1]),
            ],
            "links"=> []
        ],
  [
            "title"=>"Description",
     //       "type"=> "remark",
            "description"=> [
               ($obj->isConfidential()) ? 'REDACTED FOR PRIVACY' : $data->description,
            ],
            "links"=> [	    
				[          
					"href"=> OIDplus::webpath()."?goto=".urlencode($query),           
					"type"=> "text/html",           
					"title"=> sprintf("Information about the %s %s in the online repository", $ns, $n[1]),          
					"value"=> OIDplus::webpath()."?goto=".urlencode($query),
					"rel"=> "alternate"      
				]			
			]     
  ],
	
	///plugins/viathinksoft/publicPages/100_whois/whois/webwhois.php?query=oid%3A1.3.6.1.4.1.37553.8.6
	
	
    ];


  $oidIPUrl =  OIDplus::webpath().'plugins/viathinksoft/publicPages/100_whois/whois/webwhois.php?query='.urlencode($query);
  $oidIP = file_get_contents($oidIPUrl);
  if(false !== $oidIP){
 $out['remarks'][]= [
            "title"=>"OIDIP Result", 
            "description"=> [
                $oidIP,
            ],
            "links"=> [	    
				[          
					"href"=> $oidIPUrl,           
					"type"=> "text/plain",           
					"title"=> sprintf("OIDIP Result for the %s %s", $ns, $n[1]),          
					"value"=> $oidIPUrl,
					"rel"=> "alternate"      
				]			
			]     
  ];	  
  }

  $oidIPUrlJSON =  OIDplus::webpath().'plugins/viathinksoft/publicPages/100_whois/whois/webwhois.php?query='.urlencode($query).'$format=json';
  $oidIPJSON = file_get_contents($oidIPUrlJSON);
  if(false !== $oidIPJSON){
     $out['oidplus_oidip'] = json_decode($oidIPJSON);	  	  
  }

$out['notices']=[
	 [
         "title" => "Authentication Policy",
         "description" =>
         [
           "Access to sensitive data for users with proper credentials."
         ],
         "links" =>
         [
           [
			   //   /help query must be done by url rewrite?
               //    without url rewrite conformance is violated !?!
            // "value" => OIDplus::webpath()."?goto=oidplus%3Aresources%24OIDplus%2Fprivacy_documentation.html",
			 "value" => $rdapBaseUri."help",  
             "rel" => "alternate",
             "type" => "text/html",
             "href" => OIDplus::webpath()."?goto=oidplus%3Aresources%24OIDplus%2Fprivacy_documentation.html"
           ]
         ]
       ]
	
];

if($obj->isConfidential()){
 $out['remarks'][1]['type'] = "result set truncated due to authorization"; 	
}

$out['statuses']=[
	'active',
	//'locked',	
];

 
___rdap_write_cache($out, $cacheFile);
___rdap_out($out);

 
function ___rdap_write_cache($out, $cacheFile){
 $exp = var_export($out, true);
 $code = <<<PHPCODE
<?php
 return $exp; 
PHPCODE;

	file_put_contents($cacheFile, $code);	
	touch($cacheFile);
}

function ___rdap_read_cache($cacheFile, $rdapCacheExpires){
 if(file_exists($cacheFile) && filemtime($cacheFile) >= time() - $rdapCacheExpires ){
	 $out = require $cacheFile;
	 if(is_array($out) || is_object($out)){
	   ___rdap_out($out);
	 }
 }
}


function ___rdap_out($out){
	originHeaders();
	header('Content-Type: application/rdap+json');
	echo json_encode($out);
	exit;
}
