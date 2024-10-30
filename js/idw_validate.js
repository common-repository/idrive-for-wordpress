/**
 * Author: Diganta Dutta
 * Compnay: Pro-Softnet Corp.
 */

/**
 *	Validation fucntions 
 */

IDriveWpPluginValidate = {};

/**
 * @param form, the form which called the validate
 * @return true on success, failrue otherwise
 */
IDriveWpPluginValidate.validateForm = function(form) {
	if ( form.id == 'idw_login_form' )
		return this.validateLoginForm(form);
	if ( form.id == 'idw_signup_form')
		return this.validateSignupForm(form);
	
	alert("Unknown form " + form.id);
	return false;
};

/**
 * 
 * @param form, the form which called the validate
 * @return true on success, failrue otherwise
 */
IDriveWpPluginValidate.validateLoginForm = function(form) {
	// validate username
	if ( form.idrive_account_username.value == "" ) {
		form.idrive_account_username.focus();
		alert("Username cannot be blank");
		
		return false;
	}	
	if ( ! this.isValidUserName(form.idrive_account_username.value) ) {
		form.idrive_account_username.focus();
		alert("Invalid character in username");
		
		return false;
	}
	
	// validate password
	if ( form.idrive_account_password.value == "" ) {
		form.idrive_account_password.focus();
		alert("Password cannot be blank");
		
		return false;
	}	
	if ( ! this.isValidPassword(form.idrive_account_password.value) ) {
		form.idrive_account_password.focus();
		alert("Invalid character in password");
		
		return false;
	}
	
	return true;
};

/**
 * 
 */
IDriveWpPluginValidate.validateSignupForm = function(form) {
	// validate First name
	if ( form.idrive_account_firstname.value == "" ) {
		form.idrive_account_firstname.focus();
		alert("First Name cannot be blank");
		
		return false;
	}	
	if ( ! this.isValidName(form.idrive_account_firstname.value) ) {
		form.idrive_account_firstname.focus();
		alert("Invalid character in First Name");
		
		return false;
	}
	
	// validate Last name
	if ( form.idrive_account_lastname.value == "" ) {
		form.idrive_account_lastname.focus();
		alert("Last Name cannot be blank");
		
		return false;
	}	
	if ( ! this.isValidName(form.idrive_account_lastname.value) ) {
		form.idrive_account_lastname.focus();
		alert("Invalid character in Last Name");
		
		return false;
	}
	
	// validate username
	if ( form.idrive_account_username.value == "" ) {
		form.idrive_account_username.focus();
		alert("Username cannot be blank");
		
		return false;
	}	
	if ( ! this.isValidUserName(form.idrive_account_username.value) ) {
		form.idrive_account_username.focus();
		alert("Invalid character in username");
		
		return false;
	}
	
	// validate password
	if ( form.idrive_account_password.value != form.idrive_account_confirm_password.value ){
		form.idrive_account_password.focus();
		alert ("Passwords do not match");
		
		return false;
	}
	if ( form.idrive_account_password.value == "" ) {
		form.idrive_account_password.focus();
		alert("Password cannot be blank");
		
		return false;
	}	
	if ( ! this.isValidPassword(form.idrive_account_password.value) ) {
		form.idrive_account_password.focus();
		alert("Invalid character in password");
		
		return false;
	}
	
	// validate email
	if ( form.idrive_account_email_address.value == "" ) {
		form.idrive_account_email_address.focus();
		alert("Email address cannot be blank");
		
		return false;
	}	
	if ( ! this.isValidEmail(form.idrive_account_email_address.value) ) {
		form.idrive_account_email_address.focus();
		alert("Invalid character in email address");
		
		return false;
	}
	
	return true;
};

/**
 * 
 * @param Str, First or Last name
 * @return true/false
 */
IDriveWpPluginValidate.isValidName = function(Str) {
	
	 if ( Str.match(/[^a-zA-Z]+/) )
		 return false;
	 
	 return true;
};

/**
 * 
 * @param Str, email address
 * @return true/false
 */
IDriveWpPluginValidate.isValidEmail = function(Str) {

	if ( Str.match(/[^a-z0-9_A-Z\.\-@]+/) )
		return false;
	 
	 return true;
};

/**
 * 
 * @param Str, the username
 * @return true/false
 */
IDriveWpPluginValidate.isValidUserName = function(Str) {
	 
	 if ( Str.match(/[^a-z0-9_]+/) )
		 return false;
	 
	 return true;
};

/**
 * 
 * @param Str, the password
 * @return true/false
 */
IDriveWpPluginValidate.isValidPassword = function(Str) {

	if ( Str.match( /[^a-z0-9_A-Z]+/ ) )
	 	return false;
	 
	 return true;
};
