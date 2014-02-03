<?php
/**
 * This is the implementation of the server side part of
 * Resumable.js client script, which sends/uploads files
 * to a server in several chunks.
 *
 * The script receives the files in a standard way as if
 * the files were uploaded using standard HTML form (multipart).
 *
 * This PHP script stores all the chunks of a file in a temporary
 * directory (`temp`) with the extension `_part<#ChunkN>`. Once all
 * the parts have been uploaded, a final destination file is
 * being created from all the stored parts (appending one by one).
 *
 * @author Gregory Chris (http://online-php.com)
 *
 * @email www.online.php@gmail.com
 */

/**
 * implement backend for resumableJS
 * @author pagliaccio (https://github.com/pagliaccio)
 *
 */
class resumableJS {
	private $log;
	private $tmpdir;
	private $name;
	private $chunkSize;
	private $totalSize;
	/**
	 *
	 * @param Array $config 'name'=>name of file 'tmpdir' 'chunkSize' 'totalSize' log'=>function for loggin
	 */
	function __construct($config) {
		$this->log=$config['log'];
		$this->tmpdir=$config['tmpdir']? $config['tmpdir'] : 'temp';
		if (!is_dir($this->tmpdir)) {
			if (!mkdir($this->tmpdir)) throw new Exception("i can't write in this directory: ".$this->tmpdir);
		}
		$this->name=$config['name'];
		$this->chunkSize=$config['chunkSize'];
		$this->totalSize=$config['totalSize'];
		$require=array('name','chunkSize','totalSize');
		foreach ($require as $value) {
			if ($config[$value]==null) throw new Exception($value.' must not be null value='.$config[$value]);
			$this->log($value.' must not be null value='.$config[$value]);
		}

	}
	function listen($data,$type,$files=NULL) {
		if ($type==='GET') {
			$chunk_file = $this->tmpdir.'/'.$this->name.'.part'.$_GET['resumableChunkNumber'];
			if (file_exists($chunk_file)) {
				header("HTTP/1.0 200 Ok");
			} else
			{
				header("HTTP/1.0 404 Not Found");
			}
		}
		// loop through files and move the chunks to a temporarily created directory
		if (!empty($files)) {
			foreach ($files as $file) {
				// check the error status
				if ($file['error'] != 0) {
					_log('error '.$file['error'].' in file '.$this->name);
					continue;
				}

				// init the destination file (format <filename.ext>.part<#chunk>
				// the file is stored in a temporary directory
				$dest_file = $this->tmpdir.'/'.$data['resumableFilename'].'.part'.$data['resumableChunkNumber'];

				// create the temporary directory
				if (!is_dir($this->tmpdir)) {
					mkdir($this->tmpdir, 0777, true);
				}

				// move the temporary file
				if (!move_uploaded_file($file['tmp_name'], $dest_file)) {
					_log('Error saving (move_uploaded_file) chunk '.$data['resumableChunkNumber'].' for file '.$this->name);
				} else {

					// check if all the parts present, and create the final destination file
					$this->createFile();
				}
			}
		}
	}
	private function log($str) {
		if (function_exists($this->log)) {
			call_user_func($this->log,$str);
		}
		else {
			if (($fp = fopen('upload_log.txt', 'a+')) !== false) {
				fputs($fp, $log_str);
				fclose($fp);
			}
		}
	}
	/**
	 *
	 * Delete a directory RECURSIVELY
	 * @param string $dir - directory path
	 * @link http://php.net/manual/en/function.rmdir.php
	 */
	private	function rrmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (filetype($dir . "/" . $object) == "dir") {
						rrmdir($dir . "/" . $object);
					} else {
						unlink($dir . "/" . $object);
					}
				}
			}
			reset($objects);
			rmdir($dir);
		}
	}
	/**
	 *
	 * Check if all the parts exist, and
	 * gather all the parts of the file together
	 * @param string $dir - the temporary directory holding all the parts of the file
	 * @param string $fileName - the original file name
	 * @param string $chunkSize - each chunk size (in bytes)
	 * @param string $totalSize - original file size (in bytes)
	 */
	function createFile() {

		// count all the parts of this file
		$total_files = 0;
		foreach(scandir($this->tmpdir) as $file) {
			if (stripos($file, $this->name) !== false) {
				$total_files++;
			}
		}

		// check that all the parts are present
		// the size of the last part is between chunkSize and 2*$chunkSize
		if ($total_files * $this->chunkSize >=  ($this->totalSize - $this->chunkSize + 1)) {

			// create the final destination file
			if (($fp = fopen('temp/'.$this->name, 'w')) !== false) {
				for ($i=1; $i<=$total_files; $i++) {
					fwrite($fp, file_get_contents($this->tmpdir.'/'.$this->name.'.part'.$i));
					_log('writing chunk '.$i);
				}
				fclose($fp);
			} else {
				_log('cannot create the destination file');
				return false;
			}

			// rename the temporary directory (to avoid access from other
			// concurrent chunks uploads) and than delete it
			if (rename($this->tmpdir, $this->tmpdir.'_UNUSED')) {
				rrmdir($this->tmpdir.'_UNUSED');
			} else {
				rrmdir($this->tmpdir);
			}
		}

	}
}
?>


