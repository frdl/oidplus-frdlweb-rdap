<?php

/*
 * OIDplus 2.0 RDAP
 * Copyright 2019 - 2022 Daniel Marschall, ViaThinkSoft
 * Author                Till Wehowski, Frdlweb
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

if (OIDplus::baseConfig()->getValue('DISABLE_PLUGIN_OIDplusPagePublicWhois', false)) {
	throw new OIDplusException(_L('This plugin was disabled by the system administrator!'));
}

originHeaders();

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
	___rdap_out($out);
}

$res = OIDplus::db()->query("select * from ###objects where id = ?", [$query]);
$data = $res ? $res->fetch_object() : null;
if(null === $data){
	$out['error'] = 'Not found';
	___rdap_out($out);
}

$obj = OIDplusObject::parse($data->id);



		//	if ($obj->isConfidential()) { // yes, we use isConfidential() instead of allowObjectView()!
				//$out[] = 'attribute: confidential'; // DO NOT TRANSLATE!
		//	}


$out['ldhName'] = $obj->nodeId(false);
$out['name'] = $obj->nodeId(true);
$out['objectClassName'] = $ns;
$out['rdapConformance'] = [
        "rdap_level_0",
        "oidplus_level_2",
        "frdlweb_level_2"
    ];
$out['links'] = [
          [
            "href"=> 'https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'],
            "type"=> "application/rdap+json",
            "title"=> sprintf("Information about the %s %s", $ns, $n[1]),
            "value"=> 'https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'],
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
            "type"=> "remark",
            "description"=> [
                sprintf("The %s %s is known.", strtoupper($ns), $n[1]),
            ],
            "links"=> []
        ],
  [
            "title"=>"Description",
            "type"=> "remark",
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

___rdap_out($out);

//print_r('<pre>');
//print_r($obj);


function ___rdap_out($out){
	header('Content-Type: application/rdap+json');
	echo json_encode($out);
	exit;
}