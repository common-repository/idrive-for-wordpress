<table class="idw_table">
	<tr>
		<td><label> Restore is in Progress Now! </label> <br />
		
		<?php
           if ( $adminOptions['restore_stage'] == 1 ) {
                print "<br/> Retrieving remote file list... <br/> <br/> ";
            }
            else { 
        ?>
    
		Started at: <?php print $adminOptions['last_restore_start_time'];?> <br />
		Files considered: <?php print $adminOptions['num_files_to_restore'];?>
		<br />
		Files transferred successfully: <?php print $adminOptions['num_files_restore_success'];?>
		<br />
		Files remaining: <?php print $adminOptions['num_files_to_restore'] - $adminOptions['num_files_restore_success'];?>
		</td>
		
		<?php
            } 
		?>
	</tr>
</table>
