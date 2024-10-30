<?php
$adminOptions = get_option(IDriveWpPlugin::$adminOptionName);

// include CSS
print "<link rel='stylesheet' href='" . $adminOptions['plugin_url'] . "/css/idw_styles.css' type='text/css' />\n";

?>

<div id="shadow" class="opaqueLayer"></div>


<!-- %%%%%%%%%%%%%%%% BEGINING OF LOGIN HTML %%%%%%%%%%%%%%%%%%%%%%%% -->
<!-- IDrive Wordpress Plugin Starts Here -->
<div
	class="idw_container">
	
	<!-- Header Starts Here-->
    <div class="idw_header">
		<div class="idw_logo"><a href="http://www.idrive.com/" target='_blank'> <img
			src="<?php print $adminOptions['plugin_url']?>/images/idw_header_logo.jpg"
			height="100" width="153" alt="IDrive - Online Backup" /> </a></div>
		</div>
    <!-- Header Ends Here -->

    <div class="idw_clear"></div>

    <!-- IDW Body Section starts Here-->
    <div class="idw_page">
    
        <!-- Tab Section Starts here -->
		<h1>Free online backup of your Wordpress content</h1>
		<p>IDrive offers 5 GB of free online storage for your Wordpress files
		and MySql database. IDrive for Wordpress plugin will allow automatically uploading
		your wordpress files and database dump into your IDrive online account.</p>

		<div class="idw_tab_container">
		<!-- Tab Header Starts Here -->
		<div class="idw_tab_header">
			<div class="idw_tabs"><a id="idw_backupTab" class="idw_tab_act" href="#">Login</a>
			</div>
		</div>
        <!-- Tab Header Ends Here --> 
        
        <!-- Tab Body Starts Here -->
		<div class="idw_tab_bottom">
		  <div class="idw_text_container">
              <noscript>
			     <div style="color: red;">
                     Javascript is disabled for your web browser. This plugin will not work without javascrip support.
                 </div>
              </noscript>
		      <form id="idw_login_form" method="post"
				action="<?php echo $_SERVER["REQUEST_URI"];?>" onSubmit="return false;">
			     <div id='error' style="color: red;"><?php isset($this->errorMsg) && print($this->errorMsg);?> </div>
			     <p>
				 <label for="idrive_account_username">Username:</label> 
				 <input type="text" class="idw_txt" id="idrive_account_username"
						name="idrive_account_username" />
				 </p>
			     <p>
			     <label for="idrive_account_password">Password:</label> 
			     <input type="password" class="idw_txt" id="idrive_account_password"
					name="idrive_account_password" size="20">
				 </p>

				<div class="idw_btn">
				    <input type="hidden" name="idw_login" value="true" />
				    
				    <input class="idw_btn" type="submit" value="Login"
					   onClick="IDriveWpPluginValidate.validateForm(document.getElementById('idw_login_form')) && submit();" />
				</div>
				
				<div class="idw_liner">
				    <img  src="<?php print $adminOptions['plugin_url']?>/images/idw_sep.gif"
					   height="1" width="100%" />
			    </div>

                <div class="idw_clear"></div>

				<p>
				<input type="button" value="Create New IDrive Account"
					class="idw_btn"
					onclick="javascript: IDriveWpPluginMisc.openshadow(); document.getElementById('idw_signup').style.display = ''; document.getElementById('idrive_account_firstname').focus();" 
					/>
				</p>
              </form>
            </div>
        </div>
        </div>
    </div>
<!-- Tab Body Ends Here -->
</div>
<!-- Tab Section ends here -->

<div id="idw_signup" style="display: none">
    <div class="idw_popcont">
        <form id="idw_signup_form" method="post"
	       action="<?php echo $_SERVER["REQUEST_URI"]; ?>" onSubmit="return false;">
	       
            <div class="idw_popbody">
            <h1>Sign up<span class="idw_close"
	               onclick="javascript: IDriveWpPluginMisc.closeshadow(); document.getElementById('idw_signup').style.display = 'none';"></span></h1>
            <div class="idw_signuptab">
				<p>Even if you have a regular IDrive account, you must register for a
				Wordpress specific IDrive account for backup.</p>
				
				<table cellpadding="0" cellspacing="0" border="0" align="center"
					width="100%">
					<tr>
						<td><label>First Name</label> <br />
						<input type="text" id="idrive_account_firstname"
							name="idrive_account_firstname" value="" size="20" /></td>
						<td><label>Last Name</label> <br />
						<input type="text" id="idrive_account_lastname"
							name="idrive_account_lastname" value="" size="20" /></td>
					</tr>
					<tr>
						<td><label>Username</label> <br/> (Characters allowed: a-z, 0-9, underscore) <span id="idw_username_result"
							style="display: none; color: red;"> &nbsp; Username already exists</span>
						<br />
						<input type="text" id="idrive_account_username"
							name="idrive_account_username" value="" size="20" /></td>
						<td><label>Email Address</label> <br />
						<input type="text" id="idrive_account_email_address"
							name="idrive_account_email_address" value="" size="20" /></td>
					</tr>
					<tr>
						<td><label>Password</label> <br /> (Characters allowed: A-Z, a-z, 0-9, underscore)
						<br />
						<input type="password" id="idrive_account_password"
							name="idrive_account_password" value="" size="20" /></td>
						<td><label>Confirm Password</label> <br />
						<input type="password" id="idrive_account_confirm_password"
							name="idrive_account_confirm_password" value="" size="20" /></td>
					</tr>
				</table>

			<div class="idw_liner"><img
				src="<?php print $adminOptions['plugin_url']?>/images/idw_sep.gif"
				height="1" width="100%" /></div>
			</div>
        </div>

        <div class="idw_pop_bot">
        
			<div class="idw_popfooter"><input type="hidden" name="idw_login"
				value="false" /> <input type="submit"
				name="create_idrive_account_button" value="Sign up for new account"
				onClick="this.disabled = true; IDriveWpPluginValidate.validateForm(document.getElementById('idw_signup_form')) && IDriveWpPluginJQuery.createNewAccount(document.getElementById('idw_signup_form')) || (this.disabled = false);" 
				onblur="document.getElementById('idrive_account_firstname').focus();" />
			<br />
			By clicking on Sign up for new account, you agree to the <a
				href="http://www.idrive.com/terms.htm#5b" target='_blank'>Terms of Service.</a>
			</div>
        </div>
        
        </form>
    </div>
</div>
<!-- IDW Body Section Ends Here -->
<!-- IDrive Wordpress Plugin Ends Here -->
