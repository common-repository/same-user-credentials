=== SUC - same user credentials ===
Contributors: giuliopanda
Tags: Authentication, share login, multisite user, Users Sync
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 1.0.0

It allows you to log in to two or more of your websites using the same credentials.

== Description ==

The plugin synchronizes users with a main site, allowing you to access all sites where the plugin is installed with the same credentials.

- One website must be configured as a server, while the other sites must be configured as clients.
- Users registered on the server site can now access client sites using the same login credentials.
- In the client site, if the user does not exist, a new user is created with the data coming from the server. You can customize the data to be saved on the client site through several hooks described later.
- If the user already exists (checks the username) then the plugin updates the user information.
- If the user exists on the client, but not on the server, the plugin blocks access by changing the password to the user saved on the client.

- For security reasons the plugin does not synchronize administrators.
- When you click on recover password from a client site you are redirected to the server site to recover your password. Once you have recovered the password you return to the client site login. When you try to register a user from a client site you are redirected to the server site to register the user.
- If a user has logged in to a client site and logs in again through cookies, then without logging in again the system updates the user data with the server data once a day. If the user no longer exists on the server, he or she is logged out of the client site.
- Client users are never deleted even if they are no longer present on the server.
- Be careful if a user already exists on the client with the same email, but different user login, the user is not logged in.

== Security ==
Communications take place via APIs protected through an encrypted token system. Usernames and passwords are never passed in clear text or through a basic authentication system.

Synchronizing administrators is not allowed, administrators must be managed locally.

Some user metadata is not passed because it is specific to the configuration of each individual site.

== Logs ==
All operations are logged both on the client site and on the server.


== Customizations ==
By default the plugin synchronizes all user except administrators. By default The plugin synchronizes all user data, roles, and metadata.

However, you can customize who and what to sync through many specially created filters and hooks.


First you may want to choose which users you want to sync and which you don't.   You can choose which user roles you want to sync. This way if the user has a certain role it will be synchronized, otherwise not. You can do this through the sucw-roles-exclude-all-sync-except filter placed in the client site.


**apply_filters('sucw-roles-exclude-all-sync-except', ['subscriber']);**
*(CLIENT)* Excludes all roles from synchronization except those specified
This overrides the filter 'sucw-roles-to-exclude-sync'!
param array $array_exclude the list of default roles ['subscriber']
since 1.0.0


Otherwise you can choose to sync all users except those who have a certain role.


**apply_filters('sucw-roles-to-exclude-sync', ['administrator'])**
*(CLIENT)* These are the roles that do not need to synchronize
If active The filter 'sucw-roles-exclude-all-sync-except' will be ignored
param array $array_exclude the list of default roles ['administrator']
since 1.0.0


The same role configuration entered in the client sites should be placed in the server site.


**add_filter('sucw-roles-exclude-all-sync-except', []);**
*(SERVER)* Exclude all roles from synchronization except those specified
If active the 'sucw-block-user-roles' filter will be ignored
param array $array_exclude the list of default roles []
since 1.0.0


**add_filter('sucw-block-user-roles', ['administrator']);**
*(SERVER)* If the user has one of the blocked roles I won't let them through
var array $block_user_roles
return array
since 1.0.0

### Below are the other filters and hooks you can use to customize your plugin configuration.

**apply_filters('sucw-update-roles', $roles)**
*(CLIENT)* The list of roles to save in the user profile when creating or updating the user. if it is an empty array it does not update the roles.
since 1.0.0

**do_action( 'sucw-update-user', $user_id, $user_data )**
*(CLIENT)* It is called after updating or creating a user
param: int $user_id the user id
object $user_data user data
since 1.0.0

**apply_filters('sucw-remote-args', $args)**
*(CLIENT)* These are the arguments for the client to call the server
param array $args Default ['method':'POST', 'timeout':$timeout, 'redirection':2, 'httpversion':'1.0', 'blocking':true, 'headers':$headers, 'cookies':[]]
since 1.0.0

**apply_filters('sucw-remote-timeout', 15)**
*(CLIENT)* The server call times out
param int $timeout Default 15
since 1.0.0

**apply_filters('sucw-allow-metadata', true)**
*(CLIENT)* Allows you to update metadata
param bool $allow_metadata Allows you to update metadata
if false it does not update the metadata, if it is an array it only updates the metadata present in the array
since 1.0.0

**apply_filters('sucw_register_url', $url)**
*(CLIENT)* Manages the registration link
param string $url il link di default
since 1.0.0

**apply_filters('sucw-lostpassword-url', url)**
*(CLIENT)* Manages lost password link
param string $url il link di default
since 1.0.0

**apply_filters( 'sucw-htaccess', true )**
*(CLIENT)* If the server uses htaccess or you need to make the call to the API via /?rest_route (false)
since 1.0.0

**apply_filters('sucw-api-response', $response, 'login|check-user')**
*(SERVER)* The server's response to the login client api call
param array $response ['response_status'=>'ok', 'user'=>$user] | ['response_status'=>'error', 'message'=>'...']
param string $type login | check-user
since 1.0.0

**apply_filters('sucw-log-limit', 1000)**
(SERVER & CLIENT) The number of logs to keep on both server and client
param int $log_limit Default 1000
since 1.0.0

== TIPS & TRICKS ==

If the user misspells the password, it may appear as an error message that the user does not exist. To make the error messages more generic you can use the following code:

`
add_filter('login_errors', 'login_message', 10, 1);
function login_message($error ) {
    if ($error != '') {
		$error = "Incorrect username or password";
	}
	return $error;
}
`

To add a new role you need to create code like this on both the client and server sites

`
add_role('my_custom_role', 
	__( 'My Custom Role' ), 
	array( 'read' => true, 'read_private_posts' => true, )
);
`

== Installation ==
The plugin must be installed on two or more sites. The first site must be configured as a server, while the others as clients. Remember to save your settings once you have configured the plugin.

**Server:**
Click on the "Server" box and save.

**Client:**
Copy the token generated by the server and paste it into the client's "Token" box. Copy the server URL into the URL. Save.

When you save the client settings it tries to connect to the server to verify that everything is working correctly. If the server does not have active htaccess, the API address changes and the following code must be applied to the client's functions.php:

`
add_filter( 'sucw-htaccess', 'sucw_htaccess' );
 function sucw_htaccess() {
 	return false;
 }
`

== Credits ==
Same user credentials as started in 2024 by Giulio Pandolfelli
Thanks to [Ekebu](https://www.ekebu.com) for the supports.