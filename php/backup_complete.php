<table class="idw_table">
	<tr>
		<td><label> Last Backup Summary </label> <br />
		Started at: <?php print $adminOptions['last_backup_start_time'];?> <br />
		Ended at: <?php print $adminOptions['last_backup_end_time']?> <br />
		Files considered: <?php print $adminOptions['num_files_to_backup'];?>
		out of <?php print $adminOptions['total_files'];?> <br />
		Files transferred successfully: <?php print $adminOptions['num_files_backup_success'];?>
		<br />
		<label> <?php 
		if ($adminOptions['last_backup_status'] == 1 ) {
			print "Last backup failed";
		}
		else if ( $adminOptions['last_backup_status'] == 0 ) {
			print "Last backup was successful";
		}
		?> </label></td>
	</tr>
</table>
