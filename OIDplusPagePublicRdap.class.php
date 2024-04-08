<?php

/*
 * OIDplus 2.0
 * Copyright 2019 - 2023 Daniel Marschall, ViaThinkSoft
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

namespace Frdlweb\OIDplus;

use ViaThinkSoft\OIDplus\OIDplusGui;
use ViaThinkSoft\OIDplus\OIDplusPagePublicAttachments;
use ViaThinkSoft\OIDplus\INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_2;
use ViaThinkSoft\OIDplus\OIDplus;
use ViaThinkSoft\OIDplus\OIDplusConfig;
use ViaThinkSoft\OIDplus\OIDplusObjectTypePlugin;
use ViaThinkSoft\OIDplus\OIDplusPagePluginPublic;
use ViaThinkSoft\OIDplus\OIDplusObject;
use ViaThinkSoft\OIDplus\OIDplusException;
use ViaThinkSoft\OIDplus\OIDplusOID;
use ViaThinkSoft\OIDplus\OIDplusRA;
use ViaThinkSoft\OIDplus\OIDplusNaturalSortedQueryResult;

// phpcs:disable PSR1.Files.SideEffects
\defined('INSIDE_OIDPLUS') or die;
// phpcs:enable PSR1.Files.SideEffects

class OIDplusPagePublicRdap extends OIDplusPagePluginPublic
	implements INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_2, /* modifyContent */
	             \ViaThinkSoft\OIDplus\INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_9  //API
{
     const DEFAULT_RDAP_BASEPATH = 'rdap';
	 const DEFAULT_RDAP_FALLBACK_SERVER = 'https://rdap.frdlweb.de';
	/**
	 * Implements interface INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_2
	 * @param string $id
	 * @param string $title
	 * @param string $icon
	 * @param string $text
	 * @return void
	 * @throws \ViaThinkSoft\OIDplus\OIDplusException
	 */
	public function modifyContent(string $id, string &$title, string &$icon, string &$text) {
	    $payload = '<br /> <a href="'.OIDplus::webpath(__DIR__,OIDplus::PATH_RELATIVE)
			.'rdap/rdap.php?query='.urlencode($id).'" class="gray_footer_font" target="_blank">'._L('RDAP').'</a>';

		$text = str_replace('<!-- MARKER 6 -->', '<!-- MARKER 6 -->'.$payload, $text);
	}

	
	
	public function init($html = true) {
			OIDplus::config()->prepareConfigKey('FRDLWEB_OID_CONNECT_API_ROUTE',
												'API Route for the OID-Connect API, e.g. "oid-connect" ',
												'oid-connect', OIDplusConfig::PROTECTION_EDITABLE, function ($value) {
		  
			 	OIDplus::baseConfig()->setValue('FRDLWEB_OID_CONNECT_API_ROUTE', $value );
		});		
		
		
		OIDplus::config()->prepareConfigKey('FRDLWEB_RDAP_RELATIVE_URI_BASEPATH', 'The RDAP base uri to the RDAP -Module. Example: "rdap" or "oid-connect/rdap"', self::DEFAULT_RDAP_BASEPATH, OIDplusConfig::PROTECTION_EDITABLE, function ($value) {
		  
			 	OIDplus::baseConfig()->setValue('FRDLWEB_RDAP_RELATIVE_URI_BASEPATH', $value );
		});		
		
	 
$hint = 'Fallback Look-Up Server for foreign identifiers. Can be e.g.: "https://rdap.frdlweb.de" or "https://rdap.frdl.de" or in near future: "https://rdap.webfan.de" or "https://rdap.weid.info"';
		OIDplus::config()->prepareConfigKey('FRDLWEB_RDAP_FALLBACK_SERVER', $hint, self::DEFAULT_RDAP_FALLBACK_SERVER, OIDplusConfig::PROTECTION_EDITABLE, function ($value) {
			 
			if(!in_array($value, [   
				'https://rdap.webfan.de',
				'https://rdap.frdlweb.de',	
				'https://rdap.frdl.de', 
				'https://rdap.weid.info',
			])){
				throw new OIDplusException($hint);
			}
			
			OIDplus::baseConfig()->setValue('FRDLWEB_RDAP_FALLBACK_SERVER', $value );
		});
		 
	}	
	
	
	/**
	 * @param string $request
	 * @return bool
	 * @throws OIDplusException
	 */
	public function handle404(string $request): bool {
		$request = trim($_SERVER['REQUEST_URI'],'/');
		if (str_starts_with($request, OIDplus::baseConfig()->getValue('FRDLWEB_RDAP_RELATIVE_URI_BASEPATH', 'rdap').'/')) {
			$request
				= substr($_SERVER['REQUEST_URI'], strlen(OIDplus::baseConfig()->getValue('FRDLWEB_RDAP_RELATIVE_URI_BASEPATH', 'rdap').'/'));
			$request = trim($request,'/');
			[$ns, $id] = explode('/', $request, 2);
			
					
			$obj = OIDplusObject::findFitting($id);
				
			if (!$obj) {
				$obj = OIDplusObject::parse($id);
			}
			
			if($obj){
				if($ns === $obj::ns()){							
					$query = $ns.':'.$obj->nodeId(false);			
					$x = new OIDplusRDAP();			
					list($out_content, $out_type) = $x->rdapQuery($query);		
					if ($out_type){
						$o = (array)json_decode($out_content); 
					 if(!isset($o['error']) || 'Not found' !== $o['error']){
						header('Content-Type:'.$out_type);		
					   echo $out_content;		
					   die();
					 }
					}
				}
			}
			
 
		    foreach (OIDplus::getEnabledObjectTypes() as $ot) {
					$query = $ot::ns().':'.$id;		
			
					$x = new OIDplusRDAP();			
					list($out_content, $out_type) = $x->rdapQuery($query);		
					if ($out_type){
						$o = (array)json_decode($out_content); 
					 if(!isset($o['error']) || 'Not found' !== $o['error']){
						header('Content-Type:'.$out_type);		
					   echo $out_content;		
					   die();
					 }
					}
		 
		    }		
			
							
			header('Content-Type:'.$out_type);							  
			echo $out_content;							
			die();
		}
		
		return false;
	}

	
	
	
		public function restApiInfo(string $kind='html'): string {
			$bPath = OIDplus::baseConfig()->getValue('FRDLWEB_OID_CONNECT_API_ROUTE', 'oid-connect');
		if ($kind === 'html') {
			$struct = [
				_L('@ Get') => [
					'<b>GET</b> '.OIDplus::webpath(null,OIDplus::PATH_ABSOLUTE_CANONICAL).'rest/v1/'.$bPath.'/<abbr title="'._L('e.g. %1', '@/oid:2.999').'">[id]</abbr>',
					_L('Input parameters') => [
						'<i>'._L('None').'</i>'
					],
					_L('Output parameters') => [
						'mixed...'
					]
				],
				
	
			];
			return array_to_html_ul_li($struct);
		} else {
			throw new OIDplusException(_L('Invalid REST API information format'), null, 500);
		}
	}
	
	
	


	public function restApiCall(string $requestMethod, string $endpoint, array $json_in) {
		 
		if (str_starts_with($endpoint, OIDplus::baseConfig()->getValue('FRDLWEB_OID_CONNECT_API_ROUTE', 'oid-connect').'/')) {
			$id = substr($endpoint, strlen(OIDplus::baseConfig()->getValue('FRDLWEB_OID_CONNECT_API_ROUTE', 'oid-connect').'/'));
			$obj = OIDplusObject::findFitting($id);
				
			if (!$obj) {
				$obj = OIDplusObject::parse($id);
			}
			/*	*/
				
	    	if (!$obj) {
              http_response_code(404);
           //   throw new OIDplusException(_L('REST endpoint not found'), null, 404);
           		OIDplus::invoke_shutdown();
		    	@header('Content-Type:application/json; charset=utf-8');
			    echo json_encode([
			      'code'=>404,
			      'message'=>'Not found',
			      'endpoint'=>$endpoint,
			      'method'=>$requestMethod,
			      'payload'=>$json_in,
			    ]);
			    die(); // return true;
			}
			
			if('POST' === $requestMethod){
				 if (!$obj->userHasReadRights() && $obj->isConfidental()){    		
    		        throw new OIDplusException('Insufficient authorization to write information to this object.', null, 401);
		         }	
		         
	              http_response_code(200);
           //   throw new OIDplusException(_L('REST endpoint not found'), null, 404);
           		OIDplus::invoke_shutdown();
		    	@header('Content-Type:application/json; charset=utf-8');
			    echo json_encode([
			      'code'=>200,
			      'message'=>'Allocation Information Data',
			      'endpoint'=>$endpoint,
			      'method'=>$requestMethod,
			       'object'=>(array)$obj,
			      'alloc'=>[],
			    ]);
			    die(); // return true;	         
		         
		         
			}elseif('GET' === $requestMethod){
				 if (!$obj->userHasReadRights() && $obj->isConfidental()){    		
    		        throw new OIDplusException('Insufficient authorization to read information about this object.', null, 401);
		         }	
		         
	              http_response_code(200);
           //   throw new OIDplusException(_L('REST endpoint not found'), null, 404);
           		OIDplus::invoke_shutdown();
		    	@header('Content-Type:application/json; charset=utf-8');
			    echo json_encode([
			      'code'=>200,
			      'message'=>'Allocation Information Data',
			      'endpoint'=>$endpoint,
			      'method'=>$requestMethod,
			       'object'=>(array)$obj,
			      'alloc'=>[],
			    ]);
			    die(); // return true;	         
		         
		         
			}elseif('DELETE' === $requestMethod){
				if (!$obj->userHasParentalWriteRights()){
		         throw new OIDplusException(_L('Authentication error. Please log in as the superior RA to delete this OID.'),
		           null, 401);
				}
	             
	              http_response_code(200);
            
           		OIDplus::invoke_shutdown();
		    	@header('Content-Type:application/json; charset=utf-8');
			    echo json_encode([
			      'code'=>200,
			      'message'=>'Allocation dereferenced',
			      'endpoint'=>$endpoint,
			      'method'=>$requestMethod,
			    'object'=>(array)$obj,
			  'alloc'=>[],
			    ]);
			    die(); // return true;	     
			}else{
				if (!$obj->userHasParentalWriteRights()){
		         throw new OIDplusException(_L('Authentication error. Please log in as the superior RA to maintain this OID.'),
		           null, 401);
				}
				
					          
			    http_response_code(200);
            
           		OIDplus::invoke_shutdown();
		    	@header('Content-Type:application/json; charset=utf-8');
			    echo json_encode([
			      'code'=>200,
			      'message'=>'Allocation modified',
			      'endpoint'=>$endpoint,
			      'method'=>$requestMethod,
		          'object'=>(array)$obj,
		          'alloc'=>[],
			    ]);
			    die(); // return true;				
			}

    	  
		}
	}
					   
	
}
