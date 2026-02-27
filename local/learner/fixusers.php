<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/user/lib.php');

global $DB;

// 🔍 Target users (adjust condition if needed)
$sql = "SELECT u.*
        FROM {local_leaner_email} lu
        JOIN {user} u on u.id=lu.userid
        WHERE u.auth = 'manual'
          AND u.suspended = 0
          AND u.deleted = 0";

$users = $DB->get_records_sql($sql);
$i=1;
foreach ($users as $user) {

    // Ensure context exists
    context_user::instance($user->id);

    // Set new password (temporary)
    update_internal_user_password($user, 'Integer@123');

    // Force password change
    set_user_preference('auth_forcepasswordchange', 1, $user->id);

    echo "Password fixed for: {$user->username}\n";
    echo $i;
    echo "<br>";
    $i++;
}

echo "DONE\n";
