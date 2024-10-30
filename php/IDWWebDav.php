<?php

/**
 * Author : Diganta Dutta
 * Company: Pro-Softnet Corp.
 */

require('class_webdav_client.php');

if ( ! class_exists('IDWWebDavClient')) {
	class IDWWebDavClient extends webdav_client {
		/**
		 * Public method getsize
		 *
		 * GetSizes a single file OR directory from a webdav server
		 * And Returns File Size
		 */
		public function getsize($path) {
			if (trim($path) == '') {
				$this->_error_log('Missing a path in method propfind');
				return false;
			}
			$item = $this->gpi($path);
			if ($item === false) {
				return -1;
			} else {
				return ((int)$item['getcontentlength']);
			}
		}

		/**
		 * Public method mput_files
		 *
		 * Puts multiple files and directories onto a webdav server
		 * Param fileList must be in format array("localpath" => "destpath")
		 * @param array filelist
		 * @return bool true on success. otherwise int status code on error
		 */
		function mput_files(&$filelist) {
			$result = true;
			foreach($filelist as &$fileEntry) {
				$localpath = $fileEntry['local'];
				$destpath = $fileEntry['remote'];

				$localpath = rtrim($localpath, "/");  //Making sure that the end of the path does not contain any slash
				$destpath  = rtrim($destpath, "/");   //Making sure that the end of the path does not contain any slash
				$dest_file = basename($destpath);
				$dest_dir  = dirname($destpath);

				if(!$this->is_dir($dest_dir)) {
					$result = $this->create_dir($dest_dir);
				}
				$result_code = $this->put_file($destpath, $localpath);
				$result = (($result_code == 201) || ($result_code == 204) || ($result_code == 207) ||($result_code == 200));

				$fileEntry['success'] = $result;
			}
			return $result;
		}


		/**
		 * Public method create_dir
		 *
		 * Creates the directory recursively on a webdav server
		 * @param dir path
		 * @return bool true on success. otherwise false on error
		 */
		function create_dir($destpath) {
			$result = true;
			if(rtrim($destpath, '/') == '') {
				return $result;
			}

			$dest_file = basename($destpath);
			$dest_dir  = dirname($destpath);

			if(!$this->is_dir($dest_dir)) {
				$this->create_dir($dest_dir);
			}
			$result &= ($this->mkcol($destpath) == 201 );
			return $result;
		}

		function mget_files(&$filelist) {
			$success = true;
			foreach( $filelist as $fileEntry ) {
				$localFile = $fileEntry['local'];
				$remoteFile = $fileEntry['remote'];
				$localDir = dirname($localFile);
				if ( !is_dir($localDir) )
				$success = mkdir ($localDir, 0777, true);

				if ( $success ) {
					$filename = basename($localFile);
					if ( ! ($fileEntry['success'] = $this->get_file($remoteFile, "$localDir/$filename")) ) {
						$this->_error_log("Could not restore $remoteFile");
					}
				}
				else {
					break;
				}
			}
			return $success;
		}

		function getRemoteFileList($remoteDir, &$fileArray) {
			// recurse directories
			$remoteDir = rtrim($remoteDir, '/');
			$dir = basename($remoteDir);
			$parentDir  = dirname($remoteDir);

			$fileList = $this->ls($remoteDir . '/');
			if (!is_array($fileList)) {
				$this->_error_log("DEBUG: Failed to get the ls for $remoteDir");
				return true;
			}

			foreach($fileList as $dirEntry) {
				$fullpath = urldecode($dirEntry['href']);
				$filename = basename($fullpath);
				if ( $filename != $dir ) {
					if ( !isset($dirEntry['resourcetype']) ||
					$dirEntry['resourcetype'] != 'collection') {
						array_push($fileArray, "$remoteDir/$filename");
					} else {
						$this->getRemoteFileList("$remoteDir/$filename", $fileArray);
					}
				}
			}
		}
	}
}

?>
