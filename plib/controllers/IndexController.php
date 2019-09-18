<?php

class IndexController extends pm_Controller_Action {

    protected $_accessLevel = [
        'admin'
    ];
    protected $_moduleName = '';
	protected $_modulePath = false;
	
    public function init() {
    	
        parent::init();
        
        // Find module path
        $this->_modulePath = substr(realpath(dirname(__FILE__)), 0, - strlen('controllers'));
       	
        // Get configuration file
        $configuration = include($this->_modulePath . 'configuration.php');
        
        // Set module name
        $this->_moduleName = $configuration['name'];
        
        // Set module name to views
        $this->view->moduleName = $this->_moduleName;
        
        // Init tabs for all actions
        $this->view->tabs = [
            [
                'title' => 'Domains',
                'action' => 'index'
            ],
            [
                'title' => 'Install',
                'action' => 'install'
            ],
            [
                'title' => 'Versions',
                'action' => 'versions'
            ],
        	[
        		'title' => 'White Label',
        		'action' => 'whitelabel'
        	],
            [
                'title' => 'Settings',
                'action' => 'settings',
            ],
        	[
        		'title' => 'Logs',
        		'action' => 'logs',
        	]
        ];
    }

    public function indexAction() {
    	
    	$this->_checkIsCorrect();
    	
        $this->view->pageTitle = $this->_moduleName . ' - Domains';
        $this->view->list = $this->_getDomainsList();
    }
    
    
    public function logsAction() {
    	
    	$this->_checkIsCorrect();
    	
    	$this->view->pageTitle = $this->_moduleName . ' - Logs';
    	
    	$logger = new Modules_Microweber_Logger();
    	
    	$log =  $logger->read();
    	
    	$this->view->log = $log;
    	
    }
    
    public function versionsAction() {
    	
    	$this->_checkIsCorrect();

        $release = $this->_getRelease();
        
        $this->view->pageTitle = $this->_moduleName . ' - Versions';
		
        $this->view->latestVersion = 'unknown';
        $this->view->currentVersion = $this->_getCurrentVersion();
        $this->view->latestDownloadDate = $this->_getCurrentVersionLastDownloadDateTime();
        
        if (!empty($release)) {
        	$this->view->latestVersion = $release['version']; 
        }
        
        $this->view->updateLink = pm_Context::getBaseUrl() . 'index.php/index/update';
        $this->view->updateTemplatesLink = pm_Context::getBaseUrl() . 'index.php/index/update_templates';
    }
    
    public function whitelabelAction() {
    	
    	$this->_checkIsCorrect();
    	
    	$this->view->pageTitle = $this->_moduleName . ' - White Label';
    	
    	$this->view->updatePremiumTemplatesLink = pm_Context::getBaseUrl() . 'index.php/index/update_premium_templates';
    	
    	// WL - white label
    	
    	$form = new pm_Form_Simple();
    	$form->addElement('text', 'wl_key', [
    		'label' => 'White Label Key',
    		'value' => pm_Settings::get('wl_key'),
    		'placeholder'=> 'Place your microweber white label key.'
    	]);
    	$form->addElement('text', 'wl_brand_name', [
    		'label' => 'Brand Name',
    		'value' => pm_Settings::get('wl_brand_name'),
    		'placeholder'=> 'Enter the name of your company.'
    	]);
    	$form->addElement('text', 'wl_admin_login_url', [
    		'label' => 'Admin login - White Label URL?',
    		'value' => pm_Settings::get('wl_admin_login_url'),
    		'placeholder'=> 'Enter website url of your company.'
    	]);
    	$form->addElement('text', 'wl_contact_page', [
    		'label' => 'Enable support links?',
    		'value' => pm_Settings::get('wl_contact_page'),
    		'placeholder'=> 'Enter url of your contact page'
    	]);
    	$form->addElement('checkbox', 'wl_enable_support_links', 
    		array(
    			'label' => 'Enable support links', 'value' => pm_Settings::get('wl_enable_support_links')
    		)
    	);
    	$form->addElement('textarea', 'wl_powered_by', 
    		array(
    			'label' => 'Enter "Powered by" text', 
    			'value' => pm_Settings::get('wl_powered_by'), 
    			'rows' => 3
    		)
    	);
    	$form->addElement('checkbox', 'wl_hide_powered_by_link', 
    		array(
    			'label' => 'Hide "Powered by" link', 'value' => pm_Settings::get('wl_hide_powered_by_link')
    		)
    	);
    	$form->addElement('text', 'wl_logo_admin_panel', [
    		'label' => 'Logo for Admin panel (size: 180x35px)',
    		'value' => pm_Settings::get('wl_logo_admin_panel'),
    		'placeholder'=> ''
    	]);
    	$form->addElement('text', 'wl_logo_live_edit_toolbar', [
    		'label' => 'Logo for Live-Edit toolbar (size: 50x50px)',
    		'value' => pm_Settings::get('wl_logo_live_edit_toolbar'),
    		'placeholder'=> ''
    	]);
    	$form->addElement('text', 'wl_logo_login_screen', [
    		'label' => 'Logo for Login screen (max width: 290px)',
    		'value' => pm_Settings::get('wl_logo_login_screen'),
    		'placeholder'=> ''
    	]);
    	$form->addElement('checkbox', 'wl_disable_microweber_marketplace',
    		array(
    			'label' => 'Disable Microweber Marketplace', 'value' => pm_Settings::get('wl_disable_microweber_marketplace')
    		)
    	);
    	
    	$form->addControlButtons([
    		'cancelLink' => pm_Context::getModulesListUrl(),
    	]);
    	
    	if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
    		
    		// Check license and save it to pm settings
    		$licenseCheck = Modules_Microweber_LicenseData::getLicenseData($form->getValue('wl_key'));
    		
    		if (isset($licenseCheck['status'])) {
    			pm_Settings::set('wl_license_data', json_encode($licenseCheck));
    		}
    		
    		pm_Settings::set('wl_key', $form->getValue('wl_key'));
    		pm_Settings::set('wl_brand_name', $form->getValue('wl_brand_name'));
    		pm_Settings::set('wl_admin_login_url', $form->getValue('wl_admin_login_url'));
    		pm_Settings::set('wl_contact_page', $form->getValue('wl_contact_page'));
    		pm_Settings::set('wl_enable_support_links', $form->getValue('wl_enable_support_links'));
    		pm_Settings::set('wl_powered_by', $form->getValue('wl_powered_by'));
    		pm_Settings::set('wl_hide_powered_by_link', $form->getValue('wl_hide_powered_by_link'));
    		pm_Settings::set('wl_logo_admin_panel', $form->getValue('wl_logo_admin_panel'));
    		pm_Settings::set('wl_logo_live_edit_toolbar', $form->getValue('wl_logo_live_edit_toolbar'));
    		pm_Settings::set('wl_logo_login_screen', $form->getValue('wl_logo_login_screen'));
    		pm_Settings::set('wl_disable_microweber_marketplace', $form->getValue('wl_disable_microweber_marketplace'));
    		
    		$this->_status->addMessage('info', 'Settings was successfully saved.');
    		$this->_helper->json(['redirect' => pm_Context::getBaseUrl() . 'index.php/index/whitelabel']);
    	}
    	
    	// Show is licensed
    	$this->_getLicensedView();
    	
    	$this->view->form = $form;
    }

    public function updateAction() {
    	
    	$release = $this->_getRelease();
    	if (empty($release)) {
    		return;
    	}
    	
    	$downloadLog = pm_ApiCli::callSbin('unzip_app_version.sh',[base64_encode($release['url']), $this->_getSharedFolderAppName()])['stdout'];
    	
    	$this->_status->addMessage('info', $downloadLog);
    	
    	header("Location: " . pm_Context::getBaseUrl() . 'index.php/index/versions');
    	exit;
    }
    
    public function updatetemplatesAction() {
    	
    	$templatesUrl = $this->_getTemplatesUrl();
    	
    	$downloadLog = pm_ApiCli::callSbin('unzip_app_templates.sh',[base64_encode($templatesUrl), $this->_getSharedFolderAppName()])['stdout'];
    	
    	$this->_status->addMessage('info', $downloadLog);
    	
    	header("Location: " . pm_Context::getBaseUrl() . 'index.php/index/versions');
    	exit;
    }
    
    public function updatepremiumtemplatesAction() {
    	
    	$templatesUrl = $this->_getPremiumTemplatesUrl();
    	
    	if ($templatesUrl) {
	    	$downloadLog = pm_ApiCli::callSbin('unzip_app_templates.sh',[base64_encode($templatesUrl), $this->_getSharedFolderAppName()])['stdout'];
	    	
	    	$this->_status->addMessage('info', $downloadLog);
    	}
    	
    	header("Location: " . pm_Context::getBaseUrl() . 'index.php/index/whitelabel');
    	exit;
    }
	
    public function installAction() {

    	$this->_checkIsCorrect();
    	
        $this->view->pageTitle = $this->_moduleName . ' - Install';

        $domainsSelect = [];
        foreach (pm_Domain::getAllDomains() as $domain) {

            $domainId = $domain->getId();
            $domainName = $domain->getName();

            $domainsSelect[$domainId] = $domainName;
        }

        $form = new pm_Form_Simple();
        
        $form->addElement('select', 'installation_domain', [
            'label' => 'Domain',
            'multiOptions' => $domainsSelect,
            'required' => true,
        ]);
        
        $form->addElement('radio', 'installation_type', [
            'label' => 'Installation Type',
            'multiOptions' =>
            [
                'default' => 'Default',
                'symlink' => 'Sym-Linked'
            ],
            'value' => pm_Settings::get('installation_type'),
            'required' => true,
        ]);
        
        $form->addElement('select', 'installation_database_driver', [
        	'label' => 'Database Driver',
        	'multiOptions' => ['mysql' => 'MySQL', 'sqlite' => 'SQL Lite'],
        	'value' => pm_Settings::get('installation_database_driver'),
        	'required' => true,
        ]);
        
        $form->addElement('text', 'installation_email', [
        	'label' => 'Admin Email',
        ]);
        $form->addElement('text', 'installation_username', [
        	'label' => 'Admin Username',
        ]);
        $form->addElement('password', 'installation_password', [
        	'label' => 'Admin Password',
        ]);

        $form->addControlButtons([
            'cancelLink' => pm_Context::getModulesListUrl(),
        ]);

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            $post = $this->getRequest()->getPost();
			
            try {
            	
            	$newInstallation = new Modules_Microweber_Install();
            	$newInstallation->setDomainId($post['installation_domain']);
            	$newInstallation->setType($post['installation_type']);
            	$newInstallation->setDatabaseDriver($post['installation_database_driver']);
            	
            	if (!empty($post['installation_email'])) {
            		$newInstallation->setEmail($post['installation_email']);
            	}
            	
            	if (!empty($post['installation_username'])) {
            		$newInstallation->setUsername($post['installation_username']);
            	}
            	
            	if (!empty($post['installation_password'])) {
            		$newInstallation->setPassword($post['installation_password']);
            	}
            	
            	$newInstallation->run();
            	
            	$this->_status->addMessage('info', 'App is installed successfuly on selected domain.');
            } catch (Exception $e) {
            	$this->_status->addMessage('error', $e->getMessage());
            }
            
            $this->_helper->json(['redirect' => pm_Context::getBaseUrl(). 'index.php/index/index']);
        }

        $this->view->form = $form;
    }

    public function settingsAction() {

        $this->view->pageTitle = $this->_moduleName . ' - Settings';

        $form = new pm_Form_Simple();
        $form->addElement('radio', 'installation_settings', [
            'label' => 'Installation Settings',
            'multiOptions' =>
            [
                'auto' => 'Automaticlly install '.$this->_moduleName.' on new domains creation.',
            	// 'manual' => 'Allow users to Manualy install '.$this->_moduleName.' from Plesk.',
                'disabled' => 'Disabled for all users'
            ],
            'value' => pm_Settings::get('installation_settings'),
            'required' => true,
        ]);

        $form->addElement('radio', 'installation_type', [
            'label' => 'Installation Type',
            'multiOptions' =>
            [
                'default' => 'Default',
                'symlink' => 'Sym-Linked (saves a big amount of disk space)'
            ],
            'value' => pm_Settings::get('installation_type'),
            'required' => true,
        ]);


        $form->addElement('select', 'installation_database_driver', [
            'label' => 'Database Driver',
            'multiOptions' => ['mysql' => 'MySQL', 'sqlite' => 'SQL Lite'],
            'value' => pm_Settings::get('installation_database_driver'),
            'required' => true,
        ]);
        
        $form->addElement('text', 'update_app_url', [
        	'label' => 'Update App Url',
        	'value' => pm_Settings::get('update_app_url'),
        	'required' => true,
        ]);
        
        $form->addElement('text', 'whmcs_url', [
        	'label' => 'WHMCS Url',
        	'value' => pm_Settings::get('whmcs_url'),
        	'required' => true,
        ]);
        
        /*
        $form->addElement('text', 'download_latest_version_app_url', [
        	'label' => 'Download latest version app url',
        	'value' => pm_Settings::get('download_latest_version_app_url'),
        	'required' => true,
        ]);
        */
        
        $form->addElement('text', 'shared_folder_app_name', [
        	'label' => 'Shared folder app name (Folder name for symlinks)',
        	'value' => $this->_getSharedFolderAppName(),
        	'required' => true,
        ]);

        $form->addControlButtons([
            'cancelLink' => pm_Context::getModulesListUrl(),
        ]);

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
			
        	$success = true;
        	
            // Form proccessing
            pm_Settings::set('installation_settings', $form->getValue('installation_settings'));
            pm_Settings::set('installation_type', $form->getValue('installation_type'));
            pm_Settings::set('installation_database_driver', $form->getValue('installation_database_driver'));
            
            pm_Settings::set('update_app_url', $form->getValue('update_app_url'));
            pm_Settings::set('whmcs_url', $form->getValue('whmcs_url'));
            
            // pm_Settings::set('download_latest_version_app_url', $form->getValue('download_latest_version_app_url'));
            pm_Settings::set('shared_folder_app_name', $form->getValue('shared_folder_app_name'));

            $release = $this->_getRelease();
            if (empty($release)) {
            	$this->_status->addMessage('error', 'Can\'t get latest version from selected download url.');
            	$success = false;
            }
            
            if ($success) {
            	$this->_status->addMessage('info', 'Settings was successfully saved.');
            }
           
            $this->_helper->json(['redirect' => pm_Context::getBaseUrl() . 'index.php/index/settings']);
        }

        $this->view->form = $form;
    }
    
    private function _getLicensedView() 
   	{
    	$this->view->isLicensed = false;
    	
    	$licenseData = pm_Settings::get('wl_license_data');
    	
    	if (!empty($licenseData)) {
    		
    		$licenseData = json_decode($licenseData, TRUE);
    		
    		if ($licenseData['status'] == 'active') {
    			
    			$this->view->isLicensed = true;
    			$this->view->dueOn = $licenseData['due_on'];
    			$this->view->registeredName = $licenseData['registered_name'];
    			$this->view->relName = $licenseData['rel_name'];
    			$this->view->regOn = date("Y-m-d", strtotime($licenseData['reg_on']));
    			$this->view->billingCycle = $licenseData['billing_cycle'];
    			
    		}
    	}
    }
    
    private function _checkIsCorrect() 
    {
    	if (empty(pm_Settings::get('shared_folder_app_name'))) {
    		$this->_status->addMessage('warning', 'First you must to fill your app settings.');
    		header("Location: " . pm_Context::getBaseUrl() . 'index.php/index/settings');
    		exit;
    	}
    }
    
    private function _getCurrentVersionLastDownloadDateTime()
    {
    	$version_file = file_exists($this->_getSharedFolderPath() . 'version.txt');
    	if ($version_file) {
    		$version = filectime($this->_getSharedFolderPath() . 'version.txt');
    		if ($version) {
    			return date('Y-m-d H:i:s', $version);
    		}
    	}
    }
    private function _getCurrentVersion()
    {
    	$versionFile = file_exists($this->_getSharedFolderPath() . 'version.txt');
    	
    	$version = 'unknown';
    	if ($versionFile) {
    		$version = file_get_contents($this->_getSharedFolderPath() . 'version.txt');
    		$version = strip_tags($version);
    	}
    	return $version;
    }
	
    private function _getSharedFolderPath() {
    	return '/usr/share/'.strtolower($this->_moduleName).'/latest/';
    }
    
    private function _getSharedFolderAppName() {
    	
    	$sharedFolderAppName = pm_Settings::get('shared_folder_app_name');
    	
    	if (empty($sharedFolderAppName)) {
    		$sharedFolderAppName = strtolower($this->_moduleName);
    	}
    	
    	return $sharedFolderAppName;
    }
    
    private function _getDomainsList() {

        $data = [];
        $domains = pm_Domain::getAllDomains();

        $i = 0;
        foreach ($domains as $domain) {
        	
            $domainDocumentRoot = $domain->getDocumentRoot();
            $domainName = $domain->getName();
            $domainIsActive = $domain->isActive();
            $domainCreation = $domain->getProperty('cr_date');
			
            $appVersion = 'unknown';
            $installationType = 'unknown';
            
            $fileManager = new pm_FileManager($domain->getId());
            
            if ($fileManager->fileExists($domain->getDocumentRoot() . '/version.txt')) {
            	$appVersion = $fileManager->fileGetContents($domain->getDocumentRoot() . '/version.txt');
            }
            
            if (is_link($domain->getDocumentRoot() . '/vendor')) {
            	$installationType = 'Symlinked';
            } else {
            	$installationType = 'Standalone';
            }
            
            $data[$i] = [
                'domain' => '<a href="#">' . $domainName . '</a>',
                'created_date' => $domainCreation,
                'type' => $installationType,
            	'app_version' => $appVersion,
                'document_root' => $domainDocumentRoot,
                'active' => ($domainIsActive ? 'Yes' : 'No')
            ];
            $i++;
        }

        $options = [
            'defaultSortField' => 'active',
            'defaultSortDirection' => pm_View_List_Simple::SORT_DIR_DOWN,
        ];
        $list = new pm_View_List_Simple($this->view, $this->_request, $options);
        $list->setData($data);
        $list->setColumns([
            pm_View_List_Simple::COLUMN_SELECTION,
            'domain' => [
                'title' => 'Domain',
                'noEscape' => true,
                'searchable' => true,
            ],
            'created_date' => [
                'title' => 'Created at',
                'noEscape' => true,
                'searchable' => true,
            ],
            'type' => [
                'title' => 'Type',
                'noEscape' => true,
                'sortable' => false,
            ],
        	'app_version' => [
        		'title' => 'App Version',
        		'noEscape' => true,
        		'sortable' => false,
        	],
            'active' => [
                'title' => 'Active',
                'noEscape' => true,
                'sortable' => false,
            ],
            'document_root' => [
                'title' => 'Document Root',
                'noEscape' => true,
                'sortable' => false,
            ],
        ]);

        // Take into account listDataAction corresponds to the URL /list-data/
        $list->setDataUrl(['action' => 'list-data']);

        return $list;
    }
    
    private function _getTemplatesUrl() {
    	
    	$templatesUrl = Modules_Microweber_Config::getUpdateAppUrl();
    	$templatesUrl .= '?api_function=download&get_extra_content=1&name=templates';
    	
    	return $templatesUrl;
    }
    
    private function _getPremiumTemplatesUrl() {
    	
    	$templatesUrl = Modules_Microweber_Config::getUpdateAppUrl();
    	$templatesUrl .= '/?api_function=get_download_link&get_extra_content=1&name=templates_paid&license_key=' . pm_Settings::get('wl_key');
    	
    	$json = $this->_getJson($templatesUrl);
    	
    	if (isset($json['url'])) {
    		return $json['url'];
    	}
    }

    private function _getRelease() {

    	$releaseUrl = Modules_Microweber_Config::getUpdateAppUrl();
    	$releaseUrl .= '?api_function=get_download_link&get_last_version=';
    
    	return $this->_getJson($releaseUrl);
    }
    
    private function _getJson($url) {
    	
        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url);
        curl_setopt($tuCurl, CURLOPT_VERBOSE, 0);
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_SSL_VERIFYPEER, false);

        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $debug = 'Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url'];
        } else {
            $debug = 'Curl error: ' . curl_error($tuCurl);
        }

        curl_close($tuCurl);

        $json = json_decode($tuData, TRUE);
		
        return $json;
    }

}
