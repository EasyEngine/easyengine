<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
fwrite(STDOUT, "Enter your name\n"); // Output - prompt user
$name = fgets(STDIN);                // Read the input
fwrite(STDOUT, "Hello $name");       // Output - Some text
exit(0);                             // Script ran OK
?>
