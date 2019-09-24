<?php

// Copyright 1999-2017. Parallels IP Holdings GmbH.
class Modules_Microweber_Domain
{

	protected static $_excludeDomains = array('microweber.com', 'microweberapi.com');
	
	public static function getDomains()
	{
		$httpHost = '';
		if (isset($_SERVER['HTTP_HOST'])) {
			$httpHost = $_SERVER['HTTP_HOST'];
			$exp = explode(":", $httpHost);
			if (isset($exp[0])) {
				$httpHost = $exp[0];
				
				self::$_excludeDomains[] = $httpHost;
			}
		}
		
		if (pm_Session::getClient()->isAdmin()) {
			$domains = pm_Domain::getAllDomains();
		} else {
			$domains = pm_Domain::getDomainsByClient(pm_Session::getClient());
		}
		
		$readyDomains = array();
		foreach ($domains as $domain) {
			if (in_array($domain->getName(), self::$_excludeDomains)) {
				continue;
			}
			$readyDomains[] = $domain;
		}

		return $readyDomains;
	}

	public static function getUserDomainById($domainId)
	{
		foreach (self::getDomains() as $domain) {
			if ($domain->getId() == $domainId) {
				return $domain;
			}
		}

		throw new Exception('You don\'t have permission to manage this domain');
	}
}