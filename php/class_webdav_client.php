<?php
/*
 CVS:
  $Id: class_webdav_client.php,v 1.7 2004/08/18 14:12:40 chris Exp $
  $Author: chris $
  $Date: 2004/08/18 14:12:40 $
  $Revision: 1.7 $
*/
/**
 * webdav_client v0.1.3, a php based webdav client class.
 *
 * Copyright (C) 2003 Christian Juerges
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */
/**
 * class webdav client. a php based nearly rfc 2518 conforming client.
 *
 * This class implements methods to get access to an webdav server.
 * Most of the methods return false on error, an passtrough integer (http response status) on success
 * or an array in case of a multistatus response (207) from the webdav server.
 * It's your responsibility to handle the webdav server responses in an proper manner.
 *
 * @author Christian Juerges <christian.juerges@xwave.ch>, Xwave GmbH, Josefstr. 92, 8005 Zuerich - Switzerland.
 * @version 0.1.3
 * @copyright (C) 2003/2004, Christian Juerges
 * @license http://opensource.org/licenses/lgpl-license.php GNU Lesser General Public License
 * @package webdav_client
 */
class webdav_client {
    
    var $_debug = false;
    var $_fp;
    var $_server;
    var $_port = 443;
    var $_path ='/';
    var $_user;
    var $_protocol = 'HTTP/1.0';
    var $_pass;
    var $_socket_timeout = 30;
    var $_errno;
    var $_errstr;
    var $_user_agent = 'php class webdav_client $Revision: 1.7 $';
    var $_crlf = "\r\n";
    var $_req;
    var $_resp_status;
    var $_parser;
    var $_xmltree;

    var $_tree;
    var $_ls = array();
    var $_ls_ref;
    var $_ls_ref_cdata;

    var $_delete = array();
    var $_delete_ref;
    var $_delete_ref_cdata;

    var $_lock = array();
    var $_lock_ref;
    var $_lock_rec_cdata;


    var $_null = NULL;
    var $_header='';
    var $_body='';
    var $_connection_closed = false;
    var $_maxheaderlenth = 1000;
    var $_sri_server_dirs = array();

    /**#@-*/

 /**
    * Constructor - does nothing...
    */
    function webdav_client() {
        // do nothing here
    }

 /**
    * Set webdav server. FQN or IP address.
    * @param string server
    */
    function set_server($server) {
     $this->_server = $server;
    }

 /**
    * Set tcp port of webdav server. Default is 80.
    * @param int port
    */
    function set_port($port) {
        $this->_port = $port;
    }

 /**
    * set user name for authentification
    * @param string user
    */
    function set_user($user) {
        $this->_user = $user;
    }

 /**
    * Set password for authentification
    * @param string pass
    */
    function set_pass($pass) {
        $this->_pass = $pass;
    }

 /**
    * set debug on (1) or off (0).
    * produces a lot of debug messages in webservers error log if set to on (1).
    * @param bool debug
    */
    function set_debug($debug) {
        $this->_debug = $debug;
    }

 /**
    * Set which HTTP protocol will be used.
    * Value 1 defines that HTTP/1.1 should be used (Keeps Connection to webdav server alive).
    * Otherwise HTTP/1.0 will be used.
    * @param int version
    */
    function set_protocol($version) {
        if ($version == 1) {
            $this->_protocol = 'HTTP/1.1';
        } else {
            $this->_protocol = 'HTTP/1.0';
        }
        $this->_error_log('HTTP Protocol was set to ' . $this->_protocol);

    }

 /**
    * Convert ISO 8601 Date and Time Profile used in RFC 2518 to an unix timestamp.
    * @access private
    * @param string iso8601
    * @return unixtimestamp on sucess. Otherwise false.
    */
    function iso8601totime($iso8601) {
        /*

         date-time       = full-date "T" full-time

         full-date       = date-fullyear "-" date-month "-" date-mday
         full-time       = partial-time time-offset

         date-fullyear   = 4DIGIT
         date-month      = 2DIGIT  ; 01-12
         date-mday       = 2DIGIT  ; 01-28, 01-29, 01-30, 01-31 based on
         month/year
         time-hour       = 2DIGIT  ; 00-23
         time-minute     = 2DIGIT  ; 00-59
         time-second     = 2DIGIT  ; 00-59, 00-60 based on leap second rules
         time-secfrac    = "." 1*DIGIT
         time-numoffset  = ("+" / "-") time-hour ":" time-minute
         time-offset     = "Z" / time-numoffset

         partial-time    = time-hour ":" time-minute ":" time-second
                                            [time-secfrac]
         */

         $regs = array();
         /*         [1]        [2]        [3]        [4]        [5]        [6]  */
         if (ereg('^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})Z$', $iso8601, $regs)) {
             return mktime($regs[4],$regs[5], $regs[6], $regs[2], $regs[3], $regs[1]);
         }
         // to be done: regex for partial-time...apache webdav mod never returns partial-time

         return false;
    }

 /**
    * Open's a socket to a webdav server
    * @return bool true on success. Otherwise false.
    */
    function open() {
        // let's try to open a socket
        $this->_error_log('open a socket connection');
        $server = $this->_server;
        if ( $this->_port == '443' )
            $this->_fp = fsockopen ('ssl://' . $this->_server, $this->_port, $this->_errno, $this->_errstr, $this->_socket_timeout);
        else
            $this->_fp = fsockopen ($this->_server, $this->_port, $this->_errno, $this->_errstr, $this->_socket_timeout);
        // set_time_limit(30);
        //socket_set_blocking($this->_fp, true);
        if (!$this->_fp) {
            $this->_error_log("$this->_errstr ($this->_errno)\n");
            return false;
        } else {
            $this->_connection_closed = false;
            $this->_error_log('socket is open: ' . $this->_fp);
            socket_set_blocking($this->_fp, true);
            return true;
        }
    }

 /**
    * Closes an open socket.
    */
    function close() {
        $this->_error_log('closing socket ' . $this->_fp);
        $this->_connection_closed = true;
        fclose($this->_fp);
    }

 /**
    * Check's if server is a webdav compliant server.
    * True if server returns a DAV Element in Header and when
    * schema 1,2 is supported.
    * @return bool true if server is webdav server. Otherwise false.
    */
    function check_webdav() {
        $resp = $this->options();
        if (!$resp) {
            return false;
        }
        $this->_error_log($resp['header']['DAV']);
        // check schema
        if (preg_match('/1,2/', $resp['header']['DAV'])) {
            return true;
        }
        // otherwise return false
        return false;
    }


 /**
    * Get options from webdav server.
    * @return array with all header fields returned from webdav server. false if server does not speak http.
    */
    function options() {
        $this->_header_unset();
        $this->_create_basic_request('OPTIONS');
        $this->_send_request();
        $this->_get_respond();
        $response = $this->_process_respond();
        // validate the response ...
        // check http-version
        if ($response['status']['http-version'] == 'HTTP/1.1' ||
             $response['status']['http-version'] == 'HTTP/1.0') {
             return $response;
        }
        $this->_error_log('Response was not even http');
        return false;

    }

 /** 
    * Public method mkcol
    *
    * Creates a new collection/directory on a webdav server
    * @param string path
    * @return int status code received as reponse from webdav server (see rfc 2518)  
    */
    function mkcol($path) {
        $this->_path = $this->_translate_uri($path);
        $this->_header_unset();
        $this->_create_basic_request('MKCOL');
        $this->_send_request();
        $this->_get_respond();
        $response = $this->_process_respond();
        // validate the response ...
        // check http-version
        if ($response['status']['http-version'] == 'HTTP/1.1' ||
             $response['status']['http-version'] == 'HTTP/1.0') {
            /* seems to be http ... proceed
                just return what server gave us
                rfc 2518 says:
                201 (Created) - The collection or structured resource was created in its entirety.
                403 (Forbidden) - This indicates at least one of two conditions: 1) the server does not allow the creation of collections at the given
                                                 location in its namespace, or 2) the parent collection of the Request-URI exists but cannot accept members.
                405 (Method Not Allowed) - MKCOL can only be executed on a deleted/non-existent resource.
                409 (Conflict) - A collection cannot be made at the Request-URI until one or more intermediate collections have been created.
                415 (Unsupported Media Type)- The server does not support the request type of the body.
                507 (Insufficient Storage) - The resource does not have sufficient space to record the state of the resource after the execution of this method.
            */
            if($response['status']['status-code'] == '201') {
                $this->_sri_server_dirs[$path] = true;
            }
            return $response['status']['status-code'];
        }

    }

 /**
    * Public method get
    *
    * Gets a file from a webdav collection.
    * @param string path, string &buffer 
    * @return status code and &$buffer (by reference) with response data from server on success. False on error. 
    */
    function get($path, &$buffer) {
        $this->_path = $this->_translate_uri($path);
        $this->_header_unset();
        $this->_create_basic_request('GET');
        $this->_send_request();
        $this->_get_respond();
        $response = $this->_process_respond();

        // validate the response
        // check http-version
        if ($response['status']['http-version'] == 'HTTP/1.1' ||
             $response['status']['http-version'] == 'HTTP/1.0') {
            // seems to be http ... proceed
            // We expect a 200 code
            if ($response['status']['status-code'] == 200 ) {
                $this->_error_log('returning buffer with ' . strlen($response['body']) . ' bytes.');
                $buffer = $response['body'];
            }
            return $response['status']['status-code'];
         }
         // ups: no http status was returned ?
         return false;
    }

 /**
    * Public method put
    *
    * Puts a file into a collection. 
    *   Data is putted as one chunk!
    * @param string path, string data
    * @return int status-code read from webdavserver. False on error.
    */
    function put($path, $data ) {
        $this->_path = $this->_translate_uri($path);
        $this->_header_unset();
        $this->_create_basic_request('PUT');
        // add more needed header information ...
        $this->_header_add('Content-length: ' . strlen($data));
        $this->_header_add('Content-type: application/octet-stream');
        // send header
        $this->_send_request();
        // send the rest (data)
        fputs($this->_fp, $data);
        $this->_get_respond();
        $response = $this->_process_respond();

        // validate the response
        // check http-version
        if ($response['status']['http-version'] == 'HTTP/1.1' ||
             $response['status']['http-version'] == 'HTTP/1.0') {
            // seems to be http ... proceed
            // We expect a 200 or 204 status code
            // see rfc 2068 - 9.6 PUT...
            // print 'http ok<br>';
            return $response['status']['status-code'];
         }
         // ups: no http status was returned ?
         return false;
    }

 /**
    * Public method put_file
    *
    * Read a file as stream and puts it chunk by chunk into webdav server collection.
    * Look at php documenation for legal filenames with fopen();   
    *
    * @param string targetpath, string filename
    * @return int status code. False on error.
    */
    function put_file($path, $filename) {
        // try to open the file ...
        $handle = @fopen ($filename, 'r');
        if ($handle) {
            // $this->_fp = pfsockopen ($this->_server, $this->_port, $this->_errno, $this->_errstr, $this->_socket_timeout);
            $this->_path = $this->_translate_uri($path);
            $this->_header_unset();
            $this->_create_basic_request('PUT');
            // add more needed header information ...
            $this->_header_add('Content-length: ' . filesize($filename));
            $this->_header_add('Content-type: application/octet-stream');
            // send header
            $this->_send_request();
            while (!feof($handle)) {
                fputs($this->_fp,fgets($handle,4096));
            }
            fclose($handle);
            $this->_get_respond();
            $response = $this->_process_respond();

            // validate the response
            // check http-version
            if ($response['status']['http-version'] == 'HTTP/1.1' ||
                $response['status']['http-version'] == 'HTTP/1.0') {
                // seems to be http ... proceed
                // We expect a 200 or 204 status code
                // see rfc 2068 - 9.6 PUT...
                // print 'http ok<br>';
                return $response['status']['status-code'];
            }
            // ups: no http status was returned ?
            return false;
        } else {
            $this->_error_log('could not open ' . $filename);
            return false;
        }

    }
    
 /**
    * Public method get_file
    *
    * Gets a file from a collection into local filesystem. 
    * fopen() is used.
    * @param string srcpath, string localpath
    * @return true on success. false on error.
    */
    function get_file($srcpath, $localpath) {

        if ($this->get($srcpath, $buffer)) {
            $handle = fopen ($localpath, 'w');
            if ($handle) {
                fwrite($handle, $buffer);
                fclose($handle);
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

 /** 
    * Public method copy_file
    *
    * Copy a file on webdav server
    * Duplicates a file on the webdav server (serverside). 
    * All work is done on the webdav server. If you set param overwrite as true,
    * the target will be overwritten. 
    *
    * @param string src_path, string dest_path, bool overwrite
    * @return int status code (look at rfc 2518). false on error.
    */
    function copy_file($src_path, $dst_path, $overwrite) {
     $this->_path = $this->_translate_uri($src_path);
     $this->_header_unset();
     $this->_create_basic_request('COPY');
     $this->_header_add(sprintf('Destination: http://%s%s', $this->_server, $this->_translate_uri($dst_path)));
     if ($overwrite) {
         $this->_header_add('Overwrite: T');
     } else {
         $this->_header_add('Overwrite: F');
     }
     $this->_header_add('');
     $this->_send_request();
     $this->_get_respond();
     $response = $this->_process_respond();
     // validate the response ...
     // check http-version
     if ($response['status']['http-version'] == 'HTTP/1.1' ||
            $response['status']['http-version'] == 'HTTP/1.0') {
         /* seems to be http ... proceed
             just return what server gave us (as defined in rfc 2518) :
             201 (Created) - The source resource was successfully copied. The copy operation resulted in the creation of a new resource.
             204 (No Content) - The source resource was successfully copied to a pre-existing destination resource.
             403 (Forbidden) - The source and destination URIs are the same.
             409 (Conflict) - A resource cannot be created at the destination until one or more intermediate collections have been created.
             412 (Precondition Failed) - The server was unable to maintain the liveness of the properties listed in the propertybehavior XML element
                     or the Overwrite header is "F" and the state of the destination resource is non-null.
             423 (Locked) - The destination resource was locked.
             502 (Bad Gateway) - This may occur when the destination is on another server and the destination server refuses to accept the resource.
             507 (Insufficient Storage) - The destination resource does not have sufficient space to record the state of the resource after the
                     execution of this method.
         */
         return $response['status']['status-code'];
     }
     return false;
    }

/** 
    * Public method copy_coll
    *
    * Copy a collection on webdav server
    * Duplicates a collection on the webdav server (serverside). 
    * All work is done on the webdav server. If you set param overwrite as true,
    * the target will be overwritten. 
    *
    * @param string src_path, string dest_path, bool overwrite
    * @return int status code (look at rfc 2518). false on error.
    */
    function copy_coll($src_path, $dst_path, $overwrite) {
     $this->_path = $this->_translate_uri($src_path);
     $this->_header_unset();
     $this->_create_basic_request('COPY');
     $this->_header_add(sprintf('Destination: http://%s%s', $this->_server, $this->_translate_uri($dst_path)));
     $this->_header_add('Depth: Infinity');

     $xml  = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\r\n";
     $xml .= "<d:propertybehavior xmlns:d=\"DAV:\">\r\n";
     $xml .= "  <d:keepalive>*</d:keepalive>\r\n";
     $xml .= "</d:propertybehavior>\r\n";

     $this->_header_add('Content-length: ' . strlen($xml));
     $this->_header_add('Content-type: text/xml');
     $this->_send_request();
        // send also xml
     fputs($this->_fp, $xml);
     $this->_get_respond();
     $response = $this->_process_respond();
     // validate the response ...
     // check http-version
     if ($response['status']['http-version'] == 'HTTP/1.1' ||
            $response['status']['http-version'] == 'HTTP/1.0') {
         /* seems to be http ... proceed
             just return what server gave us (as defined in rfc 2518) :
             201 (Created) - The source resource was successfully copied. The copy operation resulted in the creation of a new resource.
             204 (No Content) - The source resource was successfully copied to a pre-existing destination resource.
             403 (Forbidden) - The source and destination URIs are the same.
             409 (Conflict) - A resource cannot be created at the destination until one or more intermediate collections have been created.
             412 (Precondition Failed) - The server was unable to maintain the liveness of the properties listed in the propertybehavior XML element
                     or the Overwrite header is "F" and the state of the destination resource is non-null.
             423 (Locked) - The destination resource was locked.
             502 (Bad Gateway) - This may occur when the destination is on another server and the destination server refuses to accept the resource.
             507 (Insufficient Storage) - The destination resource does not have sufficient space to record the state of the resource after the
                     execution of this method.
         */
         return $response['status']['status-code'];
     }
     return false;
    }

    /** 
    * Public method move
    *
    * Move a file or collection on webdav server (serverside)
    * If you set param overwrite as true, the target will be overwritten. 
    *
    * @param string src_path, string dest_path, bool overwrite
    * @return int status code (look at rfc 2518). false on error.
    */
    // --------------------------------------------------------------------------
    // public method move
    // move/rename a file/collection on webdav server
    function move($src_path,$dst_path, $overwrite) {

        $this->_path = $this->_translate_uri($src_path);
        $this->_header_unset();

        $this->_create_basic_request('MOVE');
        // dst_path should not be uri translated....
        $this->_header_add(sprintf('Destination: http://%s%s', $this->_server, $dst_path));
        if ($overwrite) {
            $this->_header_add('Overwrite: T');
        } else {
            $this->_header_add('Overwrite: F');
        }
        $this->_header_add('');

        $this->_send_request();
        $this->_get_respond();
        $response = $this->_process_respond();
        // validate the response ...
        // check http-version
        if ($response['status']['http-version'] == 'HTTP/1.1' ||
             $response['status']['http-version'] == 'HTTP/1.0') {
            /* seems to be http ... proceed
                just return what server gave us (as defined in rfc 2518) :
                201 (Created) - The source resource was successfully moved, and a new resource was created at the destination.
                204 (No Content) - The source resource was successfully moved to a pre-existing destination resource.
                403 (Forbidden) - The source and destination URIs are the same.
                409 (Conflict) - A resource cannot be created at the destination until one or more intermediate collections have been created.
                412 (Precondition Failed) - The server was unable to maintain the liveness of the properties listed in the propertybehavior XML element
                         or the Overwrite header is "F" and the state of the destination resource is non-null.
                423 (Locked) - The source or the destination resource was locked.
                502 (Bad Gateway) - This may occur when the destination is on another server and the destination server refuses to accept the resource.

                201 (Created) - The collection or structured resource was created in its entirety.
                403 (Forbidden) - This indicates at least one of two conditions: 1) the server does not allow the creation of collections at the given
                                                 location in its namespace, or 2) the parent collection of the Request-URI exists but cannot accept members.
                405 (Method Not Allowed) - MKCOL can only be executed on a deleted/non-existent resource.
                409 (Conflict) - A collection cannot be made at the Request-URI until one or more intermediate collections have been created.
                415 (Unsupported Media Type)- The server does not support the request type of the body.
                507 (Insufficient Storage) - The resource does not have sufficient space to record the state of the resource after the execution of this method.
            */
            return $response['status']['status-code'];
        }
        return false;
    }

 /** 
    * Public method lock
    *
    * Lock a file or collection.
    * 
    * Lock uses this->_user as lock owner.
    * 
    * @param string path
    * @return int status code (look at rfc 2518). false on error.
    */
    function lock($path) {
        $this->_path = $this->_translate_uri($path);
        $this->_header_unset();
        $this->_create_basic_request('LOCK');
        $this->_header_add('Timeout: Infinite');
        $this->_header_add('Content-type: text/xml');
        // create the xml request ...
        $xml =  "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\r\n";
        $xml .= "<D:lockinfo xmlns:D='DAV:'\r\n>";
        $xml .= "  <D:lockscope><D:exclusive/></D:lockscope>\r\n";
        $xml .= "  <D:locktype><D:write/></D:locktype>\r\n";
        $xml .= "  <D:owner>\r\n";
        $xml .= "    <D:href>".($this->_user)."</D:href>\r\n";
        $xml .= "  </D:owner>\r\n";
        $xml .= "</D:lockinfo>\r\n";
        $this->_header_add('Content-length: ' . strlen($xml));
        $this->_send_request();
        // send also xml
        fputs($this->_fp, $xml);
        $this->_get_respond();
        $response = $this->_process_respond();
        // validate the response ... (only basic validation)
        // check http-version
        if ($response['status']['http-version'] == 'HTTP/1.1' ||
             $response['status']['http-version'] == 'HTTP/1.0') {
            /* seems to be http ... proceed
            rfc 2518 says:
            200 (OK) - The lock request succeeded and the value of the lockdiscovery property is included in the body.
            412 (Precondition Failed) - The included lock token was not enforceable on this resource or the server could not satisfy the
                     request in the lockinfo XML element.
            423 (Locked) - The resource is locked, so the method has been rejected.
            */

            switch($response['status']['status-code']) {
                case 200:
                    // collection was successfully locked... see xml response to get lock token...
                    if (strcmp($response['header']['Content-Type'], 'text/xml; charset="utf-8"') == 0) {
                        // ok let's get the content of the xml stuff
                        $this->_parser = xml_parser_create_ns();
                        // forget old data...
                        unset($this->_lock[$this->_parser]);
                        unset($this->_xmltree[$this->_parser]);
                        xml_parser_set_option($this->_parser,XML_OPTION_SKIP_WHITE,0);
                        xml_parser_set_option($this->_parser,XML_OPTION_CASE_FOLDING,0);
                        xml_set_object($this->_parser, $this);
                        xml_set_element_handler($this->_parser, "_lock_startElement", "_endElement");
                        xml_set_character_data_handler($this->_parser, "_lock_cdata");

                        if (!xml_parse($this->_parser, $response['body'])) {
                            die(sprintf("XML error: %s at line %d",
                                                     xml_error_string(xml_get_error_code($this->_parser)),
                                                     xml_get_current_line_number($this->_parser)));
                        }

                        // Free resources
                        xml_parser_free($this->_parser);
                        // add status code to array
                        $this->_lock[$this->_parser]['status'] = 200;
                        return $this->_lock[$this->_parser];

                    } else {
                        print 'Missing Content-Type: text/xml header in response.<br>';
                    }
                    return false;

                default:
                    // hmm. not what we expected. Just return what we got from webdav server 
                    // someone else has to handle it.
                    $this->_lock['status'] = $response['status']['status-code'];
                    return $this->_lock;
            }
        }


    }


 /** 
    * Public method unlock
    *
    * Unlock a file or collection.
    * 
    * @param string path, string locktoken
    * @return int status code (look at rfc 2518). false on error.
    */
    function unlock($path, $locktoken) {
        $this->_path = $this->_translate_uri($path);
        $this->_header_unset();
        $this->_create_basic_request('UNLOCK');
        $this->_header_add(sprintf('Lock-Token: <%s>', $locktoken));
        $this->_send_request();
        $this->_get_respond();
        $response = $this->_process_respond();
        if ($response['status']['http-version'] == 'HTTP/1.1' ||
             $response['status']['http-version'] == 'HTTP/1.0') {
            /* seems to be http ... proceed
            rfc 2518 says:
            204 (OK) - The 204 (No Content) status code is used instead of 200 (OK) because there is no response entity body.
            */
            return $response['status']['status-code'];
         }
        return false;
    }

 /** --------------------------------------------------------------------------
    * Public method delete
    *
    * deletes a collection/directory on a webdav server
    * @param string path
    * @return int status code (look at rfc 2518). false on error.
    */
    function delete($path) {
        $this->_path = $this->_translate_uri($path);
        $this->_header_unset();
        $this->_create_basic_request('DELETE');
        /* $this->_header_add('Content-Length: 0'); */
        $this->_header_add('');
        $this->_send_request();
        $this->_get_respond();
        $response = $this->_process_respond();

        // validate the response ...
        // check http-version
        if ($response['status']['http-version'] == 'HTTP/1.1' ||
             $response['status']['http-version'] == 'HTTP/1.0') {
            // seems to be http ... proceed
            // We expect a 207 Multi-Status status code
            // print 'http ok<br>';

            switch ($response['status']['status-code']) {
                case 207:
                    // collection was NOT deleted... see xml response for reason...
                    // next there should be a Content-Type: text/xml; charset="utf-8" header line
                    if (strcmp($response['header']['Content-Type'], 'text/xml; charset="utf-8"') == 0) {
                        // ok let's get the content of the xml stuff
                        $this->_parser = xml_parser_create_ns();
                        // forget old data...
                        unset($this->_delete[$this->_parser]);
                        unset($this->_xmltree[$this->_parser]);
                        xml_parser_set_option($this->_parser,XML_OPTION_SKIP_WHITE,0);
                        xml_parser_set_option($this->_parser,XML_OPTION_CASE_FOLDING,0);
                        xml_set_object($this->_parser, $this);
                        xml_set_element_handler($this->_parser, "_delete_startElement", "_endElement");
                        xml_set_character_data_handler($this->_parser, "_delete_cdata");

                        if (!xml_parse($this->_parser, $response['body'])) {
                            die(sprintf("XML error: %s at line %d",
                                                     xml_error_string(xml_get_error_code($this->_parser)),
                                                     xml_get_current_line_number($this->_parser)));
                        }

                        print_r($this->_delete[$this->_parser]);
                        print "<br>";

                        // Free resources
                        xml_parser_free($this->_parser);
                        $this->_delete[$this->_parser]['status'] = $response['status']['status-code'];
                        return $this->_delete[$this->_parser];

                    } else {
                        print 'Missing Content-Type: text/xml header in response.<br>';
                    }
                    return false;

                default:
                    // collection or file was successfully deleted
                    $this->_delete['status'] = $response['status']['status-code'];
                    return $this->_delete;


            }
        }

    }

 /**    
    * Public method ls
    *
    * Get's directory information from webdav server into flat a array using PROPFIND
    * @param string path
    * @return array dirinfo, false on error
    */
    function ls($path) {
        unset($this->_ls);
        if (trim($path) == '') {
            $this->_error_log('Missing a path in method ls');
            return false;
        }
        $this->_path = $this->_translate_uri($path);

        $this->_header_unset();
        $this->_create_basic_request('PROPFIND');
        $this->_header_add('Depth: 1');
        $this->_header_add('Content-type: text/xml');
        // create profind xml request...
        $xml  = "<?xml version=\"1.0\"?>\r\n";
        $xml .= "<A:propfind xmlns:A=\"DAV:\">\r\n";
        // shall we get all properties ?
        $xml .= "    <A:allprop/>\r\n";
        // or should we better get only wanted props ?
        $xml .= "</A:propfind>\r\n";
        $this->_header_add('Content-length: ' . strlen($xml));
        $this->_send_request();
        $this->_error_log($xml);
        fputs($this->_fp, $xml);
        $this->_get_respond();
        $response = $this->_process_respond();
        // validate the response ... (only basic validation)
        // check http-version
        if ($response['status']['http-version'] == 'HTTP/1.1' ||
             $response['status']['http-version'] == 'HTTP/1.0') {
            // seems to be http ... proceed
            // We expect a 207 Multi-Status status code
            // print 'http ok<br>';
            if (strcmp($response['status']['status-code'],'207') == 0 ) {
                // ok so far
                // next there should be a Content-Type: text/xml; charset="utf-8" header line
                if (strcmp($response['header']['Content-Type'], 'text/xml; charset="utf-8"') == 0) {
                    // ok let's get the content of the xml stuff
                    $this->_parser = xml_parser_create_ns();
                    // forget old data...
                    unset($this->_ls[$this->_parser]);
                    unset($this->_xmltree[$this->_parser]);
                    xml_parser_set_option($this->_parser,XML_OPTION_SKIP_WHITE,0);
                    xml_parser_set_option($this->_parser,XML_OPTION_CASE_FOLDING,0);
                    xml_set_object($this->_parser, $this);
                    xml_set_element_handler($this->_parser, "_propfind_startElement", "_endElement");
                    xml_set_character_data_handler($this->_parser, "_propfind_cdata");


                    if (!xml_parse($this->_parser, $response['body'])) {
                        die(sprintf("XML error: %s at line %d",
                                                 xml_error_string(xml_get_error_code($this->_parser)),
                                                 xml_get_current_line_number($this->_parser)));
                    }

                    // Free resources
                    xml_parser_free($this->_parser);
                    return $this->_ls[$this->_parser];
                } else {
                    $this->_error_log('Missing Content-Type: text/xml header in response!!');
                    return false;
                }
            }
        }

        // response was not http
        $this->_error_log('Ups in method ls: error in response from server');
        return false;
    }


 /**
    * Public method gpi
    * 
    * Get's path information from webdav server for one element
    * @param string path
    * @return array dirinfo. false on error
    */
    function gpi($path) {

        // split path by last "/"
        $path = rtrim($path, "/");
        $item = basename($path);
        $dir  = dirname($path);

        $list = $this->ls($dir);

        // be sure it is an array
        if (is_array($list)) {
            foreach($list as $e) {

                $fullpath = urldecode($e['href']);
                $filename = basename($fullpath);

                if ($filename == $item && $filename != "" and $fullpath != $dir."/") {
                    return $e;
                }
            }
        }
        return false;
    }

 /**
    * Public method is_file
    *
    * Gather whether a path points to a file or not
    * @param string path
    * @return bool true or false
    */
    function is_file($path) {

        $item = $this->gpi($path);

        if ($item === false) {
            return false;
        } else {
            return ($item['resourcetype'] != 'collection');
        }
    }

 /**
    * Public method is_dir
    *
    * Gather whether a path points to a directory
    * @param string path
    * return bool true or false
    */
    function is_dir($path) {
        if(isset($this->_sri_server_dirs[$path]) && $this->_sri_server_dirs[$path] ) {
            return true;
        }
        $item = $this->gpi($path);

        if ($item === false) {
            return false;
        } else {
            if ($item['resourcetype'] == 'collection') {
                $this->_sri_server_dirs[$path] = true;
                // memory leak fix
                unset($item);
                return true;
            } else {
                // memory leak fix
                unset($item);
                return false;
            }
        }
    }



 /**
    * Public method mput
    *
    * Puts multiple files and directories onto a webdav server
    * Param fileList must be in format array("localpath" => "destpath")
    * @param array filelist
    * @return bool true on success. otherwise int status code on error
    */
    function mput($filelist) {

        $result = true;

        while (list($localpath, $destpath) = each($filelist)) {

            $localpath = rtrim($localpath, "/");
            $destpath  = rtrim($destpath, "/");

            // attempt to create target path
            if (is_dir($localpath)) {
                $pathparts = explode("/", $destpath."/ "); // add one level, last level will be created as dir
            } else {
                $pathparts = explode("/", $destpath);
            }
            $checkpath = "";
            for ($i=1; $i<sizeof($pathparts)-1; $i++) {
                $checkpath .= "/" . $pathparts[$i];
                if (!($this->is_dir($checkpath))) {

                    $result &= ($this->mkcol($checkpath) == 201 );
                }
            }

            if ($result) {
                // recurse directories
                if (is_dir($localpath)) {
                    $dp = opendir($localpath);
                    $fl = array();
                    while($filename = readdir($dp)) {
                        if ((is_file($localpath."/".$filename) || is_dir($localpath."/".$filename)) && $filename!="." && $filename != "..") {
                            $fl[$localpath."/".$filename] = $destpath."/".$filename;
                        }
                    }
                    $result &= $this->mput($fl);
                } else {
                    $result &= ($this->put_file($destpath, $localpath) == 201);
                }
            }
        }
        return $result;
    }

 /**
    * Public method mget
    * 
    * Gets multiple files and directories
    * FileList must be in format array("remotepath" => "localpath")
    * @param array filelist
    * @return bool true on succes, other int status code on error
    */
    function mget($filelist) {

        $result = true;

        while (list($remotepath, $localpath) = each($filelist)) {

            $localpath   = rtrim($localpath, "/");
            $remotepath  = rtrim($remotepath, "/");

            // attempt to create local path
            if ($this->is_dir($remotepath)) {
                $pathparts = explode("/", $localpath."/ "); // add one level, last level will be created as dir
            } else {
                $pathparts = explode("/", $localpath);
            }
            $checkpath = "";
            for ($i=1; $i<sizeof($pathparts)-1; $i++) {
                $checkpath .= "/" . $pathparts[$i];
                if (!is_dir($checkpath)) {

                    $result &= mkdir($checkpath);
                }
            }

            if ($result) {
                // recurse directories
                if ($this->is_dir($remotepath)) {
                    $list = $this->ls($remotepath);

                    $fl = array();
                    foreach($list as $e) {
                        $fullpath = urldecode($e['href']);
                        $filename = basename($fullpath);
                        if ($filename != '' and $fullpath != $remotepath . '/') {
                            $fl[$remotepath."/".$filename] = $localpath."/".$filename;
                        }
                    }
                    $result &= $this->mget($fl);
                } else {
                    $result &= ($this->get_file($remotepath, $localpath));
                }
            }
        }
        return $result;
    }
    
    // --------------------------------------------------------------------------

    // private xml callback and helper functions starting here
    // --------------------------------------------------------------------------
    
    
 /**    
    * Private method _endelement 
    *
    * a generic endElement method  (used for all xml callbacks)
    * @param resource parser, string name
    * @access private
    */
    
    function _endElement($parser, $name) {
            $this->_xmltree[$parser] = substr($this->_xmltree[$parser],0, strlen($this->_xmltree[$parser]) - (strlen($name) + 1));
    }

 /**
    * Private method _propfind_startElement
    *
    * Is needed by public method ls.
    * Generic method will called by php xml_parse when a xml start element tag has been detected.
    * The xml tree will translated into a flat php array for easier access.
    * @param resource parser, string name, string attrs
    * @access private
    */ 
    function _propfind_startElement($parser, $name, $attrs) {
        // lower XML Names... maybe break a RFC, don't know ...

        $propname = strtolower($name);
        if ( !isset ($this->_xmltree[$parser]) )
            $this->_xmltree[$parser] = "";
        $this->_xmltree[$parser] .= $propname . '_';
        
        // translate xml tree to a flat array ...
        switch($this->_xmltree[$parser]) {
            case 'dav::multistatus_dav::response_':
                // new element in mu
                $this->_ls_ref =& $this->_ls[$parser][];
                break;
            case 'dav::multistatus_dav::response_dav::href_':
                $this->_ls_ref_cdata = &$this->_ls_ref['href'];
                break;
            case 'dav::multistatus_dav::response_dav::propstat_dav::prop_dav::creationdate_':
                $this->_ls_ref_cdata = &$this->_ls_ref['creationdate'];
                break;
            case 'dav::multistatus_dav::response_dav::propstat_dav::prop_dav::getlastmodified_':
                $this->_ls_ref_cdata = &$this->_ls_ref['lastmodified'];
                break;
            case 'dav::multistatus_dav::response_dav::propstat_dav::prop_dav::getcontenttype_':
                $this->_ls_ref_cdata = &$this->_ls_ref['getcontenttype'];
                break;
            case 'dav::multistatus_dav::response_dav::propstat_dav::prop_dav::getcontentlength_':
                $this->_ls_ref_cdata = &$this->_ls_ref['getcontentlength'];
                break;
            case 'dav::multistatus_dav::response_dav::propstat_dav::prop_dav::lockdiscovery_dav::activelock_dav::depth_':
                $this->_ls_ref_cdata = &$this->_ls_ref['activelock_depth'];
                break;
            case 'dav::multistatus_dav::response_dav::propstat_dav::prop_dav::lockdiscovery_dav::activelock_dav::owner_dav::href_':
                $this->_ls_ref_cdata = &$this->_ls_ref['activelock_owner'];
                break;
            case 'dav::multistatus_dav::response_dav::propstat_dav::prop_dav::lockdiscovery_dav::activelock_dav::owner_':
                $this->_ls_ref_cdata = &$this->_ls_ref['activelock_owner'];
                break;
            case 'dav::multistatus_dav::response_dav::propstat_dav::prop_dav::lockdiscovery_dav::activelock_dav::timeout_':
                $this->_ls_ref_cdata = &$this->_ls_ref['activelock_timeout'];
                break;
            case 'dav::multistatus_dav::response_dav::propstat_dav::prop_dav::lockdiscovery_dav::activelock_dav::locktoken_dav::href_':
                $this->_ls_ref_cdata = &$this->_ls_ref['activelock_token'];
                break;
            case 'dav::multistatus_dav::response_dav::propstat_dav::prop_dav::lockdiscovery_dav::activelock_dav::locktype_dav::write_':
                $this->_ls_ref_cdata = &$this->_ls_ref['activelock_type'];
                $this->_ls_ref_cdata = 'write';
                $this->_ls_ref_cdata = &$this->_null;
                break;
            case 'dav::multistatus_dav::response_dav::propstat_dav::prop_dav::resourcetype_dav::collection_':
                $this->_ls_ref_cdata = &$this->_ls_ref['resourcetype'];
                $this->_ls_ref_cdata = 'collection';
                $this->_ls_ref_cdata = &$this->_null;
                break;
            case 'dav::multistatus_dav::response_dav::propstat_dav::status_':
                $this->_ls_ref_cdata = &$this->_ls_ref['status'];
                break;

            default:
             // handle unknown xml elements...
             $this->_ls_ref_cdata = &$this->_ls_ref[$this->_xmltree[$parser]];
        }
    }

 /** 
    * Private method _propfind_cData
    *
    * Is needed by public method ls.
    * Will be called by php xml_set_character_data_handler() when xml data has to be handled.
    * Stores data found into class var _ls_ref_cdata
    * @param resource parser, string cdata
    * @access private
    */ 
    function _propfind_cData($parser, $cdata) {
        if (trim($cdata) <> '') {
            $this->_ls_ref_cdata = $cdata;
        } else {
            // do nothing
        }
    }

 /**
    * Private method _delete_startElement
    *
    * Is used by public method delete. 
    * Will be called by php xml_parse.
    * @param resource parser, string name, string attrs)
    * @access private
    */
    function _delete_startElement($parser, $name, $attrs) {
        // lower XML Names... maybe break a RFC, don't know ...
        $propname = strtolower($name);
        $this->_xmltree[$parser] .= $propname . '_';
        
        // translate xml tree to a flat array ...
        switch($this->_xmltree[$parser]) {
            case 'dav::multistatus_dav::response_':
                // new element in mu
                $this->_delete_ref =& $this->_delete[$parser][];
                break;
            case 'dav::multistatus_dav::response_dav::href_':
                $this->_delete_ref_cdata = &$this->_ls_ref['href'];
                break;

            default:
             // handle unknown xml elements...
             $this->_delete_cdata = &$this->_delete_ref[$this->_xmltree[$parser]];
        }
    }


 /** 
    * Private method _delete_cData
    *
    * Is used by public method delete.
    * Will be called by php xml_set_character_data_handler() when xml data has to be handled.
    * Stores data found into class var _delete_ref_cdata
    * @param resource parser, string cdata
    * @access private
    */ 
    function _delete_cData($parser, $cdata) {
        if (trim($cdata) <> '') {
            $this->_delete_ref_cdata = $cdata;
        } else {
            // do nothing
        }
    }

    
/**
    * Private method _lock_startElement
    *
    * Is needed by public method lock.
    * Mmethod will called by php xml_parse when a xml start element tag has been detected.
    * The xml tree will translated into a flat php array for easier access.
    * @param resource parser, string name, string attrs
    * @access private
    */ 
    function _lock_startElement($parser, $name, $attrs) {
        // lower XML Names... maybe break a RFC, don't know ...
        $propname = strtolower($name);
        $this->_xmltree[$parser] .= $propname . '_';
    
        // translate xml tree to a flat array ...
        /*
        dav::prop_dav::lockdiscovery_dav::activelock_dav::depth_=
        dav::prop_dav::lockdiscovery_dav::activelock_dav::owner_dav::href_=
        dav::prop_dav::lockdiscovery_dav::activelock_dav::timeout_=
        dav::prop_dav::lockdiscovery_dav::activelock_dav::locktoken_dav::href_=
        */
        switch($this->_xmltree[$parser]) {
            case 'dav::prop_dav::lockdiscovery_dav::activelock_':
                // new element
                $this->_lock_ref =& $this->_lock[$parser][];
                break;
            case 'dav::prop_dav::lockdiscovery_dav::activelock_dav::locktype_dav::write_':
                $this->_lock_ref_cdata = &$this->_lock_ref['locktype'];
                $this->_lock_cdata = 'write';
                $this->_lock_cdata = &$this->_null;
                break;
            case 'dav::prop_dav::lockdiscovery_dav::activelock_dav::lockscope_dav::exclusive_':
                $this->_lock_ref_cdata = &$this->_lock_ref['lockscope'];
                $this->_lock_ref_cdata = 'exclusive';
                $this->_lock_ref_cdata = &$this->_null;
                break;
            case 'dav::prop_dav::lockdiscovery_dav::activelock_dav::depth_':
                $this->_lock_ref_cdata = &$this->_lock_ref['depth'];
                break;
            case 'dav::prop_dav::lockdiscovery_dav::activelock_dav::owner_dav::href_':
                $this->_lock_ref_cdata = &$this->_lock_ref['owner'];
                break;
            case 'dav::prop_dav::lockdiscovery_dav::activelock_dav::timeout_':
                $this->_lock_ref_cdata = &$this->_lock_ref['timeout'];
                break;
            case 'dav::prop_dav::lockdiscovery_dav::activelock_dav::locktoken_dav::href_':
                $this->_lock_ref_cdata = &$this->_lock_ref['locktoken'];
                break;
            default:
             // handle unknown xml elements...
             $this->_lock_cdata = &$this->_lock_ref[$this->_xmltree[$parser]];

        }
    }

 /** 
    * Private method _lock_cData
    *
    * Is used by public method lock.
    * Will be called by php xml_set_character_data_handler() when xml data has to be handled.
    * Stores data found into class var _lock_ref_cdata
    * @param resource parser, string cdata
    * @access private
    */ 
    function _lock_cData($parser, $cdata) {
        if (trim($cdata) <> '') {
            // $this->_error_log(($this->_xmltree[$parser]) . '='. htmlentities($cdata));
            $this->_lock_ref_cdata = $cdata;
        } else {
            // do nothing
        }
    }


 /**
    * Private method _header_add
    * 
    * extends class var array _req  
    * @param string string
    * @access private
    */
    function _header_add($string) {
        $this->_req[] = $string;
    }

 /**
    * Private method _header_unset
    * 
    * unsets class var array _req  
    * @access private
    */

    function _header_unset() {
        unset($this->_req);
    }

 /**
    * Private method _create_basic_request
    * 
    * creates by using private method _header_add an general request header.
    * @param string method
    * @access private
    */
    function _create_basic_request($method) {
        $request = '';
        $this->_header_add(sprintf('%s %s %s', $method, $this->_path, $this->_protocol));
        $this->_header_add(sprintf('Host: %s', $this->_server));
        // $request .= sprintf('Connection: Keep-Alive');
        $this->_header_add(sprintf('User-Agent: %s', $this->_user_agent));
        $this->_header_add(sprintf('Authorization: Basic %s', base64_encode("$this->_user:$this->_pass")));
    }

 /**
    * Private method _send_request
    * 
    * Sends a ready formed http/webdav request to webdav server.
    * @access private
    */
    function _send_request() {
        // check if stream is declared to be open
        // only logical check we are not sure if socket is really still open ...
                //SRIKANTH COMMENTED 'IF' HERE TO MAKE SURE THAT REOPEN OCCURS ALL THE TIME
        //if ($this->_connection_closed) {
            // reopen it
            // be sure to close the open socket.
            $this->close();
            $this->_reopen();
        //}

        // convert array to string
        $buffer = implode("\r\n", $this->_req);
        $buffer .= "\r\n\r\n";
        $this->_error_log($buffer);
        fputs($this->_fp, $buffer);
    }

 /**
    * Private method _get_respond
    * 
    * Read the reponse of the webdav server.
    * Stores data into class vars _header for the header data and
    * _body for the rest of the response.
    * This routine is the weakest part of this class, because it very depends how php does handle a socket stream.
    * If the stream is blocked for some reason php is blocked as well.
    * @access private
    */
    function _get_respond() {
        $this->_error_log('_get_respond()');
        // init vars (good coding style ;-)
        $buffer = '';
        $header = '';
        // attention: do not make max_chunk_size to big....
        $max_chunk_size = 8192;
        // be sure we got a open ressource
        if (! $this->_fp) {
            $this->_error_log('socket is not open. Can not process response');
            return false;
        }

        // following code maybe helps to improve socket behaviour ... more testing needed
        // disabled at the moment ...
        // socket_set_timeout($this->_fp,1 );
        // $socket_state = socket_get_status($this->_fp);

        // read stream one byte by another until http header ends
        $i = 0;
        do {
            $header.=fread($this->_fp,1);
            $i++;
        } while (!preg_match('/\\r\\n\\r\\n$/',$header) && $i < $this->_maxheaderlenth);

        $this->_error_log($header);

        if (preg_match('/Connection: close\\r\\n/', $header)) {
            // This says that the server will close connection at the end of this stream.
            // Therefore we need to reopen the socket, before are sending the next request...
            $this->_error_log('Connection: close found');
            $this->_connection_closed = true;
        }
        // check how to get the data on socket stream
        // chunked or content-length (HTTP/1.1) or
        // one block until feof is received (HTTP/1.0)
        switch(true) {
            case (preg_match('/Transfer\\-Encoding:\\s+chunked\\r\\n/',$header)):
                $this->_error_log('Getting HTTP/1.1 chunked data...');
                do {
                    $byte = '';
                    $chunk_size='';
                    do {
                        $chunk_size.=$byte;
                        $byte=fread($this->_fp,1);
                        // check what happens while reading, because I do not really understand how php reads the socketstream...
                        // but so far - it seems to work here - tested with php v4.3.1 on apache 1.3.27 and Debian Linux 3.0 ...
                        if (strlen($byte) == 0) {
                            $this->_error_log('_get_respond: warning --> read zero bytes');
                        }
                    } while ($byte!="\r" and strlen($byte)>0);      // till we match the Carriage Return
                    fread($this->_fp, 1);                           // also drop off the Line Feed
                    $chunk_size=hexdec($chunk_size);                // convert to a number in decimal system
                    if ($chunk_size > 0) {
                        $buffer .= fread($this->_fp,$chunk_size);
                    }
                    fread($this->_fp,2);                            // ditch the CRLF that trails the chunk
                } while ($chunk_size);                            // till we reach the 0 length chunk (end marker)
                break;

			// check for 204 No Content
            // 204 responds have no body.
            // Therefore we do not need to read any data from socket stream.
            case preg_match('/HTTP\/1\.1\ 204/',$header):
                // nothing to do, just proceed
                $this->_error_log('204 No Content found. No further data to read..');
                break;

            // check for a specified content-length
            case preg_match('/Content\\-Length:\\s+([0-9]*)\\r\\n/',$header,$matches):
                $this->_error_log('Getting data using Content-Length '. $matches[1]);
                // check if we the content data size is small enough to get it as one block
                if ($matches[1] <= $max_chunk_size ) {
                    // only read something if Content-Length is bigger than 0
                    if ($matches[1] > 0 ) {
                        $buffer = fread($this->_fp, $matches[1]);
                    } else {
                        $buffer = '';
                    }
                } else {
                    // data is to big to handle it as one. Get it chunk per chunk...
                    do {
                        $mod = $max_chunk_size % ($matches[1] - strlen($buffer));
                        $chunk_size = ($mod == $max_chunk_size ? $max_chunk_size : $matches[1] - strlen($buffer));
                        $buffer .= fread($this->_fp, $chunk_size);
                        $this->_error_log('mod: ' . $mod . ' chunk: ' . $chunk_size . ' total: ' . strlen($buffer));
                    } while ($mod == $max_chunk_size);
                }
                break;
            default:
                // just get the data until foef appears...
                $this->_error_log('reading until feof...' . $header);
                //socket_set_timeout($this->_fp,0 );
                //while (!feof($this->_fp)) {
                //    $buffer .= fread($this->_fp, 4096);
                    //socket_set_timeout($this->_fp, $this->_socket_timeout);
                //}
                // renew the socket timeout...does it do something ???? Is it needed. More debugging needed...
                //socket_set_timeout($this->_fp, $this->_socket_timeout);
		$buffer = stream_get_contents($this->_fp);
        }

        $this->_header = $header;
        $this->_body = $buffer;
        // $this->_buffer = $header . "\r\n\r\n" . $buffer;
        $this->_error_log($this->_header);
    }



    // --------------------------------------------------------------------------
    // private method _process_respond ...
    // analyse the reponse from server and divide into header and body part
    // returns an array filled with components
 /**
    * Private method _process_respond
    * 
    * Processes the webdav server respond and detects its components (header, body)
    * and returns data array structure.
    * @return array ret_struct
    * @access private
    */
    function _process_respond() {
        $lines = explode("\r\n", $this->_header);
        $header_done = false;
        // $this->_error_log($this->_buffer);
        // First line should be a HTTP status line (see http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html#sec6)
        // Format is: HTTP-Version SP Status-Code SP Reason-Phrase CRLF
        list($ret_struct['status']['http-version'],
                 $ret_struct['status']['status-code'],
                 $ret_struct['status']['reason-phrase']) = explode(' ', $lines[0],3);

        // print "HTTP Version: '$http_version' Status-Code: '$status_code' Reason Phrase: '$reason_phrase'<br>";
        // get the response header fields
        // See http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html#sec6
        for($i=1; $i<count($lines); $i++) {
            if (rtrim($lines[$i]) == '' && !$header_done) {
                $header_done = true;
                // print "--- response header end ---<br>";

            }
            if (!$header_done ) {
                // store all found headers in array ...
                list($fieldname, $fieldvalue) = explode(':', $lines[$i]);
                // check if this header was allready set (apache 2.0 webdav module does this....).
                // If so we add the the value to the end the fieldvalue, separated by comma...
                if ( !isset($ret_struct['header'][$fieldname]) || 
                        ! $ret_struct['header'][$fieldname] ) {
                    $ret_struct['header'][$fieldname] = trim($fieldvalue);
                } else {
                 $ret_struct['header'][$fieldname] .= ',' . trim($fieldvalue);
                }
            }
        }
        // print 'string len of response_body:'. strlen($response_body);
        // print '[' . htmlentities($response_body) . ']';
        $ret_struct['body'] = $this->_body;
        return $ret_struct;
    }

 /**
    * Private method _reopen
    * 
    * Reopens a socket, if 'connection: closed'-header was received from server. 
    * Uses public method open.
    * @access private
    */
    function _reopen() {
        // let's try to reopen a socket
        $this->_error_log('reopen a socket connection');
        return $this->open();
        /*
        $this->_fp = fsockopen ($this->_server, $this->_port, $this->_errno, $this->_errstr, 5);
        set_time_limit(180);
        socket_set_blocking($this->_fp, true);
        socket_set_timeout($this->_fp,5 );
        if (!$this->_fp) {
            $this->_error_log("$this->_errstr ($this->_errno)\n");
            return false;
        } else {
            $this->_connection_closed = false;
            $this->_error_log('reopen ok...' . $this->_fp);
            return true;
        }
        */
    }


 /**
    * Private method _translate_uri
    * 
    * translates an uri to raw url encoded string.
    * Removes any html entity in uri
    * @param string uri
    * @return string translated_uri
    * @access private
    */
    function _translate_uri($uri) {
        // remove all html entities...
        $native_path = html_entity_decode($uri);
        $parts = explode('/', $native_path);
        for ($i = 0; $i < count($parts); $i++) {
            $parts[$i] = rawurlencode($parts[$i]);
        }
        return implode('/', $parts);
    }

    // private method _error_log
    // writes debug information to what's in php.ini defined

 /**
    * Private method _error_log
    * 
    * a simple php error_log wrapper. 
    * @param string err_string
    * @access private
    */
    function _error_log($err_string) {
        if ($this->_debug) {
            error_log($err_string);
        }
    }
}
?>
