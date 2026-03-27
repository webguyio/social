<?php
/*
social.php -- stand-alone social auto-posting script.

Drop social.php into any folder on your site. It has no dependencies and stores
everything in the same folder (social.txt for the timestamp, social.log for the log).

To trigger automatic posting, this file needs to be executed regularly. Options:

- Include it in a high-traffic page: include 'social.php';
- Set up a cron job: curl -s https://yoursite.com/path/to/social.php
- Use a free uptime monitor like UptimeRobot to ping the page that includes it
- Or use the SimpleShare WordPress plugin: wordpress.org/plugins/simpleshare
*/

if ( basename( $_SERVER['PHP_SELF'] ) === 'social.php' ) {
	http_response_code( 404 );
	exit;
}

// Your full site URL, no trailing slash.
define( 'SOCIAL_SITE', '' );

// RSS feed URL to check for new content.
define( 'SOCIAL_RSS', '' );

// Secret key protecting the broadcast form and all setup endpoints.
define( 'SOCIAL_KEY', '' );

// Set to true to enable logging to social.log.
define( 'SOCIAL_LOG', false );

// Facebook: run ?setup=facebook&key=KEY to get your page token.
$fb_app_id       = '';
$fb_app_secret   = '';
$fb_page_id      = '';
$fb_access_token = '';

// Instagram: run ?setup=facebook&key=KEY -- retrieved automatically alongside Facebook
// if your Page has a linked Instagram Business account.
$ig_user_id      = '';
$ig_access_token = '';  // Same as Facebook page token.

// LinkedIn: run ?setup=linkedin&key=KEY to get your token and person ID.
// Tokens expire every 60 days. Re-run setup to refresh.
$li_client_id     = '';
$li_client_secret = '';
$li_person_id     = '';
$li_org_id        = '';  // Optional. Leave blank to skip org posting.
$li_access_token  = '';

// Pinterest: run ?setup=pinterest&key=KEY to get your tokens.
// Access token expires every 30 days. Run ?setup=pinterest_refresh&key=KEY to get a new one.
$pi_app_id        = '';
$pi_app_secret    = '';
$pi_board_id      = '';  // Shown on first connect. Change to any board you own.
$pi_access_token  = '';
$pi_refresh_token = '';

// Reddit: run ?setup=reddit&key=KEY to get your tokens.
// Access token expires in 1 hour. Run ?setup=reddit_refresh&key=KEY to get a new one.
$rd_client_id     = '';
$rd_client_secret = '';
$rd_subreddit     = '';  // A subreddit you own or moderate, without r/.
$rd_username      = '';  // Your Reddit username, shown during setup.
$rd_access_token  = '';
$rd_refresh_token = '';

// X: generate keys in your developer portal, no OAuth flow needed.
$x_api_key       = '';
$x_api_secret    = '';
$x_access_token  = '';
$x_access_secret = '';

$timestamp_file = __DIR__ . '/social.txt';

$setup = isset( $_GET['setup'] ) ? $_GET['setup'] : '';
$key   = isset( $_GET['key'] ) ? $_GET['key'] : '';
$code  = isset( $_GET['code'] ) ? $_GET['code'] : '';

$redirect_fb = SOCIAL_SITE . '/social.php?setup=facebook&key=' . SOCIAL_KEY;
$redirect_li = SOCIAL_SITE . '/social.php?setup=linkedin&key=' . SOCIAL_KEY;
$redirect_pi = SOCIAL_SITE . '/social.php?setup=pinterest&key=' . SOCIAL_KEY;
$redirect_rd = SOCIAL_SITE . '/social.php?setup=reddit&key=' . SOCIAL_KEY;

// Broadcast form: visiting social.php?key=KEY shows a form to send a custom message.
if ( !$setup && SOCIAL_KEY && $key === SOCIAL_KEY ) {
	$sent = false;
	if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
		$text = trim( $_POST['text'] ?? '' );
		$url  = trim( $_POST['url'] ?? '' );
		$img  = trim( $_POST['image_url'] ?? '' );
		$only = isset( $_POST['platforms'] ) ? (array) $_POST['platforms'] : [];
		if ( $text || $url || $img ) {
			$args = [
				'text'      => $text ?: $url,
				'url'       => $url,
				'image_url' => $img,
				'title'     => $text ?: $url,
				'excerpt'   => '',
			];
			social_dispatch( $args, $only );
			$sent = true;
		}
	}
	$platforms = [
		'facebook'  => [ 'label' => 'Facebook',  'needs_image' => false, 'needs_url' => false, 'connected' => $fb_access_token && $fb_page_id ],
		'instagram' => [ 'label' => 'Instagram', 'needs_image' => true,  'needs_url' => false, 'connected' => $ig_user_id && $ig_access_token ],
		'linkedin'  => [ 'label' => 'LinkedIn',  'needs_image' => false, 'needs_url' => false, 'connected' => $li_access_token ],
		'pinterest' => [ 'label' => 'Pinterest', 'needs_image' => true,  'needs_url' => true,  'connected' => $pi_access_token && $pi_board_id ],
		'reddit'    => [ 'label' => 'Reddit',    'needs_image' => false, 'needs_url' => true,  'connected' => $rd_access_token && $rd_subreddit ],
		'x'         => [ 'label' => 'X',         'needs_image' => false, 'needs_url' => false, 'connected' => $x_api_key && $x_access_token ],
	];
	$connected = array_filter( $platforms, fn( $p ) => $p['connected'] );
	?>
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<title>Send a Message</title>
		<style>
		body{font-family:sans-serif;max-width:520px;margin:60px auto;padding:0 20px}
		h2{margin-bottom:20px}
		label{display:block;margin-bottom:4px;font-size:14px;font-weight:600}
		input[type=text],input[type=url],textarea{width:100%;padding:8px 10px;font-size:15px;box-sizing:border-box;border:1px solid #ccc;border-radius:4px;margin-bottom:12px}
		textarea{height:100px;resize:vertical}
		.platforms{display:flex;flex-wrap:wrap;gap:8px 20px;margin-bottom:16px}
		.platform{display:flex;align-items:center;gap:6px;font-size:14px;cursor:pointer}
		.platform.disabled{opacity:.4;pointer-events:none}
		button{padding:10px 24px;font-size:15px;cursor:pointer;background:#1a1a1a;color:#fff;border:0;border-radius:4px}
		button:hover{opacity:.85}
		.sent{color:green;margin-bottom:16px;font-weight:600}
		.none{color:#888;font-size:14px}
		</style>
	</head>
	<body>
		<h2>Send a Message</h2>
		<?php if ( $sent ) : ?><p class="sent">Sent.</p><?php endif; ?>
		<?php if ( $connected ) : ?>
		<form method="post">
			<label>Message <span style="font-weight:400;color:#888">(optional)</span></label>
			<textarea name="text" placeholder="Your message..."><?php echo htmlspecialchars( $_POST['text'] ?? '' ); ?></textarea>
			<label>URL <span style="font-weight:400;color:#888">(optional)</span></label>
			<input type="url" name="url" placeholder="https://..." value="<?php echo htmlspecialchars( $_POST['url'] ?? '' ); ?>">
			<label>Image URL <span style="font-weight:400;color:#888">(optional — required for Instagram and Pinterest)</span></label>
			<input type="url" name="image_url" id="img" placeholder="https://..." value="<?php echo htmlspecialchars( $_POST['image_url'] ?? '' ); ?>">
			<label>Platforms</label>
			<div class="platforms" id="platforms">
			<?php foreach ( $connected as $pid => $meta ) : ?>
				<label class="platform" id="p-<?php echo $pid; ?>"
					data-needs-image="<?php echo $meta['needs_image'] ? '1' : '0'; ?>"
					data-needs-url="<?php echo $meta['needs_url'] ? '1' : '0'; ?>">
					<input type="checkbox" name="platforms[]" value="<?php echo $pid; ?>" checked>
					<?php echo $meta['label']; ?>
				</label>
			<?php endforeach; ?>
			</div>
			<button type="submit">Send</button>
		</form>
		<script>
		(function() {
			var url = document.querySelector('[name=url]');
			var img = document.getElementById('img');
			var labels = document.querySelectorAll('.platform');
			function update() {
				var hasUrl = url.value.trim() !== '';
				var hasImg = img.value.trim() !== '';
				labels.forEach(function(label) {
					var needsUrl = label.dataset.needsUrl === '1';
					var needsImg = label.dataset.needsImage === '1';
					var disabled = (needsUrl && !hasUrl) || (needsImg && !hasImg);
					label.classList.toggle('disabled', disabled);
					label.querySelector('input').checked = !disabled;
				});
			}
			url.addEventListener('input', update);
			img.addEventListener('input', update);
			update();
		})();
		</script>
		<?php else : ?>
		<p class="none">No platforms are connected. Add credentials to social.php to get started.</p>
		<?php endif; ?>
	</body>
	</html>
	<?php
	exit;
}

if ( $setup ) {
	if ( !SOCIAL_KEY || $key !== SOCIAL_KEY ) exit;

	if ( $setup === 'facebook' && !$code ) {
		$auth_url = 'https://www.facebook.com/v25.0/dialog/oauth?' . http_build_query( [
			'client_id'     => $fb_app_id,
			'redirect_uri'  => $redirect_fb,
			'scope'         => 'business_management,pages_show_list,pages_manage_posts,pages_read_engagement,instagram_content_publish,instagram_basic',
			'response_type' => 'code',
			'state'         => bin2hex( random_bytes( 8 ) ),
		] );
		header( 'Location: ' . $auth_url );
		exit;
	}

	if ( $setup === 'facebook' && $code ) {
		$r    = social_curl( 'https://graph.facebook.com/v25.0/oauth/access_token?' . http_build_query( [
			'client_id'     => $fb_app_id,
			'client_secret' => $fb_app_secret,
			'redirect_uri'  => $redirect_fb,
			'code'          => $code,
		] ) );
		$data = json_decode( $r['body'], true );
		if ( empty( $data['access_token'] ) ) { echo 'Error: no access token returned.'; exit; }
		$user_token = $data['access_token'];
		$r          = social_curl( 'https://graph.facebook.com/v25.0/oauth/access_token?' . http_build_query( [
			'grant_type'        => 'fb_exchange_token',
			'client_id'         => $fb_app_id,
			'client_secret'     => $fb_app_secret,
			'fb_exchange_token' => $user_token,
		] ) );
		$long_data  = json_decode( $r['body'], true );
		$long_token = !empty( $long_data['access_token'] ) ? $long_data['access_token'] : $user_token;
		$r          = social_curl( 'https://graph.facebook.com/v25.0/me/accounts?access_token=' . $long_token );
		$pages      = json_decode( $r['body'], true );
		$page_token = '';
		if ( !empty( $pages['data'] ) ) {
			foreach ( $pages['data'] as $page ) {
				if ( $page['id'] === $fb_page_id ) {
					$page_token = $page['access_token'];
					break;
				}
			}
			if ( !$page_token ) $page_token = $pages['data'][0]['access_token'];
		}
		if ( !$page_token ) { echo 'Error: could not retrieve page token. Confirm the logged-in account is a Page admin.'; exit; }
		$r           = social_curl( 'https://graph.facebook.com/v25.0/oauth/access_token?' . http_build_query( [
			'grant_type'        => 'fb_exchange_token',
			'client_id'         => $fb_app_id,
			'client_secret'     => $fb_app_secret,
			'fb_exchange_token' => $page_token,
		] ) );
		$perm        = json_decode( $r['body'], true );
		$final_token = !empty( $perm['access_token'] ) ? $perm['access_token'] : $page_token;
		echo 'Facebook page token: ' . $final_token;
		$r       = social_curl( 'https://graph.facebook.com/v25.0/' . $fb_page_id . '/instagram_accounts?access_token=' . $final_token );
		$ig_data = json_decode( $r['body'], true );
		if ( !empty( $ig_data['data'][0]['id'] ) ) {
			echo '<br>Instagram user ID: ' . $ig_data['data'][0]['id'];
			echo '<br>Instagram access token: ' . $final_token . ' (same as Facebook page token)';
		}
		exit;
	}

	if ( $setup === 'linkedin' && !$code ) {
		$scopes   = [ 'openid', 'profile', 'w_member_social' ];
		if ( $li_org_id ) $scopes[] = 'w_organization_social';
		$auth_url = 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query( [
			'response_type' => 'code',
			'client_id'     => $li_client_id,
			'redirect_uri'  => $redirect_li,
			'scope'         => implode( ' ', $scopes ),
			'state'         => bin2hex( random_bytes( 8 ) ),
		] );
		header( 'Location: ' . $auth_url );
		exit;
	}

	if ( $setup === 'linkedin' && $code ) {
		$r    = social_curl( 'https://www.linkedin.com/oauth/v2/accessToken', http_build_query( [
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => $redirect_li,
			'client_id'     => $li_client_id,
			'client_secret' => $li_client_secret,
		] ), [ 'Content-Type: application/x-www-form-urlencoded' ] );
		$data = json_decode( $r['body'], true );
		if ( empty( $data['access_token'] ) ) { echo 'Error: no access token returned.'; exit; }
		echo 'LinkedIn access token: ' . $data['access_token'];
		$r  = social_curl( 'https://api.linkedin.com/v2/userinfo', null, [
			'Authorization: Bearer ' . $data['access_token'],
			'LinkedIn-Version: ' . gmdate( 'Y' ) . '01',
		] );
		$me = json_decode( $r['body'], true );
		if ( !empty( $me['sub'] ) ) echo '<br>LinkedIn person ID: ' . $me['sub'];
		exit;
	}

	if ( $setup === 'pinterest' && !$code ) {
		$auth_url = 'https://www.pinterest.com/oauth/?' . http_build_query( [
			'client_id'     => $pi_app_id,
			'redirect_uri'  => $redirect_pi,
			'response_type' => 'code',
			'scope'         => 'user_accounts:read,boards:read,boards:write,pins:read,pins:write',
			'state'         => bin2hex( random_bytes( 8 ) ),
		] );
		header( 'Location: ' . $auth_url );
		exit;
	}

	if ( $setup === 'pinterest' && $code ) {
		$r = social_curl( 'https://api.pinterest.com/v5/oauth/token', http_build_query( [
			'grant_type'         => 'authorization_code',
			'code'               => $code,
			'redirect_uri'       => $redirect_pi,
			'continuous_refresh' => 'true',
		] ), [
			'Authorization: Basic ' . base64_encode( $pi_app_id . ':' . $pi_app_secret ),
			'Content-Type: application/x-www-form-urlencoded',
		] );
		$data = json_decode( $r['body'], true );
		if ( empty( $data['access_token'] ) ) { echo 'Error: no access token returned. Confirm your app has Standard access (not Trial).'; exit; }
		echo 'Pinterest access token: ' . $data['access_token'];
		if ( !empty( $data['refresh_token'] ) ) echo '<br>Pinterest refresh token: ' . $data['refresh_token'];
		if ( !$pi_board_id ) {
			$r = social_curl( 'https://api.pinterest.com/v5/boards', null, [
				'Authorization: Bearer ' . $data['access_token'],
			] );
			$b = json_decode( $r['body'], true );
			if ( !empty( $b['items'][0]['id'] ) ) echo '<br>Pinterest board ID (first board): ' . $b['items'][0]['id'];
		}
		exit;
	}

	if ( $setup === 'pinterest_refresh' ) {
		if ( !$pi_refresh_token ) { echo 'No refresh token set in social.php.'; exit; }
		$r    = social_curl( 'https://api.pinterest.com/v5/oauth/token', http_build_query( [
			'grant_type'    => 'refresh_token',
			'refresh_token' => $pi_refresh_token,
		] ), [
			'Authorization: Basic ' . base64_encode( $pi_app_id . ':' . $pi_app_secret ),
			'Content-Type: application/x-www-form-urlencoded',
		] );
		$data = json_decode( $r['body'], true );
		if ( empty( $data['access_token'] ) ) { echo 'Error: could not refresh token. Re-run ?setup=pinterest&key=KEY to re-authorize.'; exit; }
		echo 'New Pinterest access token: ' . $data['access_token'];
		if ( !empty( $data['refresh_token'] ) ) echo '<br>New Pinterest refresh token: ' . $data['refresh_token'];
		echo '<br>Paste these into $pi_access_token and $pi_refresh_token in social.php.';
		exit;
	}

	if ( $setup === 'reddit' && !$code ) {
		$auth_url = 'https://www.reddit.com/api/v1/authorize?' . http_build_query( [
			'client_id'     => $rd_client_id,
			'response_type' => 'code',
			'state'         => bin2hex( random_bytes( 8 ) ),
			'redirect_uri'  => $redirect_rd,
			'duration'      => 'permanent',
			'scope'         => 'submit identity',
		] );
		header( 'Location: ' . $auth_url );
		exit;
	}

	if ( $setup === 'reddit' && $code ) {
		$r = social_curl( 'https://www.reddit.com/api/v1/access_token', http_build_query( [
			'grant_type'   => 'authorization_code',
			'code'         => $code,
			'redirect_uri' => $redirect_rd,
		] ), [
			'Authorization: Basic ' . base64_encode( $rd_client_id . ':' . $rd_client_secret ),
			'User-Agent: web:social.php:1.0',
			'Content-Type: application/x-www-form-urlencoded',
		] );
		$data = json_decode( $r['body'], true );
		if ( empty( $data['access_token'] ) ) { echo 'Error: no access token returned.'; exit; }
		echo 'Reddit access token: ' . $data['access_token'];
		echo '<br>Note: access token expires in 1 hour.';
		if ( !empty( $data['refresh_token'] ) ) {
			echo '<br>Reddit refresh token: ' . $data['refresh_token'];
			echo '<br>Save the refresh token. Run ?setup=reddit_refresh&key=KEY to get a new access token without re-authorizing.';
		}
		$r = social_curl( 'https://oauth.reddit.com/api/v1/me', null, [
			'Authorization: Bearer ' . $data['access_token'],
			'User-Agent: web:social.php:1.0',
		] );
		$me = json_decode( $r['body'], true );
		if ( !empty( $me['name'] ) ) echo '<br>Reddit username: ' . $me['name'];
		exit;
	}

	if ( $setup === 'reddit_refresh' ) {
		if ( !$rd_refresh_token ) { echo 'No refresh token set in social.php.'; exit; }
		$r = social_curl( 'https://www.reddit.com/api/v1/access_token', http_build_query( [
			'grant_type'    => 'refresh_token',
			'refresh_token' => $rd_refresh_token,
		] ), [
			'Authorization: Basic ' . base64_encode( $rd_client_id . ':' . $rd_client_secret ),
			'User-Agent: web:social.php:1.0 (by /u/' . $rd_username . ')',
			'Content-Type: application/x-www-form-urlencoded',
		] );
		$data = json_decode( $r['body'], true );
		if ( empty( $data['access_token'] ) ) { echo 'Error: could not refresh token. Re-run ?setup=reddit&key=KEY to re-authorize.'; exit; }
		echo 'New Reddit access token: ' . $data['access_token'];
		echo '<br>Paste this into $rd_access_token in social.php.';
		exit;
	}

	exit;
}

function social_curl( $url, $post = null, $headers = [] ) {
	$ch = curl_init( $url );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 15 );
	if ( $post !== null ) {
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $post );
	}
	if ( $headers ) curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
	$body   = curl_exec( $ch );
	$status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );
	return [ 'status' => $status, 'body' => $body ];
}

function social_log( $label, $status, $response ) {
	if ( !SOCIAL_LOG ) return;
	$entry = '[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $label . ': ' . $status . ' ' . substr( $response, 0, 200 ) . "\n";
	file_put_contents( __DIR__ . '/social.log', $entry, FILE_APPEND );
}

function post_to_facebook( $args ) {
	global $fb_page_id, $fb_access_token;
	if ( !$fb_page_id || !$fb_access_token ) return;
	$payload = $args['url'] ? [ 'link' => $args['url'] ] : [ 'message' => $args['text'] ];
	$r       = social_curl(
		'https://graph.facebook.com/v25.0/' . $fb_page_id . '/feed',
		json_encode( array_merge( $payload, [ 'access_token' => $fb_access_token ] ) ),
		[ 'Content-Type: application/json' ]
	);
	social_log( 'Facebook', $r['status'], $r['body'] );
}

function post_to_instagram( $args ) {
	global $ig_user_id, $ig_access_token;
	if ( !$ig_user_id || !$ig_access_token || !$args['image_url'] ) return;
	$base = 'https://graph.facebook.com/v25.0/' . $ig_user_id;
	$r    = social_curl( $base . '/media', json_encode( [
		'image_url'    => $args['image_url'],
		'caption'      => $args['text'],
		'access_token' => $ig_access_token,
	] ), [ 'Content-Type: application/json' ] );
	$data = json_decode( $r['body'], true );
	if ( empty( $data['id'] ) ) { social_log( 'Instagram', $r['status'], $r['body'] ); return; }
	$container_id = $data['id'];
	$ready        = false;
	for ( $i = 0; $i < 10; $i++ ) {
		sleep( 3 );
		$s    = social_curl( 'https://graph.facebook.com/v25.0/' . $container_id . '?fields=status_code&access_token=' . $ig_access_token );
		$sd   = json_decode( $s['body'], true );
		$code = $sd['status_code'] ?? '';
		if ( $code === 'FINISHED' ) { $ready = true; break; }
		if ( $code === 'ERROR' || $code === 'EXPIRED' ) { social_log( 'Instagram', 0, 'Container ' . $code . ': ' . $s['body'] ); return; }
	}
	if ( !$ready ) { social_log( 'Instagram', 0, 'Container did not finish processing after 30 seconds.' ); return; }
	$r = social_curl( $base . '/media_publish', json_encode( [
		'creation_id'  => $container_id,
		'access_token' => $ig_access_token,
	] ), [ 'Content-Type: application/json' ] );
	social_log( 'Instagram', $r['status'], $r['body'] );
}

function post_to_linkedin( $args ) {
	global $li_person_id, $li_org_id, $li_access_token;
	if ( !$li_access_token ) return;
	if ( $li_person_id ) social_linkedin_post( $args, 'urn:li:person:' . $li_person_id );
	if ( $li_org_id ) social_linkedin_post( $args, 'urn:li:organization:' . $li_org_id );
}

function social_linkedin_upload_image( $image_url, $author_urn ) {
	global $li_access_token;
	$ch = curl_init( $image_url );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 15 );
	$img_body = curl_exec( $ch );
	curl_close( $ch );
	if ( !$img_body ) return null;
	$headers = [
		'Authorization: Bearer ' . $li_access_token,
		'Content-Type: application/json',
		'LinkedIn-Version: ' . gmdate( 'Y' ) . '01',
		'X-Restli-Protocol-Version: 2.0.0',
	];
	$init = social_curl( 'https://api.linkedin.com/rest/images?action=initializeUpload', json_encode( [
		'initializeUploadRequest' => [ 'owner' => $author_urn ],
	] ), $headers );
	$init_d = json_decode( $init['body'], true );
	if ( empty( $init_d['value']['uploadUrl'] ) || empty( $init_d['value']['image'] ) ) return null;
	$ch = curl_init( $init_d['value']['uploadUrl'] );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PUT' );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $img_body );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, [
		'Authorization: Bearer ' . $li_access_token,
		'Content-Type: application/octet-stream',
	] );
	curl_exec( $ch );
	curl_close( $ch );
	return $init_d['value']['image'];
}

function social_linkedin_post( $args, $author_urn ) {
	global $li_access_token;
	$post = [
		'author'       => $author_urn,
		'commentary'   => $args['text'],
		'visibility'   => 'PUBLIC',
		'distribution' => [
			'feedDistribution'               => 'MAIN_FEED',
			'targetEntities'                 => [],
			'thirdPartyDistributionChannels' => [],
		],
		'lifecycleState'            => 'PUBLISHED',
		'isReshareDisabledByAuthor' => false,
	];
	if ( !empty( $args['image_url'] ) ) {
		$image_urn = social_linkedin_upload_image( $args['image_url'], $author_urn );
		if ( $image_urn ) $post['content'] = [ 'media' => [ 'id' => $image_urn ] ];
	}
	$r = social_curl( 'https://api.linkedin.com/rest/posts', json_encode( $post ), [
		'Authorization: Bearer ' . $li_access_token,
		'Content-Type: application/json',
		'LinkedIn-Version: ' . gmdate( 'Y' ) . '01',
		'X-Restli-Protocol-Version: 2.0.0',
	] );
	$label = strpos( $author_urn, 'organization' ) !== false ? 'LinkedIn (org)' : 'LinkedIn (person)';
	social_log( $label, $r['status'], $r['body'] );
}

function post_to_pinterest( $args ) {
	global $pi_app_id, $pi_app_secret, $pi_access_token, $pi_refresh_token, $pi_board_id;
	if ( !$pi_access_token || !$pi_board_id || !$args['image_url'] ) return;
	if ( $pi_refresh_token ) {
		$r = social_curl( 'https://api.pinterest.com/v5/oauth/token', http_build_query( [
			'grant_type'    => 'refresh_token',
			'refresh_token' => $pi_refresh_token,
		] ), [
			'Authorization: Basic ' . base64_encode( $pi_app_id . ':' . $pi_app_secret ),
			'Content-Type: application/x-www-form-urlencoded',
		] );
		$data = json_decode( $r['body'], true );
		if ( !empty( $data['access_token'] ) ) {
			$pi_access_token  = $data['access_token'];
			$pi_refresh_token = $data['refresh_token'] ?? $pi_refresh_token;
			social_log( 'Pinterest', 0, 'Token refreshed.' );
		}
	}
	$r = social_curl( 'https://api.pinterest.com/v5/pins', json_encode( [
		'board_id'     => $pi_board_id,
		'title'        => $args['title'],
		'description'  => $args['excerpt'],
		'link'         => $args['url'],
		'media_source' => [ 'source_type' => 'image_url', 'url' => $args['image_url'] ],
	] ), [
		'Authorization: Bearer ' . $pi_access_token,
		'Content-Type: application/json',
	] );
	social_log( 'Pinterest', $r['status'], $r['body'] );
}

function post_to_reddit( $args ) {
	global $rd_access_token, $rd_subreddit, $rd_username;
	if ( !$rd_access_token || !$rd_subreddit || !$args['url'] ) return;
	$r = social_curl( 'https://oauth.reddit.com/api/submit', http_build_query( [
		'sr'       => $rd_subreddit,
		'kind'     => 'link',
		'title'    => $args['title'] ?: $args['text'],
		'url'      => $args['url'],
		'resubmit' => 'true',
	] ), [
		'Authorization: Bearer ' . $rd_access_token,
		'User-Agent: web:social.php:1.0 (by /u/' . $rd_username . ')',
		'Content-Type: application/x-www-form-urlencoded',
	] );
	social_log( 'Reddit', $r['status'], $r['body'] );
}

function post_to_x( $args ) {
	global $x_api_key, $x_api_secret, $x_access_token, $x_access_secret;
	if ( !$x_api_key || !$x_access_token ) return;
	$url   = 'https://api.twitter.com/2/tweets';
	$body  = json_encode( [ 'text' => $args['url'] ?: $args['text'] ] );
	$oauth = [
		'oauth_consumer_key'     => $x_api_key,
		'oauth_nonce'            => bin2hex( random_bytes( 16 ) ),
		'oauth_signature_method' => 'HMAC-SHA1',
		'oauth_timestamp'        => time(),
		'oauth_token'            => $x_access_token,
		'oauth_version'          => '1.0',
	];
	$base_params = $oauth;
	ksort( $base_params );
	$param_string             = http_build_query( $base_params, '', '&', PHP_QUERY_RFC3986 );
	$base_string              = 'POST&' . rawurlencode( $url ) . '&' . rawurlencode( $param_string );
	$signing_key              = rawurlencode( $x_api_secret ) . '&' . rawurlencode( $x_access_secret );
	$oauth['oauth_signature'] = base64_encode( hash_hmac( 'sha1', $base_string, $signing_key, true ) );
	ksort( $oauth );
	$parts = [];
	foreach ( $oauth as $k => $v ) $parts[] = rawurlencode( $k ) . '="' . rawurlencode( $v ) . '"';
	$r = social_curl( $url, $body, [
		'Authorization: OAuth ' . implode( ', ', $parts ),
		'Content-Type: application/json',
	] );
	social_log( 'X', $r['status'], $r['body'] );
}

function social_dispatch( $args, $only = [] ) {
	if ( !$only || in_array( 'facebook', $only ) ) post_to_facebook( $args );
	if ( !$only || in_array( 'instagram', $only ) ) post_to_instagram( $args );
	if ( !$only || in_array( 'linkedin', $only ) ) post_to_linkedin( $args );
	if ( !$only || in_array( 'pinterest', $only ) ) post_to_pinterest( $args );
	if ( !$only || in_array( 'reddit', $only ) ) post_to_reddit( $args );
	if ( !$only || in_array( 'x', $only ) ) post_to_x( $args );
}

if ( !SOCIAL_RSS ) exit;
$xml = @simplexml_load_string( @file_get_contents( SOCIAL_RSS ) );
if ( !$xml ) exit;
$channel = $xml->channel;
if ( empty( $channel->item ) ) exit;
$items = [];
foreach ( $channel->item as $item ) {
	$image_url = '';
	foreach ( $item->enclosure as $enc ) {
		$image_url = (string) $enc['url'];
		break;
	}
	if ( !$image_url ) {
		$media = $item->children( 'media', true );
		if ( !empty( $media->content ) ) $image_url = (string) $media->content['url'];
	}
	$items[] = [
		'title'     => (string) $item->title,
		'url'       => (string) $item->link,
		'image_url' => $image_url,
		'excerpt'   => strip_tags( (string) $item->description ),
		'pubdate'   => strtotime( (string) $item->pubDate ),
		'text'      => '',
	];
}
if ( empty( $items ) ) exit;
usort( $items, fn( $a, $b ) => $a['pubdate'] - $b['pubdate'] );
$last = file_exists( $timestamp_file ) ? (int) file_get_contents( $timestamp_file ) : 0;
if ( $last === 0 ) {
	$item         = end( $items );
	$item['text'] = $item['title'] . "\n\n" . $item['excerpt'] . "\n\n" . $item['url'];
	social_dispatch( $item );
	$written = file_put_contents( $timestamp_file, time() );
	social_log( 'social.txt', $written !== false ? 'updated' : 'failed', '' );
	exit;
}
$new_items = array_filter( $items, fn( $item ) => $item['pubdate'] > $last );
foreach ( $new_items as $item ) {
	$item['text'] = $item['title'] . "\n\n" . $item['excerpt'] . "\n\n" . $item['url'];
	social_dispatch( $item );
}
if ( !empty( $new_items ) ) {
	$written = file_put_contents( $timestamp_file, time() );
	social_log( 'social.txt', $written !== false ? 'updated' : 'failed', '' );
}