<table class="idw_table">
	<tr>
		<td><label>Last Restore Summary </label> <br />
		Started at: <?php print $adminOptions['last_restore_start_time'];?> <br />
		Ended at: <?php print $adminOptions['last_restore_end_time']?> <br />
		Files considered: <?php print $adminOptions['num_files_to_restore'];?>
		<br />
		Files transferred successfully: <?php print $adminOptions['num_files_restore_success'] . " ";?>
		<br />
		<label> <?php 
		if ($adminOptions['last_restore_status'] == 1 ) {
			print "Last restore failed";
		}
		else if ($adminOptions['last_restore_status'] == 0 ) {
			print "Last restore was successful";
		}
		?> </label></td>
	</tr>
</table>
