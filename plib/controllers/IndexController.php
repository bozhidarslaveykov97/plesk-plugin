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
                'title' => 'Settings',
                'action' => 'settings',
            ]
        ];
    }

    public function indexAction() {
        $this->view->pageTitle = $this->_moduleName . ' - Domains';
        $this->view->list = $this->_getDomainsList();
    }
    
    public function versionsAction() {

        $release = $this->_getRelease();
        
        $this->view->pageTitle = $this->_moduleName . ' - Versions';
		
        $this->view->latestVersion = 'unknown';
        $this->view->currentVersion = $this->_getCurrentVersion();
        $this->view->latestDownloadDate = $this->_getCurrentVersionLastDownloadDateTime();
        
        if (!empty($release)) {
        	$this->view->latestVersion = $release['version']; 
        }
        
        $this->view->updateLink = pm_Context::getBaseUrl() . 'index.php/index/update';

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

    public function testAction() {
        $newInstallation = new Modules_CredoCart_Install();
        $newInstallation->setDomainId(2);
        $newInstallation->setType('default');
        $newInstallation->run();
    }

    public function installAction() {

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
                'sym_linked' => 'Sym-Linked'
            ],
            'value' => pm_Settings::get('installation_type'),
            'required' => true,
        ]);

        $form->addControlButtons([
            'cancelLink' => pm_Context::getModulesListUrl(),
        ]);

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            $post = $this->getRequest()->getPost();
			
            $newInstallation = new Modules_CredoCart_Install();
            $newInstallation->setDomainId($post['installation_domain']);
            $newInstallation->setType($post['installation_type']);
            $newInstallation->run();
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
                'sym_linked' => 'Sym-Linked (saves a big amount of disk space)'
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
        
        
        $form->addElement('text', 'download_latest_version_app_url', [
        	'label' => 'Download latest version app url',
        	'value' => pm_Settings::get('download_latest_version_app_url'),
        	'required' => true,
        ]);
        
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
            
            pm_Settings::set('download_latest_version_app_url', $form->getValue('download_latest_version_app_url'));
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

            $data[$i] = [
                'domain' => '<a href="#">' . $domainName . '</a>',
                'created_date' => $domainCreation,
                'type' => 'Symlink',
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

    private function _getRelease() {

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, pm_Settings::get('download_latest_version_app_url'));
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
