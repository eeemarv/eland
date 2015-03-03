<?php
/**
 * Class to perform eLAS hosting manipulations
 *
 * This file is part of eLAS http://elas.vsbnet.be
 * 
 * Copyright(C) 2009 Guy Van Sanden <guy@vsbnet.be>
 *
 * eLAS is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/
//require_once($rootpath."includes/inc_default.php");
//require_once($rootpath."includes/inc_adoconnection.php");

/** Provided functions:
  * check_contract()		Check if the hosting contract has expired, returns 1 for a valid contract
  * check_contractgrace()	Check if we are still inside the grace period, return 1 for a valid or graceperiod contract
  * check_hostinglock()		Check if the installation is locked, return 1 if locked
  * get_provider()		Get the Hosting provider details
  * get_contract()		Get the contract details
  * get_billingcontact()	Get the billing contact for the provider
*/

function get_provider(){
	global $configuration;
    global $baseurl;
	global $rootpath;
    if($configuration["hosting"]["enabled"] == 1){
                $providerxml = simplexml_load_file("$rootpath/sites/provider.xml");
				$provider["name"] = $providerxml->providername;
				$provider["url"] = $providerxml->providerurl;
				$provider["contact"] = $providerxml->providercontact;
				$provider["email"] = $providerxml->provideremail;
				$provider["billingemail"] = $providerxml->billingemail;
				$provider["esmurl"] = $providerxml->esmurl;
				return $provider;
	}
}

function get_contract(){
		global $rootpath;
        global $configuration;
        global $xmlconfig;
        global $baseurl;
		global $dirbase;
		global $provider;
		global $redis;
		                    
        if($configuration["hosting"]["enabled"] == 1){
			$rediskey = "esm::" .readconfigfromdb('systemtag') ."::contract::json";
			//echo "Getting $rediskey";
			
			$result = $redis->get($rediskey);
			
			//var_dump($result);
			
			$mycontract=json_decode($result);
			
			$contract["systemtag"] = $mycontract->systemtag;
			$contract["start"] = $mycontract->contractstart;
			$contract["end"] = $mycontract->contractend;
			$contract["paymenttype"] = $mycontract->paymenttype;
			$contract["period"] = $mycontract->contractperiod;
			$contract["cost"] = $mycontract->cost;
			$contract["graceperiod"] = $mycontract->gracedays;
			$contract["onsitecontact"] = $mycontract->sitecontact;
			$contract["onsiteemail"] = $mycontract->sitemail;
			$contract["supportcredits"] = $mycontract->supportcredits;
		return $contract;
	}
}
		

function check_contract(){
         // test if the hosting expired first
        global $configuration;
        global $baseurl;
		global $dirbase;
		global $rootpath;
        $now = time();
        if($configuration["hosting"]["enabled"] == 1){
			$contract = get_contract();
                $testdate = strtotime($contract['end']);
                if($now < $testdate){
                        $return = 1;
                } else {
                        $return = 0;
                }
        } else {
                $return = 1;
        }
        return $return;
}

function check_contractgrace(){
         // test if the hosting expired first
        global $configuration;
        global $baseurl;
		global $dirbase;
        $now = time();
        if($configuration["hosting"]["enabled"] == 1){
                $contractxml = simplexml_load_file("sites/$dirbase/hosting.xml");
                //print_r($contractxml);
                //echo $contractxml->contractend;
                $testdate = strtotime($contractxml->contractend) + ($contractxml->graceperiod * 24 * 60 * 60);
                //echo "Now: $now - Testdate: $testdate";
                if($now < $testdate){
                        $return = 1;
                } else {
                        $return = 0;
                }
        } else {
                $return = 1;
        }
        return $return;
}

