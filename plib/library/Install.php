<?php

class Modules_Microweber_Install {

    protected $_appLatestVersionFolder = false;
    protected $_overwrite = true;
    protected $_domainId;
    protected $_type = 'default';
    protected $_databaseDriver = 'mysql';
    protected $_email = 'admin@microweber.com';
    protected $_username = '';
    protected $_password = '';
    protected $_path = false;
    protected $_progressLogger = false;
    
    public function __construct() {
    	$this->_appLatestVersionFolder = Modules_Microweber_Config::getAppLatestVersionFolder();
    }
    
    public function setProgressLogger($logger) {
    	$this->_progressLogger = $logger;
    }
    
    public function setProgress($progress) {
    	if (is_object($this->_progressLogger) && method_exists($this->_progressLogger, 'updateProgress')) {
    		$this->_progressLogger->updateProgress($progress);
    	}
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
    	
    	$this->setProgress(5);
    	
    	$domain = Modules_Microweber_Domain::getUserDomainById($this->_domainId);
        
        if (empty($domain->getName())) {
            throw new \Exception('Domain not found.');
        }
	    
        $this->setProgress(10);
        
        $fileManager = new \pm_FileManager($domain->getId());
        
		$sslEmail = 'admin@microweber.com';
		    
		// Add SSL
		try {
			pm_Log::debug('Start installign SSL for domain: ' . $domain->getName() . '; SSL Email: ' . $sslEmail);
			$artisan = pm_ApiCli::callSbin('encrypt_domain.sh', [$domain->getName(), $sslEmail]);
			pm_Log::debug('Encrypt domain log for: ' . $domain->getName() . '<br />' . $artisan['stdout']. '<br /><br />');
			pm_Log::debug('Success instalation SSL for domain: ' . $domain->getName());
		} catch(\Exception $e) {
			pm_Log::debug('Can\'t install SSL for domain: ' . $domain->getName());
			pm_Log::debug('Error: ' . $e->getMessage());
		}
		
		$this->setProgress(20);
	    
        pm_Log::debug('Start installing Microweber on domain: ' . $domain->getName());
        
        $dbName =  str_replace('.', '', $domain->getName());
        $dbName = substr($dbName, 0, 9);
        $dbName .= '_'.date('His');  
        $dbUsername = $dbName;
        $dbPassword = Modules_Microweber_Helper::getRandomPassword(12, true);
        
        if ($this->_databaseDriver == 'mysql') {
        	
        	pm_Log::debug('Create database for domain: ' . $domain->getName());
        	
	        $dbManager = new Modules_Microweber_DatabaseManager();
	        $dbManager->setDomainId($domain->getId());
	
	        $newDb = $dbManager->createDatabase($dbName);
	        
	        if (isset($newDb['database']['add-db']['result']['errtext'])) {
	            throw new \Exception($newDb['database']['add-db']['result']['errtext']);
	        }
	        
	        $this->setProgress(30);
	
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
	        
	        $this->setProgress(40);
        }
        
        $domainDocumentRoot = $domain->getDocumentRoot(); 
        
        if ($this->_path) {
        	$domainDocumentRoot = $domainDocumentRoot . '/'.$this->_path;
        }
        
        $domainName = $domain->getName();
        $domainIsActive = $domain->isActive();
        $domainCreation = $domain->getProperty('cr_date');
        
        pm_Log::debug('Clear old folder on domain: ' . $domain->getName());
        
        // Clear domain files if exists
        $this->_prepairDomainFolder($fileManager, $domainDocumentRoot, $domain->getHomePath());
        
        $this->setProgress(60);
       	
        if ($this->_type == 'symlink') {
        	
        	// First we will make a directories
        	foreach ($this->_getDirsToMake() as $dir) {
        		$fileManager->mkdir($domainDocumentRoot . '/' . $dir, '0755', true);
        	}
        	
        	$this->setProgress(65);
        	
        	foreach ($this->_getFilesForSymlinking() as $folder) {
        		
        		$scriptDirOrFile = $this->_appLatestVersionFolder . '/' . $folder;
        		$domainDirOrFile = $domainDocumentRoot .'/'. $folder;
        		
        		$result = pm_ApiCli::callSbin('create_symlink.sh', [$domain->getSysUserLogin(), $scriptDirOrFile, $domainDirOrFile], pm_ApiCli::RESULT_FULL);
        		
        	}
        	
        	$this->setProgress(70);
        	
        	// And then we will copy files
        	foreach ($this->_getFilesForCopy() as $file) {
        		$fileManager->copyFile($this->_appLatestVersionFolder . '/' . $file, $domainDocumentRoot . '/' . $file);
        	}
        	
        	$this->setProgress(75);
        	
        } else {
        	pm_ApiCli::callSbin('rsync_two_dirs.sh', [$domain->getSysUserLogin(), $this->_appLatestVersionFolder . '/', $domainDocumentRoot]);
        	$this->setProgress(65);
        }
        
        $this->setProgress(80);
        
        $this->_fixHtaccess($fileManager, $domainDocumentRoot);
        
        $this->setProgress(85);
        
        
        $adminEmail = 'admin@microweber.com';
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
        
        $this->setProgress(90);
        
        $installArguments = [];
        
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
		
        $installArguments = array_map('escapeshellarg', $installArguments);
        $installArguments = implode(' ', $installArguments);
		
        $command = $domainDocumentRoot . '/artisan microweber:install ' . $installArguments;
		
        try {
        	$artisan = pm_ApiCli::callSbin('run_php.sh', [$domain->getSysUserLogin(), $command]);  
        	
        	$this->setProgress(95);
 
        	pm_Log::debug('Microweber install log for: ' . $domain->getName() . '<br />' . $artisan['stdout']. '<br /><br />');
        	
        	Modules_Microweber_WhiteLabel::updateWhiteLabelDomainById($domain->getId());
        	
        	return array('success'=>true, 'log'=> $artisan['stdout']);
        	
        } catch (Exception $e) {
        	return array('success'=>false,'error'=>true, 'log'=> $e->getMessage());
        }
        
    }
    
    private function _fixHtaccess($fileManager, $installPath)
    {
    	try {
    		
    		$content = $fileManager->fileGetContents($installPath . '/.htaccess');
    		
    		$content = str_replace('-MultiViews -Indexes', 'FollowSymLinks', $content);
    		
    		$fileManager->filePutContents($installPath . '/.htaccess', $content);
    		
    	} catch (Exception $e) {
    		\pm_Log::warn($e);
    	}
    }
    
    private function _prepairDomainFolder($fileManager, $installPath, $backupPath)
    {
    	try {
    		$findedFiles = [];
    		foreach ($fileManager->scanDir($installPath) as $file) {
    			if ($file == '.' || $file == '..') {
    				continue;
    			}
    			$findedFiles[] = $file;
    		}
    		
    		if (!empty($findedFiles)) {
    			// Make backup dir
    			$backupFilesPath = $backupPath . '/backup-files-' . date('Y-m-d-H-i-s');
    			$fileManager->mkdir($backupFilesPath);
    			
    			// Move files to backup dir
    			foreach ($findedFiles as $file) {
    				$fileManager->moveFile($installPath . '/' . $file, $backupFilesPath . '/' . $file);
    			}
    		}
    		
    	} catch (Exception $e) {
    		\pm_Log::warn($e);
    	}
    	
    }
    
    private function _getDirsToMake() {
    	
    	$dirs = [];
    	
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
    	
    	$files = [];
    	
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
    	
    	$files = [];
    	
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
    
}
