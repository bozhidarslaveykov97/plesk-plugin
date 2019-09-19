<?php
// Copyright 1999-2017. Parallels IP Holdings GmbH.

class Modules_Microweber_Config
{
	public static function getPlanItems()
	{
		return [
			'microweber' => 'Install Microweber',
			'microweber_lite' => 'Install Microweber Lite'
		];
	}

	public static function getUpdateAppUrl()
	{
		return pm_Settings::get('update_app_url', 'https://update.microweberapi.com/');
	}

	public static function getWhmcsUrl()
	{
		return pm_Settings::get('whmcs_url', 'https://members.microweber.com/');
	}
}