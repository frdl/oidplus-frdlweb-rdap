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
use ViaThinkSoft\OIDplus\OIDplusOid;
use ViaThinkSoft\OIDplus\OIDplusRA;
use ViaThinkSoft\OIDplus\OIDplusNaturalSortedQueryResult;
use ViaThinkSoft\OIDplus\OIDplusOIDIP;


use Symfony\Component\Cache\Adapter\FilesystemAdapter;

// phpcs:disable PSR1.Files.SideEffects
\defined('INSIDE_OIDPLUS') or die;
// phpcs:enable PSR1.Files.SideEffects

class OIDplusPagePublicRdap extends OIDplusPagePluginPublic
	implements \ViaThinkSoft\OIDplus\INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_2, /* modifyContent */
	             \ViaThinkSoft\OIDplus\INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_9,  //API

	 
	           \ViaThinkSoft\OIDplus\INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_3, /* beforeObject*, afterObject* */
	           \ViaThinkSoft\OIDplus\INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_4, /* whois*Attributes */
	           \ViaThinkSoft\OIDplus\INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_8  /* getNotifications */
{
     const DEFAULT_RDAP_BASEPATH = 'rdap';
	 const DEFAULT_RDAP_FALLBACK_SERVER = 'https://rdap.frdlweb.de';
	 const CACHE_VERSION = '24.0.0';
	 const INSTANCES_URL = 'https://hosted.oidplus.com/viathinksoft/plugins/viathinksoft/publicPages/100_whois/whois/webwhois.php?query=oid%3A1.3.6.1.4.1.37476.30.9$format=json';
				   
	protected $rdapServer_configdir;
	protected $rdapServer_bootfile;		
	protected $db_table_exists = false;
				   
	protected $_cache = null;
				   
				   
				   
	/**
	 * Implements interface INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_8
	 * @param string|null $user
	 * @return array  returns array of array($severity, $htmlMessage)
	 */
	public function getNotifications(string $user=null): array {
		$notifications = array();
		
		if (is_null(OIDplus::getPluginByOid("1.3.6.1.4.1.37476.9000.108.19361.24196"))) { 
			    $error = '';
				$error = _L('The RDAP Server depends on the IO4 Plugin and its extensions');
				$htmlmsg = 'Please install the IO4 Plugin into plugins/frdl/adminPages/io4/ from this repository: '
					.'https://github.com/frdl/oidplus-io4-bridge-plugin (contact the site adminstrator to do it)!!!';
				$error .= ': ' . $htmlmsg;		
			$notifications[] = new \ViaThinkSoft\OIDplus\OIDplusNotification('ERR', $error);
		}
		
		
		if (OIDplus::db()->tableExists("###attributes")) {
			    $error = '';
				$error = _L('The table ###attributes is not used by the plugin anymore');
				$htmlmsg = 'The RDAP-Plugin DB-Structure changed. If the table {prefix}_attributes is not needed by another plugin  '
					.' you can delete it (or migrate if you have data in it what is not expected)!';
				$error .= ': ' . $htmlmsg;		
			$notifications[] = new \ViaThinkSoft\OIDplus\OIDplusNotification('WARN', $error);			
		}
		/*
		//if ((!$user || ($user == 'admin')) && OIDplus::authUtils()->isAdminLoggedIn()) {
			$error = '';
			try {
				$basepath =$this->rdapServer_configdir;
				if (!is_dir($basepath)) {
					mkdir($basepath, 0755, true);
				} 
				
				if(!file_exists($this->rdapServer_bootfile)){
					throw new OIDplusException(_L('RDAP Server Bootstrap File %1 does not exist. You have to run an initial setup of the RDAP server! This will be done automatically or if you register a sub-ra registries rdap server node (REGISTRAR).'
				.'<br /><a href="https://oid.zone">OID-Connect Documentation</a>'								  
												  ,
												  $this->rdapServer_bootfile));
				}
					
					//throw new OIDplusException(_L('Directory %1 is not writeable. Please check the permissions!', $basepath));
			} catch (\Exception $e) {
				$error = _L('The RDAP Server feature is not available or not setup');
				$htmlmsg = $e instanceof OIDplusException ? $e->getHtmlMessage() : htmlentities($e->getMessage());
				$error .= ': ' . $htmlmsg;
			}
			if ($error) {
				$notifications[] = new \ViaThinkSoft\OIDplus\OIDplusNotification('WARN', $error);
			}
	//	}
	*/
		return $notifications;
	}				   
				   
				   
				   
				   
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

	public function gui(string $id, array &$out, bool &$handled) {
		if (explode('$',$id)[0] == 'oidplus:rdap') {
			$handled = true;
 
 

			$out['title'] = _L('RDAP Protocol (OID-Connect) / RDAP');
	//	$out['icon'] = file_exists(__DIR__.'/img/main_icon.png') ? OIDplus::webpath(__DIR__,OIDplus::PATH_RELATIVE).'img/main_icon.png' : '';

			$out['text']  = '<p>'._L('With the RDAP Protocol, you can query object information about an object/OID/WEID/ID and its registration information and authoritive RA info.').'</p>';


		}
	}	
				   
				   
	
	public function init($html = true) {
		$this->rdapServer_configdir = __DIR__.\DIRECTORY_SEPARATOR.'rdap-server';
		$this->rdapServer_bootfile = $this->rdapServer_configdir.\DIRECTORY_SEPARATOR.'bootstrap.oid.json';
		
	if (!OIDplus::db()->tableExists("###attr")) {
			if (OIDplus::db()->getSlang()->id() == 'mysql') {
				OIDplus::db()->query("CREATE TABLE ###attr ( `id` int(11) NOT NULL AUTO_INCREMENT, `oid` varchar(255) NOT NULL, `a_subject` varchar(255) NOT NULL, `a_verb` varchar(255) NOT NULL, `a_object` varchar(255) NOT NULL, `created` int(11) NOT NULL DEFAULT 0,  `expires` int(11) NOT NULL DEFAULT -1,  PRIMARY KEY(`id`), CONSTRAINT ucosv UNIQUE (oid,a_subject,a_verb,a_object) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
				$this->db_table_exists = true;
			} else if (OIDplus::db()->getSlang()->id() == 'mssql') {
				// We use nvarchar(225) instead of varchar(255), see https://github.com/frdl/oidplus-plugin-alternate-id-tracking/issues/18
				// Unfortunately, we cannot use nvarchar(255), because we need two of them for the primary key, and an index must not be greater than 900 bytes in SQL Server.
				// Therefore we can only use 225 Unicode characters instead of 255.
				// It is very unlikely that someone has such giant identifiers. But if they do, then saveAltIdsForQuery() will reject the INSERT commands to avoid that an SQL Exception is thrown.
				OIDplus::db()->query("CREATE TABLE ###attr (  [id] int(11) NOT NULL AUTO_INCREMENT, [oid] nvarchar(225) NOT NULL, [a_subject] nvarchar(225) NOT NULL, [a_verb] nvarchar(225), [a_object] nvarchar(225) NOT NULL,  [created] int(11) NOT NULL DEFAULT 0,  [expires] int(11) NOT NULL DEFAULT -1, CONSTRAINT [PK_###attr] PRIMARY KEY ( [id]  ) , CONSTRAINT [PK_###attributes] UNIQUE KEY CLUSTERED( [oid] ASC, [a_subject] ASC [a_verb] ASC [a_object] ASC ) )");
				$this->db_table_exists = true;
			} else if (OIDplus::db()->getSlang()->id() == 'oracle') {
				// TODO: Implement Table Creation for this DBMS (see CREATE TABLE syntax at plugins/viathinksoft/sqlSlang/oracle/sql/*.sql)
				$this->db_table_exists = false;
			} else if (OIDplus::db()->getSlang()->id() == 'pgsql') {
				// TODO: Implement Table Creation for this DBMS (see CREATE TABLE syntax at plugins/viathinksoft/sqlSlang/pgsql/sql/*.sql)
				$this->db_table_exists = false;
			} else if (OIDplus::db()->getSlang()->id() == 'access') {
				// TODO: Implement Table Creation for this DBMS (see CREATE TABLE syntax at plugins/viathinksoft/sqlSlang/access/sql/*.sql)
				$this->db_table_exists = false;
			} else if (OIDplus::db()->getSlang()->id() == 'sqlite') {
				// TODO: Implement Table Creation for this DBMS (see CREATE TABLE syntax at plugins/viathinksoft/sqlSlang/sqlite/sql/*.sql)
				$this->db_table_exists = false;
			} else if (OIDplus::db()->getSlang()->id() == 'firebird') {
				// TODO: Implement Table Creation for this DBMS (see CREATE TABLE syntax at plugins/viathinksoft/sqlSlang/firebird/sql/*.sql)
				$this->db_table_exists = false;
			} else {
				// DBMS not supported
				$this->db_table_exists = false;
			}
		} else {
			$this->db_table_exists = true;
		}	
		
	
		
	if (!OIDplus::db()->tableExists("###attrlogstream")) {
			if (OIDplus::db()->getSlang()->id() == 'mysql') {
				OIDplus::db()->query("CREATE TABLE ###attrlogstream ( `expires` int(11) NOT NULL DEFAULT -1, `id_attribute` int(11) NOT NULL,  `created` int(11) NOT NULL DEFAULT 0,  `to` varchar(255) NOT NULL, `from` varchar(255) NOT NULL, `type` varchar(255) NOT NULL, `actor` varchar(255) NOT NULL, `note` varchar(255) NOT NULL, `a_value` text DEFAULT NULL,  PRIMARY KEY (`id_attribute`,  `type`, `actor`)  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
				$this->db_table_exists = true;
			} else if (OIDplus::db()->getSlang()->id() == 'mssql') {
				// We use nvarchar(225) instead of varchar(255), see https://github.com/frdl/oidplus-plugin-alternate-id-tracking/issues/18
				// Unfortunately, we cannot use nvarchar(255), because we need two of them for the primary key, and an index must not be greater than 900 bytes in SQL Server.
				// Therefore we can only use 225 Unicode characters instead of 255.
				// It is very unlikely that someone has such giant identifiers. But if they do, then saveAltIdsForQuery() will reject the INSERT commands to avoid that an SQL Exception is thrown.
				OIDplus::db()->query("CREATE TABLE ###attrlogstream ( [expires] int(11) NOT NULL DEFAULT -1,  [id_attribute] int(11) NOT NULL,  [created] int(11) NOT NULL DEFAULT 0, [to] nvarchar(225) NOT NULL, [from] nvarchar(225) NOT NULL, [type] nvarchar(225), [actor] nvarchar(225) NOT NULL, [note] nvarchar(225) NOT NULL, [a_value] TEXT, CONSTRAINT [PK_###attrlogstream] PRIMARY KEY CLUSTERED( [id_attribute] ASC, [type] ASC,  [actor] ASC )  )");
				$this->db_table_exists = true;
			} else if (OIDplus::db()->getSlang()->id() == 'oracle') {
				// TODO: Implement Table Creation for this DBMS (see CREATE TABLE syntax at plugins/viathinksoft/sqlSlang/oracle/sql/*.sql)
				$this->db_table_exists = false;
			} else if (OIDplus::db()->getSlang()->id() == 'pgsql') {
				// TODO: Implement Table Creation for this DBMS (see CREATE TABLE syntax at plugins/viathinksoft/sqlSlang/pgsql/sql/*.sql)
				$this->db_table_exists = false;
			} else if (OIDplus::db()->getSlang()->id() == 'access') {
				// TODO: Implement Table Creation for this DBMS (see CREATE TABLE syntax at plugins/viathinksoft/sqlSlang/access/sql/*.sql)
				$this->db_table_exists = false;
			} else if (OIDplus::db()->getSlang()->id() == 'sqlite') {
				// TODO: Implement Table Creation for this DBMS (see CREATE TABLE syntax at plugins/viathinksoft/sqlSlang/sqlite/sql/*.sql)
				$this->db_table_exists = false;
			} else if (OIDplus::db()->getSlang()->id() == 'firebird') {
				// TODO: Implement Table Creation for this DBMS (see CREATE TABLE syntax at plugins/viathinksoft/sqlSlang/firebird/sql/*.sql)
				$this->db_table_exists = false;
			} else {
				// DBMS not supported
				$this->db_table_exists = false;
			}
		} else {
			$this->db_table_exists = true;
		}			
		
		/*
			OIDplus::config()->prepareConfigKey('FRDLWEB_OID_CONNECT_API_ROUTE',
												'API Route for the OID-Connect API, e.g. "oid-connect" ',
												'oid-connect', OIDplusConfig::PROTECTION_EDITABLE, function ($value) {
		  
			 	OIDplus::baseConfig()->setValue('FRDLWEB_OID_CONNECT_API_ROUTE', $value );
		});		
		*/
		
		OIDplus::config()->prepareConfigKey('FRDLWEB_RDAP_RELATIVE_URI_BASEPATH', 'The RDAP base uri to the RDAP -Module. Example: "rdap" or "oid-connect/rdap"', self::DEFAULT_RDAP_BASEPATH, OIDplusConfig::PROTECTION_EDITABLE, function ($value) {
		  
			 	OIDplus::baseConfig()->setValue('FRDLWEB_RDAP_RELATIVE_URI_BASEPATH', $value );
		});		
		
	 
$hint = 'Fallback Look-Up Server for foreign identifiers. Can be e.g.: "https://rdap.frdlweb.de" or "https://rdap.frdl.de" or in near future: "https://rdap.webfan.de" or "https://rdap.weid.info"';
		OIDplus::config()->prepareConfigKey('FRDLWEB_RDAP_FALLBACK_SERVER', $hint, self::DEFAULT_RDAP_FALLBACK_SERVER, OIDplusConfig::PROTECTION_EDITABLE, function ($value) use($hint) {
			 
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
		 
	  if(!class_exists(\Webfan\RDAP\Rdap::class)){
		$io4Plugin = OIDplus::getPluginByOid("1.3.6.1.4.1.37476.9000.108.19361.24196");		         
		if (!is_null($io4Plugin) && \is_callable([$io4Plugin,'getWebfat']) ) {
		   $io4Plugin->getWebfat(true,false);	              
		}else{
			throw new OIDplusException(sprintf('You have to install the dependencies of the plugin package %s via composer OR you need the plugin %s to be installed in OIDplus and its remote-autoloader enabled. Read about how to use composer with OIDplus at: https://weid.info/plus/ .', 'https://github.com/frdl/oidplus-frdlweb-rdap', 'https://github.com/frdl/oidplus-io4-bridge-plugin'));
		}
	  }//!exists \Webfan\RDAP\Rdap::class
		
		
	}	//init				   
				   
				   
				   
				   
				   
	protected function cache(?string $type = null, ?string $name = null){
		if(null === $this->_cache){
	    	$directory =  OIDplus::baseConfig()->getValue('RDAP_CACHE_DIRECTORY', OIDplus::localpath().'userdata/cache/' );
			$namespace = 'rdap';
			//$defaultLifetime = 24 * 60 * 60;
			$defaultLifetime = 8 * 60;
			$this->_cache = new FilesystemAdapter(    
				// a string used as the subdirectory of the root cache directory, where cache   
				// items will be stored   
				$namespace,				
   
				// the default lifetime (in seconds) for cache items that do not define their   
				// own lifetime, with a value 0 causing items to be stored indefinitely (i.e.   
				// until the files are deleted)   
				$defaultLifetime,  
				// the main cache directory (the application needs read-write permissions on it)  
				// if none is specified, a directory is created inside the system temporary directory   
				$directory
			);			
		}
		//$item = $cache->getItem($this->getCacheKey('oidplus', 'instances'));
		 if(is_string($type) && is_null($name) ){
			 return $this->_cache->getItem($type);
		 }elseif(is_string($type) && is_string($name) ){
			return $this->_cache->getItem($this->getCacheKey($type, $name));
		 }
		return $this->_cache;
	}
				   
	public function rdapExtensions($out, $namespace, $id, $obj, $query) : array {
		$ns = $namespace;
		$n = [
			$namespace,
			$id,
		];
	if (!is_null(OIDplus::getPluginByOid("1.3.6.1.4.1.37476.2.5.2.4.1.100"))) { // OIDplusPagePublicWhois
			$oidIPUrl = OIDplus::webpath().'plugins/viathinksoft/publicPages/100_whois/whois/webwhois.php?query='.urlencode($query);

			$oidip_generator = new OIDplusOIDIP();

		//	list($oidIP, $dummy_content_type) = $oidip_generator->oidipQuery($query);

			$out['remarks'][] = [
				"title" => "OID-IP Result",
				"description" => [
					sprintf("Additional %s %s was added.", 'OID-IP Result info from RDAP-plugin', "1.3.6.1.4.1.37476.2.5.2.4.1.100"),
				],
				"links" => [
						[
							"href"=> $oidIPUrl,
							"type"=> "text/plain",
							"title"=> sprintf("OIDIP Result for the %s %s (Plaintext)", $ns, $n[1]),
							"value"=> $oidIPUrl,
							"rel"=> "alternate"
						],
						[
							"href"=> "$oidIPUrl\$format=json",
							"type"=> "application/json",
							"title"=> sprintf("OIDIP Result for the %s %s (JSON)", $ns, $n[1]),
							"value"=> "$oidIPUrl\$format=json",
							"rel"=> "alternate"
						],
						[
							"href"=> "$oidIPUrl\$format=xml",
							"type"=> "application/xml",
							"title"=> sprintf("OIDIP Result for the %s %s (XML)", $ns, $n[1]),
							"value"=> "$oidIPUrl\$format=xml",
							"rel"=> "alternate"
						]
					]
				];

			list($oidIPJSON, $dummy_content_type) = $oidip_generator->oidipQuery("$query\$format=json");
			$out['oidplus_oidip'] = json_decode($oidIPJSON);
			$out['rdapConformance'][]='oidplus_oidip';
			$out['oidplus_oidip_properties'] = [
				                             "\$schemma" , 
											 "oidip",
											
											];	

		}else{
		   //no oidplus_oidip plugin
			$out['remarks'][] = [
				"title" => "Availability",
				"description" => [
					sprintf("The %s %s is MISSING.", 'OID-IP Result from RDAP-plugin', "1.3.6.1.4.1.37476.2.5.2.4.1.100"),
				],
				"links"=> []
			];
			//$out['oidplus_oidip'] = false;
		}	
		//oidplus_oidip
		
				

		                 
						$description =strip_tags($obj->getDescription()); 	 
							  
		
		           $ext = [];
		           $unf = [];
		           preg_match_all("/(?P<name>[A-Z0-9\-\_\.\"\']+)(\s|\n)(\:|\=)(\s|\n)(?P<value>[^\s]+)/xs",
								  $description, $matches, \PREG_PATTERN_ORDER);
		
		            foreach($matches[0] as $k => $v){
						$ext[$matches['name'][$k]] = $matches['value'][$k];
					}
				         
		            foreach($ext as $ka => $v){
						$k = explode('.', $ka, 2)[0];
					
						if(is_numeric($k) 
						    && intval($k) > 0
						  ){	 
							$unf[$ka] = $v;
						}
					}
 
		            $ext = $this->unflatten($ext, '.', -1);
		
		           foreach($unf as $k => $v){ 
							$ext[$k] = $v; 
					}	        		            

		
			
		$out['remarks'][] = [
				"title" => "frdlweb_ini_dot is available",
				"description" => [
					sprintf("Additional %s from %s was added.", 'RDAP-extension Result', "1.3.6.1.4.1.37476.9000.108.1276945.19361.24174.17741"),
				],
				"links" => [					
								
					[
						"href"=> 'https://hosted.oidplus.com/viathinksoft/?goto=oid%3A1.3.6.1.4.1.37476.9000.108.1276945.19361.24174.17741',
							"type"=> "text/plain",
							"title"=> sprintf("frdlweb_ini_dot for the %s %s (Plaintext)", $ns, $n[1]),
							"value"=> 'https://hosted.oidplus.com/viathinksoft/?goto=oid%3A1.3.6.1.4.1.37476.9000.108.1276945.19361.24174.17741',
							"rel"=> "help"
						],		
				],				
		];
				
		    $out['frdlweb_ini_dot'] = $ext;
			$out['rdapConformance'][]='frdlweb_ini_dot'; 

		
		
		//redirect_with_content
		$out['rdapConformance'][]='redirect_with_content'; 
		if(isset($out['frdlweb_ini_dot']['RDAP'])){
			  if(isset($out['frdlweb_ini_dot']['RDAP']['URL']['AUTHORITATIV'])
				 && isset($out['frdlweb_ini_dot']['RDAP']['SYSTEM']) ){
				  $url = rtrim( $out['frdlweb_ini_dot']['RDAP']['URL']['AUTHORITATIV'],'/ ').'/';
				  $url.= $ns.'/';
				  $url.= $id;			 
				  $headers = @get_headers($url);                
				  $exists = (bool) strpos($headers[0], '200');  
				  if($exists){
					  $out['redirect_with_content'] = $url;
				  }
			  }
		}//$out['frdlweb_ini_dot']['RDAP']
		return $out;
	}				   
   
				   
				   
				   
				   
				   
    public function unflatten(array $arr, $delimiter = '.', $depth = -1)
    {
        $output = [];
        foreach ($arr as $key => $value) {
        if(($parts = @preg_split($delimiter, $key, -1)) === false){
           //pattern is broken
          $parts = ($depth>0)?explode($delimiter, $key, $depth):explode($delimiter, $key);
           }else{
           //pattern is real

           }
        //$parts = ($depth>0)?explode($delimiter, $key, $depth):explode($delimiter, $key);
        $nested = &$output;
        while (count($parts) > 1) {
          $nested = &$nested[array_shift($parts)];
          if (!is_array($nested)) $nested = [];
        }
        $nested[array_shift($parts)] = $value;
        }
        return $output;
    }
	/**
	 * Implements interface INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_4
	 * @param string $id
	 * @param array $out
	 * @return void
	 * @throws OIDplusException
	 <?xml version="1.0"?>
<xs:schema targetNamespace="urn:oid:1.3.6.1.4.1.37476.2.5.2.4.1.95.1"
           attributeFormDefault="unqualified"
           elementFormDefault="qualified"
           xmlns:xs="http://www.w3.org/2001/XMLSchema">
<xs:element type="xs:string" name="attribute-subject" />
<xs:element type="xs:string" name="attribute-verb" />
<xs:element type="xs:string" name="attribute-object" />
<xs:element type="xs:string" name="attribute-value" />
</xs:schema>
	 */
	public function whoisObjectAttributes(string $id, array &$out) {
		$xmlns = 'oidplus-rdap-plugin';
		$xmlschema = 'urn:oid:1.3.6.1.4.1.37476.2.5.2.4.1.95.1';
		$xmlschemauri = OIDplus::webpath(__DIR__.'/attributes.xsd',OIDplus::PATH_ABSOLUTE_CANONICAL);

								
		$res = OIDplus::db()->query("select * from ###attr where " .
						                            "oid = ? ORDER BY a_subject ASC, a_verb ASC, a_object ASC", array($id));
		
		//$res->naturalSortByField('id');
		while ($row = $res->fetch_object()) {
			$out[] = array(
				'xmlns' => $xmlns,
				'xmlschema' => $xmlschema,
				'xmlschemauri' => $xmlschemauri,
				'name' => 'attribute-subject',
				'value' => $row->a_subject
			);

			$out[] = array(
				'xmlns' => $xmlns,
				'xmlschema' => $xmlschema,
				'xmlschemauri' => $xmlschemauri,
				'name' => 'attribute-verb',
				'value' => $row->a_verb
			);
			
					
			$out[] = array(
				'xmlns' => $xmlns,
				'xmlschema' => $xmlschema,
				'xmlschemauri' => $xmlschemauri,
				'name' => 'attribute-object',
				'value' =>$row->a_object
			);

			$out[] = array(
				'xmlns' => $xmlns,
				'xmlschema' => $xmlschema,
				'xmlschemauri' => $xmlschemauri,
				'name' => 'attribute-value',
				'value' => $row->a_value
			);
			
 	
			
		}
		/*  rdap-redirector
		
		$files = @glob(self::getUploadDir($id) . DIRECTORY_SEPARATOR . '*');
		if ($files) foreach ($files as $file) {
			$url = OIDplus::webpath(__DIR__,OIDplus::PATH_ABSOLUTE_CANONICAL).'download.php?id='.urlencode($id).'&filename='.urlencode(basename($file));

			$out[] = array(
				'xmlns' => $xmlns,
				'xmlschema' => $xmlschema,
				'xmlschemauri' => $xmlschemauri,
				'name' => 'attachment-name',
				'value' => basename($file)
			);

			$out[] = array(
				'xmlns' => $xmlns,
				'xmlschema' => $xmlschema,
				'xmlschemauri' => $xmlschemauri,
				'name' => 'attachment-url',
				'value' => $url
			);
		}
		*/

	}

	/**
	 * Implements interface INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_4
	 * @param string $email
	 * @param array $out
	 * @return void
	 */
	public function whoisRaAttributes(string $email, array &$out) {
	
	}


	
	
				   
	public static function getRdapServerBase(){
	  //OIDplus::webpath()	
		return OIDplus::webpath().OIDplus::baseConfig()->getValue('FRDLWEB_RDAP_RELATIVE_URI_BASEPATH', 'rdap').'/';
	}
				   


		
	public function getCacheKey($type, $name){
		return 'rdap.25-'.strlen($type).'.'.self::CACHE_VERSION.'-'. __FILE__ .'-'
			.sha1($name).'l'.strlen($name).'-'.sha1($type);
	}		
				   
	public function getOIDplusInstances(){
	    $cache= $this->cache();
		//$item = $cache->getItem($this->getCacheKey('oidplus', 'instances'));
		$item = $this->cache('oidplus', 'instances');
		if (!$item->isHit()) {   
		    $item->expiresAfter(10 * 60);
			$res = []; 
			 @set_time_limit(intval(ini_get('max_execution_time')) + 90); 
	         $inst=json_decode(file_get_contents(self::INSTANCES_URL));
			 $subs = $inst->oidip->objectSection->subordinate;
			 foreach($subs as $sub){
				 $sub=explode(' ', $sub);
				 $sub=$sub[0]; 
			     @set_time_limit(intval(ini_get('max_execution_time')) + 90); 
				 try{
					 
					 $itemSub = $this->cache('oidplus', 'instance_'.$sub);
							
					 if (!$itemSub->isHit()) {   
		                 $itemSub->expiresAfter(3 * 60 * 60); 
				 $instanceInfo = json_decode(file_get_contents('https://hosted.oidplus.com/viathinksoft/plugins/viathinksoft/publicPages/100_whois/whois/webwhois.php?query='.urlencode($sub).'$format=json'));
					 
					 
					  $description = $instanceInfo->oidip->objectSection->description;
							      $registryName = $instanceInfo->oidip->objectSection->name;							
									preg_match("/System\sID\:\d\sLast\sknown\sURL\:([^\s]+)\s/", $description, $matches);
					 if(!isset($matches[1])){
						 preg_match("/URL\:([^\s]+)\s/", $description, $matches);
					 }
									$url = $matches[1];
						 
						 				  $headers = @get_headers($url);                
				                          $exists = (bool) strpos($headers[0], '200'); 
						 
						 $resultSub=[
					        'registryName' => $registryName,
					     //  'description' => $description,
					       'url' => $url,
					      'id'=>$sub,
					      'oid'=>  str_replace('oid:', '', $instanceInfo->oidip->objectSection->{'canonical-identifier'}),
						  'available'=>$exists,
				  	     ];			
						 
		                 $itemSub->set($resultSub);
			             $cache->save($itemSub);	
					 }//itemSub !hit
					 
					 $r = $itemSub->get();
			    	$res['oid:'.$r['oid']]=$r;					 
					 
				 }catch(\Exception $e){	
				
					 continue;							
				 }

			 }
			
		//	$result = json_encode($res);
			ksort($res);
			$res = array_values($res);
			
			$result = $res;
			$item->set($result);
			$cache->save($item);			
		}
		//$Instances = json_decode($item->get());
		$Instances = $item->get();
		//shuffle($Instances);
		return $Instances;
	}				   
				   
				   
				   
	public function rdapBootstrap_oid_services(array $out) : array {
	    foreach($this->getOIDplusInstances() as $instance){
			if(!isset($out[$instance['url']])){
				$out[$instance['url']] = [];
			}
			if(!isset($out[$instance['url']][0])){
				$out[$instance['url']][0] = [];
			}		
			if(!isset($out[$instance['url']][1])){
				$out[$instance['url']][1] = [];
			}	
			
			if (!in_array($instance['url'], $out[$instance['url']][1])) {   
                $out[$instance['url']][1][]=$instance['url'];
			}  
									
			if (!in_array($instance['oid'], $out[$instance['url']][0])) {   
                $out[$instance['url']][0][]=$instance['oid'];
			}      
		}		
		return $out;
	}
	

	
	public function rdapBootstrapServices($namespace) : array {
	    $cache= $this->cache();
	//	$item = $cache->getItem($this->getCacheKey('rdapBootstrapServices', 'all-in-instance'));
		$item = $this->cache('rdapBootstrapServices', 'all-in-instance-services');
		if (!$item->isHit()) {   
		    $item->expiresAfter(3 * 60);	
		
		     $out = [];
		foreach(OIDplus::getAllPlugins() as $pkey => $plugin){
			if(method_exists($plugin, 'rdapBootstrap_'.$namespace.'_services')){
				$out = \call_user_func_array([$plugin, 'rdapBootstrap_'.$namespace.'_services'], [$out]);
			}
		}
				
			$result = array_values($out);
			$item->set($result);
			$cache->save($item);			
		}
		//$Instances = json_decode($item->get());
		$out = $item->get();	
		return $out;
	}			   
	
				   
	public function rdapBootstrapRootServices($namespace) : array {
	    $cache= $this->cache();
		//$item = $cache->getItem($this->getCacheKey('rdapBootstrapServices', 'all-root-instance'));
		$item = $this->cache('rdapBootstrapServices', 'all-root-wrap-instance');
		if (!$item->isHit()) {   
		    $item->expiresAfter(3 * 60);	
		 
			  $client = new \Webfan\RDAP\Rdap($namespace);
			  $client = $client->addService($namespace, $this->rdapBootstrapServices($namespace));
			
			  $result = $client->dumpServices($protocol, true, true);	
			 
			$item->set($result);
			$cache->save($item);			
		}
		//$Instances = json_decode($item->get());
		$out = $item->get();	
		return $out;
	}						   
				   
	
	public function wrapBootstrapServices($namespace) : array {
	    $cache= $this->cache();
		//$item = $cache->getItem($this->getCacheKey('rdapBootstrapServices', 'all-root-instance'));
		$item = $this->cache('rdapBootstrapServices', 'all-in-wrap-services');
		if (!$item->isHit()) {   
		    $item->expiresAfter(3 * 60);	
		 
			  $client = new \Webfan\RDAP\Rdap($namespace);
			  $client = $client->addService($namespace, $this->rdapBootstrapServices($namespace));
			
			  $result = $client->dumpServices($protocol, false, true);	
			 
			$item->set($result);
			$cache->save($item);			
		}
		//$Instances = json_decode($item->get());
		$out = $item->get();	
		return $out;
	}						   
				   
				   
	protected function negotiateResponse(array $o, string $out_type){
	  //$out['redirect_with_content']	
		 if(!isset($o['error']) || 'Not found' !== $o['error']){
			     if(isset($o['redirect_with_content'])){
					 $url = $o['redirect_with_content'];
					 unset($o['redirect_with_content']);
					 header('Location: '.$url);
				 }
				 header('Content-Type:'.$out_type);		
				 echo json_encode($o);		
				 die();
		 }
	}
	/**
	 * @param string $request
	 * @return bool
	 * @throws OIDplusException
	 */
	
	protected function handle_wrapBootstrapServices( )  {
	     $services = $this->wrapBootstrapServices('oid');
			     http_response_code(200);
           		OIDplus::invoke_shutdown();
		    	@header('Content-Type:application/json; charset=utf-8');
			    echo json_encode($services);
			    die();			
	}
				   
	public function handle404(string $request): bool {
		$requestOidplus = $request;
		$request = trim($_SERVER['REQUEST_URI'],'/');
		$magicLink = false;
		[$ns, $id] = explode('/', $request, 2);
		
		if('bootstrap'===$ns && 'oid' === $id){
		   return $this->handle_wrapBootstrapServices( );
		}
		
		foreach (OIDplus::getEnabledObjectTypes() as $ot) {
				if (str_starts_with($request, $ot::ns().'/') || str_starts_with($requestOidplus, $ot::ns().'/') ) {
					$magicLink = true;
					break;
				}
		}	
		if($magicLink
		   && !str_starts_with($requestOidplus, OIDplus::baseConfig()->getValue('FRDLWEB_RDAP_RELATIVE_URI_BASEPATH', 'rdap').'/')) {
			$request = rtrim(OIDplus::baseConfig()->getValue('CANONICAL_SYSTEM_URL'), '/ ')
				.'/'
				.trim(OIDplus::baseConfig()->getValue('FRDLWEB_RDAP_RELATIVE_URI_BASEPATH', 'rdap'), '/ ').'/'.$ns.'/'.$id;
	 	 
			header('Location: '.$request);
			die('<a href="'.$request.'">'.$request.'</a>');
		}
		
		if (str_starts_with($requestOidplus, OIDplus::baseConfig()->getValue('FRDLWEB_RDAP_RELATIVE_URI_BASEPATH', 'rdap').'/')) {
			$request
				= substr($_SERVER['REQUEST_URI'], strlen(OIDplus::baseConfig()->getValue('FRDLWEB_RDAP_RELATIVE_URI_BASEPATH', 'rdap').'/'));
			$request = trim($request,'/');			
			[$ns, $id] = explode('/', $request, 2);
					
			if('bootstrap'===$ns && 'oid' === $id){		  
				return $this->handle_wrapBootstrapServices( );	
			}
			
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
					   $this->negotiateResponse($o,$out_type);
					}
				}
			}
			
 
		    foreach (OIDplus::getEnabledObjectTypes() as $ot) {
					$query = $ot::ns().':'.$id;		
			
					$x = new OIDplusRDAP();			
					list($out_content, $out_type) = $x->rdapQuery($query);		
					if ($out_type){
						$o = (array)json_decode($out_content); 
					    $this->negotiateResponse($o,$out_type);
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
		 
				_L('@ Get RDAP for an OID') => [
					'<b>GET</b> '.OIDplus::webpath(null,OIDplus::PATH_ABSOLUTE_CANONICAL)
					    .OIDplus::baseConfig()->getValue('FRDLWEB_RDAP_RELATIVE_URI_BASEPATH', 'rdap')
					   .'/<abbr title="'._L('e.g. %1', '@/oid:2.999').'">[id]</abbr>',
					_L('Input parameters') => [
						'<i>'._L('None').'</i>'
					],
					_L('Output parameters') => [
						'mixed...'
					]
				],
				
	
				_L('@ Get DATA for Bootstrap RDAP OID Services of this node') => [
					'<b>GET</b> '.OIDplus::webpath(null,OIDplus::PATH_ABSOLUTE_CANONICAL).'rest/v1/bootstrap/services/oid.json',
					_L('Input parameters') => [
						'<i>'._L('None').'</i>'
					],
					_L('Output parameters') => [
						'array'
					]
				],
				
				_L('@ Get WRAP for Bootstrap RDAP OID Services') => [
					'<b>GET</b> '.OIDplus::webpath(null,OIDplus::PATH_ABSOLUTE_CANONICAL)
					    .OIDplus::baseConfig()->getValue('FRDLWEB_RDAP_RELATIVE_URI_BASEPATH', 'rdap')
					   .'/bootstrap/oid',
					_L('Input parameters') => [
						'<i>'._L('None').'</i>'
					],
					_L('Output parameters') => [
						'array'
					]
				],				
	
				_L('@ Get DATA for Bootstrap RDAP OID Services') => [
					'<b>GET</b> '.OIDplus::webpath(null,OIDplus::PATH_ABSOLUTE_CANONICAL).'rest/v1/bootstrap/root/oid.json',
					_L('Input parameters') => [
						'<i>'._L('None').'</i>'
					],
					_L('Output parameters') => [
						'array'
					]
				],	
 
			];
			return array_to_html_ul_li($struct);
		} else {
			throw new OIDplusException(_L('Invalid REST API information format'), null, 500);
		}
	}
	
	
	


	public function restApiCall(string $requestMethod, string $endpoint, array $json_in) : array|false {
		if('bootstrap/services/oid.json' === $endpoint ){
			     $services = $this->rdapBootstrapServices('oid');
			     http_response_code(200);
           		OIDplus::invoke_shutdown();
		    	@header('Content-Type:application/json; charset=utf-8');
			    echo json_encode([
			      'code'=>200,
			      'message'=>'Bootstrap RDAP OID Services of this node',
			      'endpoint'=>$endpoint,
			      'method'=>$requestMethod,
			      'services'=>$services, 
			    ]);
			    die();
		}elseif('bootstrap/root/oid.json' === $endpoint ){
			     $services = $this->rdapBootstrapRootServices('oid');
			     http_response_code(200);
           		OIDplus::invoke_shutdown();
		    	@header('Content-Type:application/json; charset=utf-8');
			    echo json_encode($services);
			    die();
		}elseif (str_starts_with($endpoint, OIDplus::baseConfig()->getValue('FRDLWEB_OID_CONNECT_API_ROUTE', 'oid-connect').'/')) {
			$id = substr($endpoint, strlen(OIDplus::baseConfig()->getValue('FRDLWEB_OID_CONNECT_API_ROUTE', 'oid-connect').'/'));
			$obj = OIDplusObject::findFitting($id);
				
			if (!$obj) {
				$obj = OIDplusObject::parse($id);
			}
			
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
				 if (!$obj->userHasParentalWriteRights()){    		
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
				 if (!$obj->userHasReadRights()){    		
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
			
				   
				   
				
				   
		
	public function tree(array &$json, string $ra_email=null, bool $nonjs=false, string $req_goto=''): bool {
			return false;
	}

	/**
	 * @param string $request
	 * @return array|false
	 */
	public function tree_search(string $request) {
		$ary = array();
		return $ary;
	}			   
				   
				   
				   
  public function beforeObjectDelete(string $id) {}

	/**
	 * Implements interface INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_3
	 * @param string $id
	 * @return void
	 * @throws OIDplusException
	 */
	public function afterObjectDelete(string $id) {
		// Delete the attachment folder including all files in it (note: Subfolders are not possible)
//		OIDplus::db()->query("delete from ###attributes where oid = ?", array($id));
		//unlink($this->rdapServer_bootfile);
										
		$res = OIDplus::db()->query("select * from ###attr where " .
						                            "oid = ? ORDER BY a_subject ASC, a_verb ASC, a_object ASC", array($id));
		
		//$res->naturalSortByField('id');
		while ($row = $res->fetch_object()) {
			 OIDplus::db()->query("delete from ###attrlogstream where id_attribute = ?", array($row->id));			
		}
		
		OIDplus::db()->query("delete from ###attr where oid = ?", array($id));
	}

	/**
	 * Implements interface INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_3
	 * @param string $id
	 * @param array $params
	 * @return void
	 */
	public function beforeObjectUpdateSuperior(string $id, array &$params) {}

	/**
	 * Implements interface INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_3
	 * @param string $id
	 * @param array $params
	 * @return void
	 */
	public function afterObjectUpdateSuperior(string $id, array &$params) {}

	/**
	 * Implements interface INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_3
	 * @param string $id
	 * @param array $params
	 * @return void
	 */
	public function beforeObjectUpdateSelf(string $id, array &$params) {}

	/**
	 * Implements interface INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_3
	 * @param string $id
	 * @param array $params
	 * @return void
	 */
	public function afterObjectUpdateSelf(string $id, array &$params) {}

	/**
	 * Implements interface INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_3
	 * @param string $id
	 * @param array $params
	 * @return void
	 */
	public function beforeObjectInsert(string $id, array &$params) {}

	/**
	 * Implements interface INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_3
	 * @param string $id
	 * @param array $params
	 * @return void
	 */
	public function afterObjectInsert(string $id, array &$params) {}
			   
	
}
