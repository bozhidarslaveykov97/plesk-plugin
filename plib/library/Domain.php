<?php

// Copyright 1999-2017. Parallels IP Holdings GmbH.
class Modules_Microweber_Domain
{

	public static function getDomains()
	{
		if (pm_Session::getClient()->isAdmin()) {
			$domains = pm_Domain::getAllDomains();
		} else {
			$domains = pm_Domain::getDomainsByClient(pm_Session::getClient());
		}

		return $domains;
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