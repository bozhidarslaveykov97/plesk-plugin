<?php
/**
* Author: Bozhidar Slaveykov
* @email: info@credocart.com
* Plesk auto app installer
*/

class Modules_Microweber_EventListener implements EventListener
{
    public function handleEvent($objectType, $objectId, $action, $oldValue, $newValue)
    {
    	// https://github.com/plesk/ext-aps-autoprovision/blob/master/src/plib/library/EventListener.php
    	
    	if ($action == 'phys_hosting_create' && pm_Settings::get('installation_settings') == 'auto') {
    		
    		/*
    		$newLogger = new Modules_Microweber_Logger();
    		$newLogger->write('object type ' . print_r($objectType, true));
    		$newLogger->write('object id' . print_r($objectId, true));
    		$newLogger->write('action' . print_r($action, true));
    		$newLogger->write('old value' . print_r($oldValue, true));
    		$newLogger->write('new value' . print_r($newValue, true));
    		
    		$newLogger->write('System User' . print_r($newValue['System User'], true));
    		$newLogger->write('System User Password' . print_r($newValue['System User Password'], true));
    		*/
    		
    		$domain = new pm_Domain($objectId);
    		
    		$planItems = $domain->getPlanItems();
    		
    		if (is_array($planItems) && count($planItems) > 0 && isset(Modules_Microweber_Config::getPlanItems()[$planItems[0]])) {
    		
	    		try {
	    			
	    			// Wait to restart web server
	    			sleep(10);
	    			
		    		$newInstallation = new Modules_Microweber_Install();
		    		$newInstallation->setDomainId($objectId);
		    		$newInstallation->setType(pm_Settings::get('installation_type'));
		    		$newInstallation->setDatabaseDriver(pm_Settings::get('installation_database_driver'));
		    		$newInstallation->setUsername($newValue['System User']);
		    		$newInstallation->setEmail($newValue['System User']);
		    		$newInstallation->setPassword($newValue['System User Password']);
		    		$newInstallation->run();
		    		
	    		} catch (pm_Exception $e) {
	    			pm_Settings::set('domain_issue_' . $objectId, pm_Locale::lmsg('microweberError',
	    					['domain' => $domain->getDisplayName(), 'package' => $planItems[0], 'error' => $e->getMessage()]
	    				)
	    			);
	    		}
    		}
    		
    	} elseif ($action == 'domain_delete' && pm_Settings::get('domain_issue_' . $objectId)) {
    		pm_Settings::set('domain_issue_' . $objectId, null);
    	}
    }
}

return new Modules_Microweber_EventListener();