<?php
error_reporting(E_ALL);

$adminOptions = get_option(IDriveWpPlugin::$adminOptionName);

$quota_image_width = (int) ($adminOptions['used_quota'] * 100 / ($adminOptions['total_quota'] - $adminOptions['used_quota']));

$total_quota_string = IDriveWpPlugin::get_filesize_str($adminOptions['total_quota']);
$used_quota_string = IDriveWpPlugin::get_filesize_str($adminOptions['used_quota']);

print "<link rel='stylesheet' href='" . $adminOptions['plugin_url'] . "/css/idw_styles.css' type='text/css' />\n";
print "<link rel='stylesheet' href='" . $adminOptions['plugin_url'] . "/css/tt_style.css' type='text/css' />\n";
?>

<script type="text/javascript">

var tooltip0 = 'Logout will <strong>STOP</strong> future backups. Leave this plugin logged in for scheduled bakcups to happen.';

var tooltip1 = '<strong>Next scheduled backup </strong> will start automatically after 12 midnight on the ' + 
               'given date. Backup will be kicked off when someone visits your blog site. <br/> <br/> ' +
               'Please note that for scheduled backups to happen, you must stay logged in. ' +
               'To disable scheduled backups, just log off from the plugin.';

var tooltip2 = '<strong> Use SSL </strong> will not send any file '+
               'if an SSL connection cannot be establlished to IDrive server. This will work ' + 
               'only if PHP is compiled with OpenSSL extension (--with-openssl) for your server. ' +
               ' <br/><br/> <strong> Do not use SSL </strong> does not require SSL connection to IDrive server.';

var tooltip3 = 'Wordpress files and database dump is '+
               'transfered under <strong><?php print htmlspecialchars($adminOptions['remote_backup_location'], ENT_QUOTES) ?> </strong> directory into your IDrive account';

var tooltip4 = 'Entire wordpress data would be restored '+
               "from your online IDrive account to <strong> <?php print (addslashes(IDriveWpPlugin::TFP($adminOptions['plugin_dir'] . '/wordpress_restore/')));?> </strong>";

var tooltip5 = 'Web View and Account Management via IDrive Website. '+
               'You can manage your account as well as restore individual wordpress files or database backup. ';

var tooltip6 = 'Click here to recalculate your quota usage on IDrive server ';

var tooltip7 = 'Exclusion list is for excluding files/folders from the backupset (e.g files under wp-content/cache folder). ' +
               'Enter a complete file/folder name or part of it. If the complete path of the file considered for backup containts the pattern ' +
               'entered, it will be excluded from the backupset. Logs will show all the files which were excluded from backupset<br />  <br />' +
               'e.g. entering wp-content/cache will exclude all files under wp-content/cache <br /> ' +
               'entering .txt will exclude all files which have .txt anywhere in its name';

</script>

<div id="shadow" class="opaqueLayer"></div>

<!-- ###############BEGINING OF INTERVENING HTML ########################## -->
<!-- IDW Plugin starts here-->
<div class="idw_container">
	
	<!-- IDW Header starts-->
	<div class="idw_header">
		<div class="idw_logo">
			<a href="http://www.idrive.com/" target="_blank"><img
				src="<?php print $adminOptions['plugin_url']?>/images/idw_header_logo.jpg"
				height="100" width="153" alt="IDrive - Online Backup" /></a>
		</div>
		<div class="idw_headerbutton">
			<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?> ">
			<table>
			<tr>
			<td>
				<input type="submit" name="idw_logout"
				    onmouseout="tooltip.hide();"
					onmouseover="tooltip.show(tooltip0);"
					value="LOGOUT" />
			</td>
			</tr>
            </table>
			</form>
		</div>
	</div>
	<!-- IDW Header ends-->
	
	<div class="idw_clear"></div>

	<!-- IDW Body starts-->
	<div class="idw_page">
		<noscript>
			<div style="color: red;">
				Javascript is disabled for your web browser. This plugin will not work without javascript support
			</div>
		</noscript>

	   
		<h1>Free online backup of your Wordpress content</h1> 
		
		<!-- Tab Section starts-->
		<div class="idw_tab_container">
		<!-- Tab header starts--> 
		
			<div class="idw_tab_header">
				<div class="idw_userdetails">
					<table>
						<tr>
							<td><span class="idw_username"><?php print ($adminOptions['idrive_username']);?></span>
							    
								<?php
								print("Quota used: <span id=\"idw_quota_text\"> $used_quota_string </span> out of $total_quota_string");
								?>
								
							</td>
							<td>
								<table>
									<tr>
										<td class="idw_quota" align="left">
											<img id="idw_quota_image" src="<?php print $adminOptions['plugin_url'];?>/images/qused.gif" height="17"
												width="<?php print ("$quota_image_width");?>%" 
												alt="Quota Used" align="middle" />
										</td>
									</tr>
								</table>
							</td>
							<td>
								<table>
                                    <tr>
										<td>
										    <img id="idw_recalculate" src="<?php print $adminOptions['plugin_url']?>/images/idw_ico_calci.jpg"
										      onmouseout="tooltip.hide();"
											  onmouseover="tooltip.show(tooltip6);" 
											  onclick="IDriveWpPluginJQuery.recalculateQuota(0);"
										    />
										    <img id="idw_recalculating" src="<?php print $adminOptions['plugin_url']?>/images/idw_recalculating.gif"
										      style="display: none;" />
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
				</div>
				<div class="idw_tabs">
					<a id="idw_backupTab" class="idw_tab_act" href="#"
						onclick="IDriveWpPluginJQuery.viewHome();">Home</a>
					<a id="idw_viewlogsTab" class="idw_tab"
						href="#" onClick="IDriveWpPluginJQuery.viewLogs(1);">View Logs</a></div>
				</div>
				<!-- Tab header ends--> 
				
				<!-- Tab body starts-->

				<div class="idw_tab_bottom" id="idw_home">
					<div class="idw_text_container">
						<!-- Inner container starts-->
						<div class="idw_innercontainer">
							<div class="idw_innertopcontainer"></div>
							<div class="idw_innermidcontainer">
								<table class="idw_table">
								<tr>
									<td><label> Backup Schedule: </label> </td> <td colspan=2> <b> Once A Day</b> </td>
								</tr>
								<tr>
								<td> <label> Next backup: </label>  </td>
								<td><b>
									<?php 
										$next_sch_bkp = wp_next_scheduled('idw_cron_backup_hook');
                    					print (date('M d, Y', $next_sch_bkp));
									?>
								</b>
								</td>
								<td>
								<img src="<?php print $adminOptions['plugin_url']?>/images/idw_qbullet.gif" 
								    onmouseover="tooltip.show(tooltip1);"
									onmouseout="tooltip.hide();"
								 	height="32" width="23" />
								</td>
								</tr>
								</table>

								<div class="liner">
									<img src="<?php print $adminOptions['plugin_url']?>/images/idw_sep.gif" height="1"
										width="100%" />
								</div>
								
								<table class="idw_table">
									<tr>
										<td ><label>Notification</label> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td> 
										<td ><label>File Transfer Option</label></td>
										<td>
											<img src="<?php print $adminOptions['plugin_url']?>/images/idw_qbullet.gif" 
                                                onmouseover="tooltip.show(tooltip2);"
                                                onmouseout="tooltip.hide();"
								 				height="32" width="23" />
										</td>
									</tr>
									<tr>
										<td align="left">
											<input name="idrive_email_notification"
												type="checkbox" id='email_notification'
												onClick='IDriveWpPluginJQuery.setEmailNotification(this.checked);'
												<?php
												if ($adminOptions['email_notification']) print " checked=true ";
												?> /> 
												Send Email
										</td>
										<td colspan=2>
											<input name="must_use_ssl" type="radio" value="yes"
												id='ssl_radio_1'
												<?php if ( $adminOptions['must_use_ssl']) print "checked=true"; //if ($adminOptions['idw_backup_is_in_progress']) print " disabled ";?>
												onClick="IDriveWpPluginJQuery.setSSLOption(this.value);" /> Use SSL 
											<input name="must_use_ssl" type="radio" value="no"
												id='ssl_radio_2'
												<?php if ( ! $adminOptions['must_use_ssl'] ) print "checked=true"; //if ($adminOptions['idw_backup_is_in_progress']) print " disabled ";?>
												onClick="IDriveWpPluginJQuery.setSSLOption(this.value);" /> Do not use SSL
										</td>
									</tr>
								</table>

								<div class="liner">
									<img src="<?php print $adminOptions['plugin_url']?>/images/idw_sep.gif" height="1"
										width="100%" />
								</div>

								<table class="idw_table">
                                    <tr>
                                        <td colspan=3>
                                            <label> Exclusion list </label>
                                        </td>
										<td>
											<img src="<?php print $adminOptions['plugin_url']?>/images/idw_qbullet.gif" 
                                                onmouseover="tooltip.show(tooltip7);"
                                                onmouseout="tooltip.hide();"
								 				height="32" width="23" />
										</td>
                                    </tr>
                                </table>
								<table>
                                    <tr>
                                        <td colspan=3>
                                           <table id='exclusion_list'>
                                           <?php
                                             if ( isset($adminOptions['exclusion_list']) ) {
                                                foreach ( $adminOptions['exclusion_list'] as $exclusion_item ) {
                                                    print "<tr id=\"$exclusion_item\" >\n";
                                                    print "<td> \n";
                                                    print "<div style=\"width: 430px; overflow: hidden;\"> $exclusion_item </div> \n";
                                                    print "</td> \n";
                                                    print "<td> <input type=button value=x " .
                                                          "onClick=\"IDriveWpPluginJQuery.delExclusion('" . $exclusion_item . "')\" />".
                                                          "</td>\n";
                                                    print "</tr>\n";
                                                }
                                              }
                                           ?>
                                           </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan=3>
                                            <input class="idw_btn" id="add_exclusion_btn" type="button"
                                                  name="add_exclusion_btn" value="Add" 
                                                  onClick="IDriveWpPluginJQuery.addExclusion(window.prompt('Enter a complete file/folder name or part of it.', ''));" />
                                        </td>
                                    </tr>
								</table>
								
							</div>

						<div class="idw_innerbotcontainer"></div>
					</div>
					
					<!-- Inner container ends--> 
					
					<!-- Inner container starts-->
					<div class="idw_innercontainer">
						<div class="idw_innertopcontainer"></div>
						<div class="idw_innermidcontainer">
							<span id="idw_backup_status"> 
							
						    <?php 
							if ($adminOptions['backup_in_progress']) {
							?>
							<script type="text/javascript">
									IDriveWpPluginJQuery.getBackupStatus();
							</script>
							
							<table class="idw_table">
								<tr>
									<td><label> Backup is in Progress Now! </label> <br />
                					<?php
                						if ( $adminOptions['backup_stage'] == 1 ) {
                    						print "<br/> Backup Initializing... <br /> <br/> ";
                						} else if ( $adminOptions['backup_stage'] == 3 ) { 
                    						print "<br/> Transfering MySql dump... <br /> <br />";
										} else {
                						?>
                						Started at: <?php print $adminOptions['last_backup_start_time'];?> <br />
                						Files considered: <?php print $adminOptions['num_files_to_backup'];?>
                						out of <?php print $adminOptions['total_files'];?> <br />
                						Files transferred successfully: <?php print $adminOptions['num_files_backup_success'];?>
                						<br />
                						Files remaining: <?php print $adminOptions['num_files_to_backup'] - $adminOptions['num_files_backup_success'];?>
                						<?php
            							} 
                					?>
                					</td>


								</tr>
							</table>
							
							<?php 
							} else {
							?>
							
							<table class="idw_table">
								<tr><td>
								<label> Last Backup Summary </label> <br/>
								<?php 
								if ( $adminOptions['last_backup_status'] != -1 ) {
									?>
								Started at: <?php print $adminOptions['last_backup_start_time'];?> <br/>
								Ended at: <?php print $adminOptions['last_backup_end_time']?> <br/>
								Files considered: <?php print $adminOptions['num_files_to_backup'];?> out of <?php print $adminOptions['total_files'];?> <br/>
								Files transferred successfully: <?php print $adminOptions['num_files_backup_success'];?> <br/>
								<?php 
									if ($adminOptions['last_backup_status'] == 1 ) {
										print "<label>Last backup failed</label>";
									}
									else if ( $adminOptions['last_backup_status'] == 0 ) {
										print "<label>Last backup was successful</label>";
									}
								}
								else {
									print "<br/> No backup started yet! <br/> <br/> ";
								}
								?> 
								</td></tr>
							</table>
							
							<?php 
							}
							?> 
							
							</span>
							
							<div class="liner">
									<img src="<?php print $adminOptions['plugin_url']?>/images/idw_sep.gif" height="1"
										width="100%" />
							</div>
								
							<table>
								<tr>
								<td>
								<input class="idw_btn" id="backup_btn" type="button"
									name="backup_to_idrive_now" value="Backup Now" onClick="IDriveWpPluginJQuery.backupNow();" />
								</td>
								<td>
								    <img src="<?php print $adminOptions['plugin_url']?>/images/idw_qbullet.gif" 
                                                onmouseover="tooltip.show(tooltip3);"
                                                onmouseout="tooltip.hide();"
                                                height="32" width="23" />
								</td>
								</tr>
							</table>
						</div>

						<div class="idw_innerbotcontainer"></div>
					</div>
					
					<!-- Inner container ends-->
					
					<!-- Inner container starts-->
					<div class="idw_innercontainer">
						<div class="idw_innertopcontainer"></div>
						<div class="idw_innermidcontainer">
							<span id="idw_restore_status"> 
							<?php 
								if ($adminOptions['restore_in_progress']) {
							?>
							<script type="text/javascript">
								IDriveWpPluginJQuery.getRestoreStatus();
							</script>
							
							<table class="idw_table">
								<tr><td>
								<label> Restore is in Progress Now! </label> <br />
								Started at: <?php print $adminOptions['last_restore_start_time'];?> <br />
								Files considered: <?php print $adminOptions['num_files_to_restore'];?> <br />
								Files transferred successfully: <?php print $adminOptions['num_files_restore_success'];?> <br />
								Files remaining: <?php print $adminOptions['num_files_to_restore'] - $adminOptions['num_files_restore_success'];?> 
								</td></tr>
							</table>
							
							<?php 
							} else {
							?>
							
							<table class="idw_table">
								<tr><td>
								<label>Last Restore Summary </label> <br />
								<?php 
								if ( $adminOptions['last_restore_status'] != -1 ) {
									?>
								Started at: <?php print $adminOptions['last_restore_start_time'];?> <br />
								Ended at: <?php print $adminOptions['last_restore_end_time']?> <br />
								Files considered: <?php print $adminOptions['num_files_to_restore'];?> <br />
								Files transferred successfully: <?php print $adminOptions['num_files_restore_success'] . " ";?> <br />
								<?php 
									if ($adminOptions['last_restore_status'] == 1 ) {
										print "<label> Last restore failed </label>";
									}
									else if ($adminOptions['last_restore_status'] == 0 ) {
										print "<label> Last restore was successful </label>";
									}
								}
								else {
									print "<br/> No restore started yet! <br/> <br/>";
								}
								?>
								</td></tr>
							</table>
								
							<?php 
							}
							?> 
							</span>
							
							<div class="liner">
									<img src="<?php print $adminOptions['plugin_url']?>/images/idw_sep.gif" height="1"
										width="100%" />
							</div>
							
							<table>
								<tr><td>
								<input class="idw_btn" id="restore_btn" type="button" name="restore_from_idrive_now"
									value="Restore Now" onClick="IDriveWpPluginJQuery.restoreNow();"/>
								</td>
								<td>
                                    <img src="<?php print $adminOptions['plugin_url']?>/images/idw_qbullet.gif" 
                                                onmouseover="tooltip.show(tooltip4);"
                                                onmouseout="tooltip.hide();"
                                                height="32" width="23" />
                                </td>
								<td>
								<form id='idw_web_login_form' method="post" action='https://www.idrive.com/idrivee/servlet/IDELoginServlet' 
							           onsubmit="alert('submitting' + this.USERNAME.value + ' ' + this.PASSWORD.value);">
							        <input type='hidden' id='USERNAME' name='USERNAME' value='' />
							        <input type='hidden' id='PASSWORD' name='PASSWORD' value='' />
	                                <input class="idw_btn" id="idrive_web_btn" type="button" name="goto_idrive_web"
	                                    value="Visit IDrive Web" onClick="IDriveWpPluginJQuery.loginToIDriveWeb();"/>
                                </td>
                                <td>
                                    <img src="<?php print $adminOptions['plugin_url']?>/images/idw_qbullet.gif" 
                                                onmouseover="tooltip.show(tooltip5);"
                                                onmouseout="tooltip.hide();"
                                                height="32" width="23" />
                                </td>
                                </form>
								</tr>
							</table>
						</div>

						<div class="idw_innerbotcontainer"></div>
					</div>
					
					<!-- Inner container ends-->
					
				</div>
			</div>
			<!-- Tab body ends-->
			
			<div class="idw_clear"></div>

<!--  &&&&&&&&&&&&&&&&&&&&&& LOGS &&&&&&&&&&&&&&&&&&&&&&&&&&&&&&& -->

			<div class="idw_tab_bottom" id="idw_viewlogs" style="display: none">
				<div class="idw_loghead">Backup/Restore Logs</div>

				<table cellpadding="0" cellspacing="0" border="0" class="idw_tabarea"
					align="center">
					<tr>
						<td>
						<table border="0" cellspacing="0" cellpadding="0" class="idw_tblhead">
							<tr>
								<td width="100" align="center">&nbsp;&nbsp;&nbsp;Type</td>
								<td width="250">Start Time<span></span></td>
								<td width="450">Summary</td>
								<td align=left>Status</td>
							</tr>
						</table>
						<div class="idw_tblbody" id="idw_logtable">
							<table cellpadding="0" cellspacing="0" border="0">
								<tr>
									<td>&nbsp;</td>
								</tr>
								<tr>
									<td>&nbsp;</td>
								</tr>
								<tr>
									<td align="center">Retrieving ...</td>
								</tr>
								<tr>
									<td>&nbsp;</td>
								</tr>
								<tr>
									<td>&nbsp;</td>
								</tr>
							</table>
						</div>
						</td>
					</tr>
				</table>
				
				<table cellspacing="0" cellpadding="0" border="0" class="idw_pagination">
					<tbody>
						<tr>
							<td align="right">
							<div id="idw_logbuttons">
							<table cellspacing="0" cellpadding="0" border="0" align="right">
								<tbody>
									<tr>
										<td><input type="button" value="Go to page number"></td>
										<td valign="middle">
											<input type="text" class="idw_stxtfld"
												size="3" value="1"></td>
										<td><a href="#">First</a></td>
										<td><a class="show" href="#">1-10 of 0 </a></td>
										<td><a href="#">Next</a></td>
										<td><a href="#">Last</a></td>
									</tr>
								</tbody>
							</table>
							</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<!-- Tab Section ends-->
		
	</div>
	<!-- IDW Body ends -->

<!-- Log details pop up -->
	<div id="idw_logdetails" style="display: none">
		<div class="idw_popcont">
			<div class="idw_popbody">
				<h1>Backup / Restore Logs
				<span class="idw_close"
					onclick="document.getElementById('idw_logdetails').style.display = 'none'; document.getElementById('idw_logfile').innerHTML = ''; IDriveWpPluginMisc.closeshadow();">
				</span></h1>
				
				<div id="idw_logfile" class="idw_details">
					<p>&nbsp;
					</p>
				</div>
			</div>

			<div class="idw_pop_bot">
				<div class="idw_popfooter">
					<input type="button" value="Refresh"
						id="idw_log_refresh_btn" name="0"
						onClick="IDriveWpPluginJQuery.viewLogDetails(getElementById('idw_log_refresh_btn').name)" />
					<input type="button" value="Close"
						onclick="document.getElementById('idw_logdetails').style.display = 'none'; document.getElementById('idw_logfile').innerHTML = ''; IDriveWpPluginMisc.closeshadow();" />
				</div>
			</div>
		</div>
	</div>
	
</div>
<!-- IDW Plugin end -->
