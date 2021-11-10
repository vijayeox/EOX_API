<?php
namespace Oxzion\Log4PHP\Appender;
/**
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to You under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 *
 *	   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package log4php
 */

/**
 * LoggerAppenderRollingFile writes logging events to a specified file. The 
 * file is rolled over after a specified size has been reached.
 * 
 * This appender uses a layout.
 *
 * ## Configurable parameters: ##
 * 
 * - **file** - Path to the target file.
 * - **append** - If set to true, the appender will append to the file, 
 *     otherwise the file contents will be overwritten.
 * - **maxBackupIndex** - Maximum number of backup files to keep. Default is 1.
 * - **maxFileSize** - Maximum allowed file size (in bytes) before rolling 
 *     over. Suffixes "KB", "MB" and "GB" are allowed. 10KB = 10240 bytes, etc.
 *     Default is 10M.
 * - **compress** - If set to true, rolled-over files will be compressed. 
 *     Requires the zlib extension.
 *
 * @version $Revision$
 * @package log4php
 * @subpackage appenders
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @link http://logging.apache.org/log4php/docs/appenders/rolling-file.html Appender documentation
 */

use LoggerAppenderFile;

class LoggerAppenderRollingFile extends LoggerAppenderFile {

	/** Compressing backup files is done in chunks, this determines how large. */
	const COMPRESS_CHUNK_SIZE = 102400; // 100KB
	
	/**
	 * The maximum size (in bytes) that the output file is allowed to reach 
	 * before being rolled over to backup files.
	 *
	 * The default maximum file size is 10MB (10485760 bytes). Maximum value 
	 * for this option may depend on the file system.
	 *
	 * @var integer
	 */
	protected $maxFileSize = 10485760;
	
	/**
	 * The <var>compress</var> parameter determindes the compression with zlib. 
	 * If set to true, the rollover files are compressed and saved with the .gz extension.
	 * @var boolean
	 */
	protected $compress = false;

	/**
	 * Set to true in the constructor if PHP >= 5.3.0. In that case clearstatcache
	 * supports conditional clearing of statistics.
	 * @var boolean
	 * @see http://php.net/manual/en/function.clearstatcache.php
	 */
	private $clearConditional = false;
	
	protected $datePattern = "Ymd";
	protected $currentDate;
	/**
	 * Get the maximum size that the output file is allowed to reach
	 * before being rolled over to backup files.
	 * @return integer
	 */
	public function getMaximumFileSize() {
		return $this->maxFileSize;
	}

	public function __construct($name = '') {
		parent::__construct($name);
		if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
			$this->clearConditional = true;
		}
		// static $backupIndex = 0;
	}
	
	/**
	 * Implements the usual roll over behaviour.
	 * 
	 * Rollover must be called while the file is locked so that it is safe for concurrent access. 
	 * 
	 * @throws LoggerException If any part of the rollover procedure fails.
	 */
	private function rollOver() {
		$this->backupIndex = 0;
		$filePattern = $this->getTargetFile();
		$globvar = glob($filePattern.'.[0-9]*');
		if(sizeof($globvar) > 0){
			$this->backupIndex = sizeof($globvar);
		}
		$this->backupIndex ++;
		$target = $this->newFile . '.'.$this->backupIndex;
		copy($this->newFile, $target);	
		// Truncate the active file
		ftruncate($this->fp, 0);
		rewind($this->fp);
	}
	
	

	private function compressFile($source, $target) {
		$target = 'compress.zlib://' . $target;
		
		$fin = fopen($source, 'rb');
		if ($fin === false) {
			throw new LoggerException("Unable to open file for reading: [$source].");
		}
		
		$fout = fopen($target, 'wb');
		if ($fout === false) {
			throw new LoggerException("Unable to open file for writing: [$target].");
		}
	
		while (!feof($fin)) {
			$chunk = fread($fin, self::COMPRESS_CHUNK_SIZE);
			if (false === fwrite($fout, $chunk)) {
				throw new LoggerException("Failed writing to compressed file.");
			}
		}
	
		fclose($fin);
		fclose($fout);
	}
	
	
	/**
	 * Writes a string to the target file. Opens file if not already open.
	 * @param string $string Data to write.
	 */
	protected function write($string) {
		// Lazy file open
		if(!isset($this->fp)) {
			if ($this->openFile() === false) {
				return; // Do not write if file open failed.
			}
		}
		
		// Lock the file while writing and possible rolling over
		if(flock($this->fp, LOCK_EX)) {
			
			// Write to locked file
			if(fwrite($this->fp, $string) === false) {
				$this->warn("Failed writing to file. Closing appender.");
				$this->closed = true;
			}
			
			// Stats cache must be cleared, otherwise filesize() returns cached results
			// If supported (PHP 5.3+), clear only the state cache for the target file
			
			if ($this->newFile) {
				clearstatcache(true, $this->newFile);
			} else {
				clearstatcache();
			}
			
			// Rollover if needed
			if (filesize($this->newFile) > $this->maxFileSize) {
				try {
					$this->rollOver();
				} catch (LoggerException $ex) {
					$this->warn("Rollover failed: " . $ex->getMessage() . " Closing appender.");
					$this->closed = true;
				}
			}
			
			flock($this->fp, LOCK_UN);
		} else {
			$this->warn("Failed locking file for writing. Closing appender.");
			$this->closed = true;
		}
	}
	
	public function activateOptions() {
		parent::activateOptions();
		
		if ($this->compress && !extension_loaded('zlib')) {
			$this->warn("The 'zlib' extension is required for file compression. Disabling compression.");
			$this->compression = false;
		}
	}
	
	/**
	 * Set the 'maxBackupIndex' parameter.
	 * @param integer $maxBackupIndex
	 */
	public function setMaxBackupIndex($maxBackupIndex) {
		$this->setPositiveInteger('maxBackupIndex', $maxBackupIndex);
	}
	
	/**
	 * Returns the 'maxBackupIndex' parameter.
	 * @return integer
	 */
	public function getMaxBackupIndex() {
		return $this->maxBackupIndex;
	}
	
	/**
	 * Set the 'maxFileSize' parameter.
	 * @param mixed $maxFileSize
	 */
	public function setMaxFileSize($maxFileSize) {
		$this->setFileSize('maxFileSize', $maxFileSize);
	}
	
	/**
	 * Returns the 'maxFileSize' parameter.
	 * @return integer
	 */
	public function getMaxFileSize() {
		return $this->maxFileSize;
	}
	
	/**
	 * Set the 'maxFileSize' parameter (kept for backward compatibility).
	 * @param mixed $maxFileSize
	 * @deprecated Use setMaxFileSize() instead.
	 */
	public function setMaximumFileSize($maxFileSize) {
		$this->warn("The 'maximumFileSize' parameter is deprecated. Use 'maxFileSize' instead.");
		return $this->setMaxFileSize($maxFileSize);
	}
	
	/**
	 * Sets the 'compress' parameter.
	 * @param boolean $compress
	 */
	public function setCompress($compress) {
		$this->setBoolean('compress', $compress);
	}
	
	/**
	 * Returns the 'compress' parameter.
	 * @param boolean 
	 */
	public function getCompress() {
		return $this->compress;
	}

	
	/**
	 * Determines target file. Replaces %s in file path with a date. 
	 */
	protected function getTargetFile() {
		$eventDate = date('Y-m-d');
		if(!$this->currentDate){
			$this->currentDate = $eventDate;
		}
		$this->newFile = str_replace('%s', $eventDate, $this->file);
		return $this->newFile;
	}
	
	/**
	 * Sets the 'datePattern' parameter.
	 * @param string $datePattern
	 */
	public function setDatePattern($datePattern) {
		$this->setString('datePattern', $datePattern);
	}
	
	/**
	 * Returns the 'datePattern' parameter.
	 * @return string
	 */
	public function getDatePattern() {
		return $this->datePattern;
	}
}
