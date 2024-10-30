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
