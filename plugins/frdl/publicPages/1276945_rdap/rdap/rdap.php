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

require_once __DIR__ . '/../../../../../includes/oidplus.inc.php';
 


OIDplus::init(true);
set_exception_handler(array('OIDplusGui', 'html_exception_handler'));

if (OIDplus::baseConfig()->getValue('DISABLE_PLUGIN_OIDplusPagePublicRdap', false)) {
	throw new OIDplusException(_L('This plugin was disabled by the system administrator!'));
}

$rdapBaseUri = OIDplus::baseConfig()->getValue('RDAP_BASE_URI', OIDplus::webpath() );
$useCache = OIDplus::baseConfig()->getValue('RDAP_CACHE_ENABLED', true );
$rdapCacheDir = OIDplus::baseConfig()->getValue('CACHE_DIRECTORY_OIDplusPagePublicRdap', \sys_get_temp_dir().\DIRECTORY_SEPARATOR );
$rdapCacheExpires = OIDplus::baseConfig()->getValue('CACHE_EXPIRES_OIDplusPagePublicRdap', 60  * 3 );

if (\PHP_SAPI == 'cli') {
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

$tokens = explode('$', $query);
$query = array_shift($tokens);

$query = str_replace('oid:.', 'oid:', $query); 
$n = explode(':', $query);
if(2>count($n)){
 array_unshift($n, 'oid');	
 $query = 'oid:'.$query;	
}
$ns = $n[0];


if(true === $useCache){
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
}else{
  $cacheFile = false;	
}

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
}


$out = [];


	try {
		$obj = OIDplusObject::findFitting($query);
		if (!$obj) $obj = OIDplusObject::parse($query);  
		$query = $obj->nodeId();
	} catch (Exception $e) {
		$obj = null;
	}

if(null === $obj){
	$out['error'] = 'Not found';
	if(true === $useCache){
    	___rdap_write_cache($out, $cacheFile);
	}
	___rdap_out($out);
}

$res = OIDplus::db()->query("select * from ###objects where id = ?", [$query]);
$data = $res ? $res->fetch_object() : null;
if(null === $data){
	$out['error'] = 'Not found';
	if(true === $useCache){
	    ___rdap_write_cache($out, $cacheFile); 
	}
	___rdap_out($out);
}

$obj = OIDplusObject::parse($data->id);


$whois_server = '';
if (OIDplus::config()->getValue('individual_whois_server', '') != '') {
	$whois_server = OIDplus::config()->getValue('individual_whois_server', '');
}
else if (OIDplus::config()->getValue('vts_whois', '') != '') {
	$whois_server = OIDplus::config()->getValue('vts_whois', '');
}
if (!empty($whois_server)) {
	list($whois_host, $whois_port) = explode(':',"$whois_server:43");  
	if ($whois_port === '43') $out['port43'] = $whois_host;
}


    $parentHandle=$obj->one_up();

 
$out['name'] = $obj->nodeId(true);
$out['objectClassName'] = $ns;
$out['handle'] = $ns.':'.$n[1];
$out['parentHandle'] =   (null !== $parentHandle && is_callable([$parentHandle, 'nodeId']) )
	              ? $obj->one_up()->nodeId(true)
	              : null;

$out['rdapConformance'] = [
        "rdap_level_0", //https://datatracker.ietf.org/doc/html/rfc9083
];
$out['links'] = [
          [
            "href"=> 'https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'],
            "type"=> "application/rdap+json",
            "title"=> sprintf("Information about the %s %s", $ns, $n[1]), 
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
	
    ];
$out['remarks'] = [
                 [
            "title"=>"Availability",
            "description"=> [
                sprintf("The %s %s is known.", strtoupper($ns), $n[1]),
            ],
            "links"=> []
        ],
  [
            "title"=>"Description", 
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
	  
        if(isset($out['oidplus_oidip']->oidip->objectSection->created)){
    	 $out['oidplus_oidip']->oidip->objectSection->created
			 = implode('T', explode(' ', $out['oidplus_oidip']->oidip->objectSection->created, 2)).'Z';								
	}
	if(isset($out['oidplus_oidip']->oidip->objectSection->updated)){
    	 $out['oidplus_oidip']->oidip->objectSection->updated
			 = implode('T', explode(' ',  $out['oidplus_oidip']->oidip->objectSection->updated, 2)).'Z';								
	}
	  
	if(isset($out['oidplus_oidip']->oidip->raSection->created)){
    	 $out['oidplus_oidip']->oidip->raSection->created
			 = implode('T', explode(' ', $out['oidplus_oidip']->oidip->raSection->created, 2)).'Z';								
	}
	if(isset($out['oidplus_oidip']->oidip->raSection->updated)){
    	 $out['oidplus_oidip']->oidip->raSection->updated
			 = implode('T', explode(' ',  $out['oidplus_oidip']->oidip->raSection->updated, 2)).'Z';								
	}	  	  
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
];

 
if(true === $useCache){
  ___rdap_write_cache($out, $cacheFile);
}
___rdap_out($out);

 
function ___rdap_write_cache($out, $cacheFile){
 if(!is_string($cacheFile)){
   return;	 
 }
 $exp = var_export($out, true);
 $code = <<<PHPCODE
<?php
 return $exp; 
PHPCODE;

	file_put_contents($cacheFile, $code);	
	touch($cacheFile);
}

function ___rdap_read_cache($cacheFile, $rdapCacheExpires){
 if(is_string($cacheFile) && file_exists($cacheFile) && filemtime($cacheFile) >= time() - $rdapCacheExpires ){
	 $out = include $cacheFile;
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
