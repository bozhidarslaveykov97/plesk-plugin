<?php

class Modules_Microweber_Logger
{

	protected $_logFileName = 'microweberLog.txt';

	public function clear()
	{
		file_put_contents($this->_getLogFilename(), false);
	}

	public function read()
	{
		$log = file_get_contents($this->_getLogFilename());
		
		$log = explode(PHP_EOL, $log);
		
		$log = implode("<br />", $log);
		
		return $log;
	}

	public function write($logText)
	{
		$this->_addNew($logText, 45);
	}

	private function _addNew($logText, $maxLines = 15)
	{
		
		$fileName = $this->_getLogFilename();
		
		if (! is_file($fileName)) {
			file_put_contents($fileName, '');
		}

		// Remove Empty Spaces
		$file = array_filter(array_map("trim", file($fileName)));

		// Make Sure you always have maximum number of lines
		$file = array_slice($file, 0, $maxLines);

		// Remove any extra line
		count($file) >= $maxLines and array_shift($file);

		// Add new Line
		array_push($file, $logText);

		// Save Result
		@file_put_contents($fileName, implode(PHP_EOL, array_filter($file)));
	}

	protected function _getLogFilename()
	{
		return pm_ProductInfo::getPrivateTempDir() . '/' . $this->_logFileName;
	}
}