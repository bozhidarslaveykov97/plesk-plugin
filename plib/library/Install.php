<?php

/*
 * https://docs.plesk.com/en-US/onyx/api-rpc/about-xml-api/reference/managing-databases/creating-database-users/creating-multiple-database-users.34472/#creating-a-database-user
 */

class Modules_Microweber_Install {

protected $_logger;
    protected $_appLatestVersionFolder = false;
    protected $_overwrite = true;
    protected $_domainId;
    protected $_type = 'default';
    protected $_databaseDriver = 'mysql';
    protected $_email = 'encrypt@microweber.com';
    protected $_username = '';
    protected $_password = '';
    protected $_path = false;
    
    public function __construct() {
    	
    	$this->_logger = new Modules_Microweber_Logger();
    	$this->_appLatestVersionFolder = Modules_Microweber_Config::getAppLatestVersionFolder();
    }
    
    public function setPath($path) {
    	$this->_path = $path;	
    }
    
    public function setDomainId($id) {
        $this->_domainId = $id;
    }

    public function setType($type) {
        $this->_type = $type;
    }
    
    public function setDatabaseDriver($driver) {
    	$this->_databaseDriver = $driver;
    }
    
    public function setEmail($email) {
    	$this->_email = $email;
    }
    
    public function setUsername($username) {
    	$this->_username = $username;
    }
    
    public function setPassword($password) {
    	$this->_password = $password;
    }

    public function run() {
        
    	$domain = Modules_Microweber_Domain::getUserDomainById($this->_domainId);
        
        if (empty($domain->getName())) {
            throw new \Exception('Domain not found.');
        }
	    
	// Add SSL
	try {
		$artisan = pm_ApiCli::callSbin('encrypt_domain.sh', [$domain->getName(), $this->_email]);
		$this->_logger->write('Encrypt domain log for: ' . $domain->getName() . '<br />' . $artisan['stdout']. '<br /><br />');
		$this->_logger->write('Success instalation SSL for domain: ' . $domain->getName());
	} catch(\Exception $e) {
		$this->_logger->write('Can\'t install SSL for domain: ' . $domain->getName());
		$this->_logger->write('Error: ' . $e->getMessage());
	}
	    
        $this->_logger->write('Start installing Microweber on domain: ' . $domain->getName());
        
        $dbName =  str_replace('.', '', $domain->getName());
        $dbName = substr($dbName, 0, 9);
        $dbName .= '_'.date('His');  
        $dbUsername = $dbName;
        $dbPassword = $this->_getRandomPassword(12);
        
        if ($this->_databaseDriver == 'mysql') {
        	
        	$this->_logger->write('Create database for domain: ' . $domain->getName());
        	
	        $dbManager = new Modules_Microweber_DatabaseManager();
	        $dbManager->setDomainId($domain->getId());
	
	        $newDb = $dbManager->createDatabase($dbName);
	
	        if (isset($newDb['database']['add-db']['result']['errtext'])) {
	            throw new \Exception($newDb['database']['add-db']['result']['errtext']);
	        }
	
	        if (isset($newDb['database']['add-db']['result']['id'])) {
	            $dbId = $newDb['database']['add-db']['result']['id'];
	        }
	
	        if (!$dbId) {
	            throw new \Exception('Can\'t create database.');
	        }
	
	        if ($dbId) {
	        	$newUser = $dbManager->createUser($dbId, $dbUsername, $dbPassword);
	        }
	
	        if (isset($newUser['database']['add-db-user']['result']['errtext'])) {
	            throw new \Exception($newUser['database']['add-db-user']['result']['errtext']);
	        }
        }

        $domainDocumentRoot = $domain->getDocumentRoot(); 
        
        if ($this->_path) {
        	$domainDocumentRoot = $domainDocumentRoot . '/'.$this->_path;
        }
        
        $domainName = $domain->getName();
        $domainIsActive = $domain->isActive();
        $domainCreation = $domain->getProperty('cr_date');
        
        $this->_logger->write('Clear old folder on domain: ' . $domain->getName());
        
        // Clear domain files if exists
        pm_ApiCli::callSbin('prepair_domain_folder.sh', [$domainDocumentRoot]);
       	
        if ($this->_type == 'symlink') {
        	
        	// First we will make a directories
        	foreach ($this->_getDirsToMake() as $dir) {
        		pm_ApiCli::callSbin('create_dir.sh', [$domainDocumentRoot . '/' . $dir]);
        	}
        	
        	foreach ($this->_getFilesForSymlinking() as $folder) {
        		
        		$scriptDirOrFile = $this->_appLatestVersionFolder . '/' . $folder;
        		$domainDirOrFile = $domainDocumentRoot .'/'. $folder;
        		
        		$result = pm_ApiCli::callSbin('create_symlink.sh', [$scriptDirOrFile, $domainDirOrFile], pm_ApiCli::RESULT_FULL);
        		
        	}
        	
        	// And then we will copy files
        	foreach ($this->_getFilesForCopy() as $file) {
        		pm_ApiCli::callSbin('copy_file.sh', [$this->_appLatestVersionFolder . '/' . $file, $domainDocumentRoot . '/' . $file]);
        	}
        } else {
        	pm_ApiCli::callSbin('rsync_two_dirs.sh', [$this->_appLatestVersionFolder . '/', $domainDocumentRoot]);
        }
        
        $adminEmail = 'encrypt@microweber.com';
        $adminPassword = '1';
        $adminUsername = '1';
        
        if (!empty($this->_email)) {
        	$adminEmail = $this->_email;
        }
        if (!empty($this->_password)) {
        	$adminPassword = $this->_password;
        }
        if (!empty($this->_username)) {
        	$adminUsername = $this->_username;
        }
        
        if ($this->_databaseDriver == 'mysql') {
	        $dbHost = '127.0.0.1';
	        $dbPort = '3306';
        } else {
        	$dbHost = 'localhost';
        	$dbPort = '';
        	$dbName = $domainDocumentRoot . '/storage/database1.sqlite';
        }
        
        $whmcsConnector = new Modules_Microweber_WhmcsConnector();
        $whmcsConnector->setDomainName($domainName);
        
        $installArguments = array();
        
        $installArguments[] =  $adminEmail;
        $installArguments[] =  $adminUsername;
        $installArguments[] =  $adminPassword;
        
        $installArguments[] = $dbHost;
        $installArguments[] = $dbName;
        $installArguments[] = $dbUsername;
        $installArguments[] = $dbPassword;
        $installArguments[] = $this->_databaseDriver;
        $installArguments[] = '-p mw_';
        $installArguments[] = '-t ' . $whmcsConnector->getSelectedTemplate();
        $installArguments[] = '-d 1';
        if (!pm_Session::getClient()->isAdmin()) {
       		$installArguments[] = '-c 1';
        }
        
        $installArguments = implode(' ', $installArguments);
		
        $command = $domainDocumentRoot . '/artisan microweber:install ' . $installArguments;
        
        $artisan = pm_ApiCli::callSbin('run_php.sh', [$command]);  
      	
        $this->_logger->write('Microweber install log for: ' . $domain->getName() . '<br />' . $artisan['stdout']. '<br /><br />');
        
        // Repair domain permission
        Modules_Microweber_Config::fixDomainPermissions($domain->getId());
       
        Modules_Microweber_WhiteLabel::updateWhiteLabelDomainById($domain->getId());
        
        return array('success'=>true, 'log'=> $artisan['stdout']);
        
    }
    
    private function _getDirsToMake() {
    	
    	$dirs = array();
    	
    	// Storage dirs
    	$dirs[] = 'storage';
    	$dirs[] = 'storage/framework';
    	$dirs[] = 'storage/framework/sessions';
    	$dirs[] = 'storage/framework/views';
    	$dirs[] = 'storage/cache';
    	$dirs[] = 'storage/logs';
    	$dirs[] = 'storage/app';
    	
    	// Bootstrap dirs
    	$dirs[] = 'bootstrap';
    	$dirs[] = 'bootstrap/cache';
    	
    	// User files dirs
    	$dirs[] = 'userfiles';
    	$dirs[] = 'userfiles/media';
    	$dirs[] = 'userfiles/modules';
    	$dirs[] = 'userfiles/templates';
    	
    	// Config dir
    	$dirs[] = 'config';
    	
    	return $dirs;
    }
    
    private function _getFilesForSymlinking() {
    	
    	$files = array();
    	
    	$files[] = 'vendor';
    	$files[] = 'src';
    	$files[] = 'resources';
    	$files[] = 'database';
    	$files[] = 'userfiles/modules';
    	$files[] = 'userfiles/templates';
    	$files[] = 'userfiles/elements';
    	
    	return $files;
    }
    
    /**
     * This is the files when symlinking app.
     * @return string[]
     */
    private function _getFilesForCopy() {
    	
    	$files = array();
    	
    	// Index
    	$files[] = 'version.txt';
    	$files[] = 'index.php';
    	$files[] = '.htaccess';
    	$files[] = 'favicon.ico';
    	$files[] = 'composer.json';
    	$files[] = 'artisan';
    	
    	// Config folder
    	$files[] = 'config/.htaccess';
    	$files[] = 'config/database.php';
    	$files[] = 'config/app.php';
    	$files[] = 'config/auth.php';
    	$files[] = 'config/cache.php';
    	$files[] = 'config/compile.php';
    	$files[] = 'config/filesystems.php';
    	$files[] = 'config/queue.php';
    	$files[] = 'config/services.php';
    	$files[] = 'config/view.php';
    	$files[] = 'config/workbench.php';
    	$files[] = 'config/hashing.php';
    	$files[] = 'config/mail.php';
    	$files[] = 'config/session.php';
    	
    	// Bootstrap folder
    	$files[] = 'bootstrap/.htaccess';
    	$files[] = 'bootstrap/app.php';
    	$files[] = 'bootstrap/autoload.php';
    	
    	return $files;
    }
    
    private function _getRandomPassword($length = 16)
    {
    	$alphabet = 'ghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    	$pass = array();
    	$alphaLength = strlen($alphabet) - 1;
    	for ($i = 0; $i < $length; $i++) {
    		$n = rand(0, $alphaLength);
    		$pass[] = $alphabet[$n];
    	}
    	return implode($pass);
    }
    
}
