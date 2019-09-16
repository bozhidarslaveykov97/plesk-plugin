<?php
/**
* Author: Bozhidar Slaveykov
* @email: info@credocart.com
* Plesk auto app installer
*/

class Modules_Credocart_EventListener implements EventListener
{
    public function handleEvent($objectType, $objectId, $action, $oldValue, $newValue)
    {
    	// https://github.com/plesk/ext-aps-autoprovision/blob/master/src/plib/library/EventListener.php
    	
    	if ($action == 'phys_hosting_create' && pm_Settings::get('installation_settings') == 'auto') {
    		
    		$domain = new pm_Domain($objectId);
    		
    		$planItems = $domain->getPlanItems();
    		
    		if (is_array($planItems) && count($planItems) > 0 && isset(Modules_Microweber_Config::getPlanItems()[$planItems[0]])) {
    		
	    		try {
	    			
		    		$newInstallation = new Modules_Microweber_Install();
		    		$newInstallation->setDomainId($objectId);
		    		$newInstallation->setType(pm_Settings::get('installation_type'));
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
