/**
 * Author: Diganta Dutta.
 * Company: Pro-Softnet Corp.
 */

/**
 *
 * This file contains all the javascript functions
 * which are called by the UI. Each function acts as 
 * the client part of AJAX
 * 
 */

IDriveWpPluginJQuery = {};

/**
 * this is called when view logs tab is clicked
 */
IDriveWpPluginJQuery.viewLogs = function (offset) {
	// enable the View Logs tab first
	document.getElementById('idw_home').style.display ="none";
	document.getElementById('idw_viewlogs').style.display ="";
	document.getElementById('idw_backupTab').className="idw_tab";
	document.getElementById('idw_viewlogsTab').className="idw_tab_act";
	
	var data = {
            action: 'idw_view_logs',
            offset: offset
    };
    jQuery.post(ajaxurl, data, function(response) {
            //alert('Browse LOg Got this from the server: ' + response);
            var dtls = response.split('|||');
            var pathRef = document.getElementById("idw_logtable"); //alert(pathRef.innerHTML);
            pathRef.innerHTML = dtls[0];
            var pathRef = document.getElementById("idw_logbuttons"); //alert(pathRef.innerHTML);
            pathRef.innerHTML = dtls[1];
    });
};

IDriveWpPluginJQuery.viewLogDetails = function (log_id) {
	// enable the View Logs tab first
	document.getElementById('idw_logdetails').style.display ="";
	
	var data = {
            action: 'idw_view_log_details',
            log_id: log_id
    };
    jQuery.post(ajaxurl, data, function(response) {
    	var pathRef = document.getElementById("idw_logfile");
        pathRef.innerHTML = response;
        document.getElementById('idw_log_refresh_btn').name=log_id;
    });
};

/**
 * This is called when Home tab is clicked
 */
IDriveWpPluginJQuery.viewHome = function () {
	document.getElementById('idw_viewlogs').style.display ="none";
	document.getElementById('idw_home').style.display ="";
	document.getElementById('idw_backupTab').className="idw_tab_act";
	document.getElementById('idw_viewlogsTab').className="idw_tab";
	
	// call get status jQuery here
};

/**
 * This is called when Email Notification check box is clicked
 */
IDriveWpPluginJQuery.setEmailNotification = function (notification) {
	var data = {
        action: 'idw_set_email_notification',
        email_notification: notification
    };
    jQuery.post(
	    		ajaxurl, 
	    		data, 
	    		function(response) {
			    	//response.trim();
			    	if ( response.match(/.*error.*/i) ){
			    		alert(response);
			    		document.getElementById('email_notification').checked = !document.getElementById('email_notification').checked;
			    	}
	    		}
    			);
};

IDriveWpPluginJQuery.delExclusion = function (exclusion) {
    if ( exclusion == null || exclusion  == "" ) 
        return;

    var data = {
        action: 'idw_del_exclusion',
        exclusion: exclusion
    };
    jQuery.post(
                ajaxurl, 
                data, 
                function(response) {
                    //response.trim();
                    if ( response.match(/.*error.*/i) ){
                        alert(response);
                    }
                    else {
                        var row = document.getElementById(exclusion);

                        document.getElementById('exclusion_list').deleteRow(row.rowIndex);
                    }
                }
                );
};

IDriveWpPluginJQuery.addExclusion = function (exclusion) {
    if ( exclusion == null || exclusion  == "" ) 
        return;

    if ( exclusion.match(/[^a-zA-Z0-9_\-\/\.]+/) ) {
        alert("Error: Invalid character. Valid characters are a-z, A-Z, 0-9, _, -, .");
        return;
    }

    var data = {
        action: 'idw_add_exclusion',
        exclusion: exclusion
    };
    jQuery.post(
                ajaxurl, 
                data, 
                function(response) {
                    if ( response.match(/.*error.*/i) ){
                        alert(response);
                    }
                    else {
                        var tbl = document.getElementById('exclusion_list');
                        var row = tbl.insertRow(tbl.rows.length);
                        row.id = exclusion;

                        var cellLeft = row.insertCell(0);
                        var div = document.createElement('div');
                        div.setAttribute("style", "width: 430px; overflow: hidden;");
                        var textNode = document.createTextNode(exclusion);
                        div.appendChild(textNode);
                        cellLeft.appendChild(div);

                        var rightCell = row.insertCell(1);
                        var elemNode = document.createElement('input');
                        elemNode.type = 'button';
                        elemNode.value = 'x';
                        elemNode.setAttribute("onclick", "IDriveWpPluginJQuery.delExclusion('" + exclusion + "')");
                        rightCell.appendChild(elemNode);
                    }
                }
                );
};

/**
 * This is called when ssl option radio button is clicked
 */
IDriveWpPluginJQuery.setSSLOption = function (option) {
	// 1.0.6 - show warning for SSL transfer
    if ( document.getElementById('ssl_radio_1').checked ) {
	 var msg = 'Backup using SSL option may result in increased CPU utilization, and may trigger restrictions as mandated by some hosting providers in a shared hosting environment. Are you sure you want to enable the SSL option?';

	 if ( !confirm(msg) ) {	
		document.getElementById('ssl_radio_2').checked = true;
		return;
	 }
    }

	var data = {
        action: 'idw_set_ssl_option',
        must_use_ssl: option
    };
    jQuery.post(
	    		ajaxurl, 
	    		data, 
	    		function(response) {
			    	//response.trim();
			    	if ( response.match(/.*error.*/i) ){
			    		alert(response);
			    		if ( !document.getElementById('ssl_radio_1').checked )
			    			document.getElementById('ssl_radio_1').checked = true;
			    		else 
			    			document.getElementById('ssl_radio_2').checked = true;
			    	}
	    		}
    			);
};

/**
 * This is called when create new account button is clicked
 */
IDriveWpPluginJQuery.createNewAccount = function (form) {
	var data = {
		action: 'idw_create_new_account',
		firstname: form.idrive_account_firstname.value,
		lastname: 	form.idrive_account_lastname.value,
		username: form.idrive_account_username.value,
		email_address: form.idrive_account_email_address.value,
		password: form.idrive_account_password.value
	};
	
	jQuery.post(
	    		ajaxurl, 
	    		data, 
	    		function(response) {
	    			form.create_idrive_account_button.disabled = false;
	    			
			    	//response.trim();
			    	if ( response.match(/.*error.*/i) ){
			    		alert(response);
			    	}
			    	else {
			    		form.idw_login.value='true';
			    		form.submit();
			    	}
	    		}
				);
};

/**
 * This is called when backup now button is clicked
 */
IDriveWpPluginJQuery.backupNow = function () {
	var data = {
		action: 'idw_backup_now'
	};
	
	jQuery.post(
	    		ajaxurl, 
	    		data, 
	    		function(response) {
			    	//response.trim();
			    	if ( response.match(/.*error.*/i) ){
			    		alert(response);
			    	}
			    	else {
			    		alert(response);
			    		IDriveWpPluginJQuery.getBackupStatus();
			    	}
	    		}
				);
};

/**
 * This is called to get backup status when backup is going on
 */
IDriveWpPluginJQuery.getBackupStatus = function(){
	var data = {
		action : 'idw_get_backup_status'
	};

	jQuery.post(ajaxurl, data, function(response) {
		//response.trim();
		if (response.match(/.*error.*/i)) {
			alert(response);
		} else {
			var responseParts = response.split('|||');
			document.getElementById('idw_backup_status').innerHTML = responseParts[0];
			if ( responseParts[1] == 1 )
				setTimeout('IDriveWpPluginJQuery.getBackupStatus()', 5000);
		}
	});
};

/**
 * This is called when restore now button is clicked
 */
IDriveWpPluginJQuery.restoreNow = function () {
	var data = {
		action: 'idw_restore_now'
	};
	
	jQuery.post(
	    		ajaxurl, 
	    		data, 
	    		function(response) {
			    	//response.trim();
			    	if ( response.match(/.*error.*/i) ){
			    		alert(response);
			    	}
			    	else {
			    		alert(response);
			    		IDriveWpPluginJQuery.getRestoreStatus();
			    	}
	    		}
				);
};

/**
 * This is called to get restore status when restore is going on
 */
IDriveWpPluginJQuery.getRestoreStatus = function(){
	var data = {
		action : 'idw_get_restore_status'
	};

	jQuery.post(ajaxurl, data, function(response) {
		//response.trim();
		if (response.match(/.*error.*/i)) {
			alert(response);
		} else {
			var responseParts = response.split('|||');
			document.getElementById('idw_restore_status').innerHTML = responseParts[0];
			if ( responseParts[1] == 1 )
				setTimeout('IDriveWpPluginJQuery.getRestoreStatus()', 5000);
		}
	});
};

IDriveWpPluginJQuery.loginToIDriveWeb = function () {
	var data = {
			action: 'idw_do_login_to_idrive_web'
	};
	
	jQuery.post(ajaxurl, data, function (response) {
		var reply = response.split('|||');
		
		form = document.getElementById('idw_web_login_form');
		form.USERNAME.value = reply[0];
		form.PASSWORD.value = reply[1];
		form.setAttribute("target", "_blank");
		form.submit();
		form.USERNAME.value = '';
		form.PASSWORD.value = '';
	});
};

IDriveWpPluginJQuery.recalculateQuota = function (count) {
	document.getElementById('idw_recalculate').style.display = "none";
	document.getElementById('idw_recalculating').style.display = "";
	
	var data = {
			action: 'idw_do_recalcualte_quota'
	};
	
	count++;
	jQuery.post(ajaxurl, data, function (response) {
		// FIXME - remove
		//alert(response);
		if ( response == 'recalculating' ){
			if ( count <= 5 ){
				setTimeout("IDriveWpPluginJQuery.recalculateQuota(" + count + ")", 30000);
			}
			else {
				document.getElementById('idw_recalculate').style.display = "";
				document.getElementById('idw_recalculating').style.display = "none";
			}
		}
		else if ( response == 'error' ){
			document.getElementById('idw_recalculate').style.display = "";
			document.getElementById('idw_recalculating').style.display = "none";
		}
		else {
			document.getElementById('idw_recalculate').style.display = "";
			document.getElementById('idw_recalculating').style.display = "none";
			
			var size = response.split('|||');
			
			document.getElementById('idw_quota_text').innerHTML = size[0];
			document.getElementById('idw_quota_image').width = size[1];
		}
	});
};
