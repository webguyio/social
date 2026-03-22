# Social Auto-Posting Setup Guide

This guide covers setting up auto-posting to Facebook, Instagram, LinkedIn, Pinterest, Reddit, and X using either the **SimpleShare WordPress plugin** or the standalone **social.php** script.

Both tools use the same underlying platform APIs and require the same developer apps and credentials. The difference is how credentials are stored and how the auth flow is initiated:

- **SimpleShare plugin**: Credentials are saved in WordPress settings. OAuth flows are handled automatically via the plugin's Connect buttons.
- **social.php script**: Credentials are stored directly in the file. OAuth flows are initiated by visiting setup URLs in your browser, which display tokens on screen for you to paste back into the file.

Platform-specific notes apply to both unless marked otherwise.

---

## Before You Start

Each platform requires you to register as a developer and create an app. This is a one-time setup per platform. Once complete, the app lives permanently in your developer account and the credentials never change (with some exceptions noted per platform).

You will always need:
- A personal account on the platform (the one you want to post from or that manages the page you want to post to)
- Access to that platform's developer portal
- A publicly accessible website URL (staging sites, localhost, and password-protected sites will cause failures on most platforms)

---

## Facebook

### Overview

Facebook requires a Meta developer app to post to a Page. The app uses **Facebook Login for Business** (not regular Facebook Login) and requires a **Page access token**, not a user access token. Tokens obtained correctly are permanent and never expire.

### 1. Create a developer account

- Go to [developers.facebook.com](https://developers.facebook.com) and log in with the Facebook account that manages your Page
- Click **Get Started** and follow the registration prompts (requires phone number verification)

### 2. Create an app

- Click **My Apps > Create App**
- When asked for a use case, select **Manage everything on your Page**
- Connect your Business Portfolio if prompted, or skip
- Click **Create App** and confirm your password

The app creation flow has changed from a product-based to use-case-based system. Any tutorial referencing app types like "Business," "Consumer," or "None" is outdated. Once a use case is added it cannot be removed. Do not add the "Authenticate and request data from users with Facebook Login" use case (it is incompatible with page management).

### 3. Configure basic settings

- Go to **App Settings > Basic**
- Add your domain to **App Domains** (e.g. `yoursite.com`)
- Add your site URL to **Privacy Policy URL**
- Save Changes

### 4. Add Facebook Login for Business

- In the left sidebar, click **Add Product**
- Find **Facebook Login for Business** and click **Set Up**
- Go to **Facebook Login for Business > Settings**
- Enable **Client OAuth Login** and **Web OAuth Login**
- Add your redirect URI under **Valid OAuth Redirect URIs**:
  - **Plugin**: Use the URL shown in the plugin's Facebook setup instructions panel
  - **social.php**: `https://yoursite.com/social.php?setup=facebook&key=[SOCIAL-KEY-HERE]`
- Save Changes

### 5. Switch to Live Mode

Toggle your app to **Live Mode** using the switch at the top of the App Dashboard. Posts made in Development Mode are only visible to app admins (not to the public). Live Mode is required for posts to appear publicly.

The required permissions (`pages_manage_posts`, `pages_read_engagement`, `pages_show_list`, `business_management`) are all available at Standard Access and do not require App Review. They work immediately once your app is live.

### 6. Get your credentials

- Go to **App Settings > Basic**
- Copy **App ID** and **App Secret** (click Show to reveal the secret)

### 7. Get your Page ID (social.php only)

The plugin discovers your Page ID automatically during the auth flow. For social.php, you need to find it manually:

- Go to your Facebook Page in a browser on desktop
- Click **About** in the left sidebar and scroll to the bottom (the Page ID is listed there)
- Alternatively, view the page source and search for `"pageID"` or `"page_id"`

### 8. Connect

**Plugin**: Enter App ID and App Secret in the Facebook settings panel, then click **Connect Facebook**. The plugin handles the token exchange and page discovery automatically, and also attempts to connect Instagram if your Page has a linked Instagram Business account.

**social.php**:
```php
$fb_app_id       = '[FACEBOOK-APP-ID]';
$fb_app_secret   = '[FACEBOOK-APP-SECRET]';
$fb_page_id      = '[FACEBOOK-PAGE-ID]';
```

Then visit the auth flow URL:
`https://yoursite.com/social.php?setup=facebook&key=[SOCIAL-KEY-HERE]`

After authorizing, you will be shown your page token:
```
Facebook page token: [TOKEN]
```

Add it to social.php:
```php
$fb_access_token = '[FACEBOOK-PAGE-TOKEN]';
```

### Token permanence

Page access tokens obtained via the plugin or social.php are permanent and do not expire under normal circumstances. They are invalidated if you change your Facebook password, lose admin access to the page, or manually revoke the app's access. If posts suddenly stop working, run the auth flow again to get a fresh token.

### Troubleshooting Facebook

| Error | Cause | Fix |
|-------|-------|-----|
| Posts not appearing publicly | App in Development Mode | Switch app to Live Mode |
| Error 190 | Token expired or revoked | Re-run the auth flow |
| Error 200 | Missing permissions | Verify pages_manage_posts is added |
| Error 100 | Wrong token type (user instead of page) | Re-run auth flow (plugin/script handles this correctly) |
| Empty accounts array | business_management or pages_show_list missing from scope, or page is owned by a Business Portfolio without those permissions | Ensure business_management and pages_show_list are in your OAuth scope; confirm you are the app admin |
| Error 32 | Page rate limit | Same page token used across too many requests |

---

## Instagram

### Requirements

Instagram's publishing API only works with **Business accounts** when connecting via the Facebook Login path (which both the plugin and social.php use). Creator accounts and personal accounts are not supported by this path.

Your Instagram account must be:
- Switched to a Business account (not Creator, not Personal)
- Linked to a Facebook Page that you manage
- Connected through the same Meta app used for Facebook

If you only have a personal Instagram account with no Facebook Page, the API cannot be used. You would need to convert to a Business account and create or link a Facebook Page first.

### How connection works

Instagram does not have a separate auth flow. It connects automatically when you connect Facebook, as long as your Facebook Page has a linked Instagram Business account.

**Plugin**: Click **Connect Facebook**. After the Facebook auth completes, the plugin queries the Facebook API for any Instagram Business account linked to your Page and stores the connection automatically. If the Instagram panel shows "Not connected" after connecting Facebook, your Instagram account is either not a Business account or not linked to the correct Facebook Page.

**social.php**: Instagram connects automatically alongside Facebook. After running the Facebook auth flow, if your Page has a linked Instagram Business account the script will display the Instagram user ID and access token on screen alongside the Facebook page token. Paste both values into social.php:

```php
$ig_user_id      = '[INSTAGRAM-USER-ID]';
$ig_access_token = '[INSTAGRAM-ACCESS-TOKEN]';  // Same as Facebook page token.
```

### Linking Instagram to a Facebook Page

If your Instagram account isn't already linked to your Facebook Page:

1. On Facebook, go to your Page's settings
2. Find **Instagram** in the left sidebar
3. Click **Connect account** and log in to your Instagram Business account
4. On Instagram, go to **Settings > Account > Linked accounts** and verify Facebook is connected

### Image requirements

Every Instagram post requires an image (text-only posts are not possible through the API). The image must be:

- Publicly accessible via a direct URL (no authentication, no redirects to login pages)
- Not blocked by hotlinking protection or CDN authentication
- JPEG or PNG format (WebP is not supported)
- Under 8MB
- Aspect ratio between 4:5 (portrait) and 1.91:1 (landscape)

WordPress optimization plugins that convert images to WebP will cause Instagram posting to fail silently. If your site serves WebP by default, you need to ensure the full-size JPEG or PNG is also available.

The posting limit is **100 API-published posts per 24-hour rolling period**.

### Two-step publish process

Instagram posting always requires two API calls: creating a media container, then publishing it. The plugin handles this automatically. If you're building on top of social.php, be aware:

1. `POST /{ig-user-id}/media` creates the container, returns a container ID
2. Wait a few seconds (containers for images are near-instant, videos take longer)
3. `POST /{ig-user-id}/media_publish` publishes the container

Containers expire after 24 hours. Always create and publish in the same operation, not at separate times.

### Troubleshooting Instagram

| Error | Cause | Fix |
|-------|-------|-----|
| Not connected after Facebook auth | Instagram not a Business account or not linked to Page | Convert account type; link to Page in Facebook Page settings |
| Image could not be fetched | Image URL not publicly accessible | Check site isn't password-protected; disable hotlink protection |
| Aspect ratio error | Image dimensions outside 4:5 to 1.91:1 range | Resize or crop the featured image |
| The user is not an Instagram Business | Using Creator account via old API path | Convert to Business account |
| 10 posts per hour | Container publish rate limit | Space out posts; the plugin's 1-minute delay after publish handles this |

---

## LinkedIn

### Overview

LinkedIn is the most complex platform to set up due to its product-approval system and token expiry. Tokens expire every 60 days with no automatic renewal for basic apps. Plan to re-authenticate quarterly.

### 1. Create a LinkedIn Company Page

A Company Page is required before you can create a developer app. Your LinkedIn profile must meet these requirements:

- Profile older than 7 days
- Name matches your real name
- Company email address added and verified (free domains like Gmail are rejected: must be your company domain, e.g. `you@yoursite.com`)
- At least one connection
- Listed as an employee of the company in your Experience section

These requirements are inconsistently enforced. If you hit a "Feature not available" block, try using an older, more established LinkedIn account to create the page, then add your preferred account as an admin afterward.

To create the page:
- Go to [linkedin.com/company/setup/new](https://www.linkedin.com/company/setup/new)
- Fill in company name, website, industry, size, and type
- Upload a logo
- Check the verification box and click **Create Page**

### 2. Create a developer app

- Go to [developer.linkedin.com](https://developer.linkedin.com) and sign in
- Click **Create App**
- Enter an app name, select your Company Page, upload a logo
- Agree to terms and click **Create App**
- Go to the **Auth** tab and verify the app

### 3. Configure auth settings

Add your redirect URI under **OAuth 2.0 Settings** on the Auth tab:

- **Plugin**: Use the URL shown in the plugin's LinkedIn setup instructions panel
- **social.php**: `https://yoursite.com/social.php?setup=linkedin&key=[SOCIAL-KEY-HERE]`

Copy the **Client ID** and **Client Secret** from the Auth tab.

### 4. Add required products

You need **both** of the following products (one alone is insufficient):

- **Share on LinkedIn**: Grants `w_member_social` scope for posting to personal profiles. Approved instantly. Without this, you cannot post.
- **Sign In with LinkedIn using OpenID Connect**: Grants `openid`, `profile`, and `email` scopes and access to `/v2/userinfo`, which returns your Person ID (the `sub` field). Approved instantly. Without this, you cannot retrieve your own Person ID, which is required as the `author` field in every post.

If you only request "Share on LinkedIn," the auth will succeed and tokens will be issued, but posting will fail because the `author` URN is unknown.

To post to a **Company Page**, you also need:
- **Community Management API**: Grants `w_organization_social` scope. This product is only available to registered legal organizations (LLC, Corporation, etc.), individual developers are explicitly excluded. Approval requires submitting a screen recording demo and may take days to weeks. If rejected, you must create a new app to reapply.

### 5. Get your IDs

**Personal profile ID**: Retrieved automatically during the auth flow from the `sub` field in `/v2/userinfo`. The plugin stores this automatically. For social.php, it is displayed on screen after the auth flow completes.

**Company Page ID**: Go to your Company Page in a desktop browser. The numeric string after `/company/` in the URL is your org ID (e.g. `linkedin.com/company/112734241/`). If your page uses a custom name, click **See all employees** and check the new URL for `f_C=` followed by the numeric ID.

### 6. Connect

**Plugin**: Enter Client ID and Secret, optionally enter Organization ID if posting to a Company Page, then click **Connect LinkedIn**.

**social.php**:
```php
$li_client_id     = '[LINKEDIN-CLIENT-ID]';
$li_client_secret = '[LINKEDIN-CLIENT-SECRET]';
$li_person_id     = '[LINKEDIN-PERSON-ID]';
$li_org_id        = '[LINKEDIN-ORG-ID]';
```

Then visit the auth flow URL:
`https://yoursite.com/social.php?setup=linkedin&key=[SOCIAL-KEY-HERE]`

After authorizing, you will see:
```
LinkedIn access token: [TOKEN]
LinkedIn person ID: [PERSON-ID]
```

Add to social.php:
```php
$li_person_id    = '[LINKEDIN-PERSON-ID]';
$li_access_token = '[LINKEDIN-ACCESS-TOKEN]';
```

### Token expiry

Tokens expire after exactly 60 days. There is no way to extend them for basic apps (the full OAuth flow must be repeated). Set a calendar reminder to reconnect every 60 days. The plugin's LinkedIn settings panel shows a countdown to token expiry — the counter turns orange at 14 days and red at 7 days.

Refresh tokens (which would allow automatic renewal) are only available to Marketing Developer Platform partners (not available to standard developer apps).

### API versioning

LinkedIn's API uses versioned headers. The `LinkedIn-Version` header must be in `YYYYMM` format (e.g. `202501`). Versions sunset approximately every 12 months. Using a deprecated version returns a 403 error without a clear message. The plugin automatically uses the current year's version.

### Troubleshooting LinkedIn

| Error | Cause | Fix |
|-------|-------|-----|
| 403 on posting | Token expired | Re-run auth flow |
| 403 on posting | Missing w_member_social scope | Verify Share on LinkedIn product is added |
| 403 on org posting | Missing w_organization_social scope | Request Community Management API product |
| Person ID not retrieved | Sign In with OpenID Connect product not added | Add the product and re-run auth flow |
| Deprecated API version | LinkedIn-Version header too old | Plugin handles this; for social.php update the header year |
| Token works but posts fail | Wrong API endpoint (using old ugcPosts) | Use `/rest/posts` with the Posts API |

---

## Pinterest

### Overview

Pinterest API access involves an approval process before you can post publicly. Initial access is **Trial** (sandbox only) and must be upgraded to **Standard** access before pins are visible to anyone other than yourself.

### 1. Create an app

- Go to [developers.pinterest.com](https://developers.pinterest.com) and log in
- Click **My Apps > Create App**
- Enter an app name and description
- Agree to the terms and click **Create**

### 2. Configure auth settings

Under **App settings**, add your redirect URI:

- **Plugin**: Use the URL shown in the plugin's Pinterest setup instructions panel
- **social.php**: `https://yoursite.com/social.php?setup=pinterest&key=[SOCIAL-KEY-HERE]`

Copy the **App ID** and **App Secret Key** from the app overview.

### 3. Request Standard access

New apps start in **Trial access**, which limits pins to the sandbox (`api-sandbox.pinterest.com`) and trial tokens expire after only 24 hours. To post publicly, you must upgrade to **Standard access**:

- In your app dashboard, look for the access level upgrade section
- Submit a request with a description of your use case
- **A video demo is required** showing the full OAuth flow and at least one Pinterest action (pin creation)
- Pinterest frequently rejects submissions with vague feedback. Record the demo in sandbox mode and explain this in your submission

This approval process can take days to weeks. Many developers report multiple rejections. If rejected, check the Pinterest Business Community forum at `community.pinterest.biz`. Pinterest staff occasionally assist there and can manually upgrade access.

### 4. Scopes required

The documented scopes `boards:read` and `pins:write` are not sufficient. You also need `boards:write` even just for creating pins. The full recommended scope set is:

`user_accounts:read,boards:read,boards:write,pins:read,pins:write`

Omitting `boards:write` results in: "Your token does not have sufficient permissions to perform this operation."

### 5. Connect

**Plugin**: Enter App ID and App Secret, then click **Connect Pinterest**. The plugin automatically populates your first board ID after connecting. You can change this to any board you own afterward.

**social.php**: Enter App ID and App Secret, then visit `https://yoursite.com/social.php?setup=pinterest&key=[SOCIAL-KEY-HERE]`. After authorizing, the access token, refresh token, and first board ID are displayed on screen. Paste them into social.php. Board IDs are large numeric strings, always treat them as strings, not integers, as they exceed JavaScript's safe integer range.

### Token expiry and refresh

Access tokens expire after **30 days**. Refresh tokens use Pinterest's "continuous refresh" system: they expire after 60 days but can be renewed indefinitely by including `"continuous_refresh": true` in the initial token request. The plugin handles token refresh automatically. For manual implementations, pass this parameter when requesting the initial access token.

### Image requirements

- Images must be publicly accessible via direct URL
- Supported formats: JPEG, PNG, GIF, WebP (Pinterest is more permissive than Instagram)
- Maximum 10MB
- **URL shorteners (bit.ly, goo.gl, etc.) are completely blocked** (Pinterest rejects pins with shortened URLs with error 5000)
- Redirect URIs must match exactly (no wildcards, no trailing slash differences, no HTTP/HTTPS mismatches)

### Troubleshooting Pinterest

| Error | Cause | Fix |
|-------|-------|-----|
| Pins visible only to you | Still on Trial access | Apply for Standard access |
| Insufficient permissions | Missing boards:write scope | Re-authorize with all required scopes |
| Error 5000 | Shortened URL in pin link | Use the full URL |
| Image fetch failure | Image not publicly accessible | Remove hotlink protection; check CDN settings |
| Token expired | 30-day access token limit | Re-authorize; plugin handles this automatically |

---

## Reddit

### Overview

Reddit is the most restrictive platform for automated posting. API access requires approval, posting to public subreddits risks bans, and the only reliable use case for auto-posting is a subreddit **you own or moderate**.

### API access approval

As of late 2024, Reddit removed self-service API access. You must submit an application and wait for approval. Personal/low-volume use is typically approved within a few days. Commercial use (including monetized WordPress plugins) requires explicit approval and may cost $12,000+/year for enterprise access.

When applying, describe your use case accurately. "Auto-posting blog articles to my own subreddit for community building" is a valid personal use case. Be specific about your posting frequency and that you are only posting to a subreddit you own.

### 1. Create a Reddit app

- Log in to Reddit and go to [reddit.com/prefs/apps](https://www.reddit.com/prefs/apps)
- Scroll to the bottom and click **create another app**
- Enter a name
- Select **web app** as the type (not script, script apps only work for your own account and break with 2FA)
- Add your redirect URI:
  - **Plugin**: Use the URL shown in the plugin's Reddit setup instructions panel
  - **social.php**: `https://yoursite.com/social.php?setup=reddit&key=[SOCIAL-KEY-HERE]`
- Click **Create App**
- The **Client ID** is shown under the app name (the short string, not the secret)
- The **Client Secret** is labeled "secret"

### 2. Connect

**Plugin**: Enter Client ID, Client Secret, and your subreddit name (without `r/`), then click **Connect Reddit**. The plugin retrieves your Reddit username automatically during the auth flow.

**social.php**: Enter Client ID, Client Secret, and subreddit, then visit `https://yoursite.com/social.php?setup=reddit&key=[SOCIAL-KEY-HERE]`. After authorizing, the access token, refresh token, and your Reddit username are displayed on screen. Paste all three into social.php.

The auth request uses `duration=permanent` which grants a refresh token. Reddit access tokens expire after 1 hour. The plugin refreshes them automatically. For social.php, visit `?setup=reddit_refresh&key=[SOCIAL-KEY-HERE]` to exchange the stored refresh token for a new access token without re-authorizing.

### Subreddit restrictions and ban risks

**Only post to a subreddit you own or moderate.** Reddit enforces self-promotion rules aggressively:

- Reddit's 90/10 rule: 90% of account activity should be genuine participation, only 10% self-promotional
- Many subreddits have minimum karma requirements (0 to 100+) and minimum account age requirements (1 to 30 days)
- These requirements are often set via AutoModerator config and are not publicly visible
- Posts can return a **200 success response** but be silently removed by AutoModerator immediately after
- Posting identical content to multiple subreddits is one of the fastest paths to a spam ban

If you want to auto-post your content to Reddit, create your own branded subreddit (`r/yoursite` or `r/yourbrand`) and post there. This avoids all karma/age restrictions and self-promotion rules since you are the moderator.

### Flair requirements

Many subreddits require post flair at submission time. If your subreddit has required flair, posts without flair will fail with `SUBMIT_VALIDATION_FLAIR_REQUIRED`. To handle this:

1. Fetch available flairs: `GET /r/{subreddit}/api/link_flair_v2` (requires `flair` scope)
2. Include `flair_id` in the submission body

The plugin currently submits without flair. If your subreddit has required flair, disable flair requirements in your subreddit settings (Mod tools > Post requirements).

### Token management

Access tokens expire every hour. The plugin refreshes them automatically using the stored refresh token. For social.php, visit `?setup=reddit_refresh&key=KEY` to get a new access token using the stored refresh token. Refresh tokens are long-lived but can be revoked at any time (if the refresh fails, re-run the full setup flow).

### User-Agent requirements

Reddit requires a specific User-Agent format and **blocks generic strings**. The plugin sends: `wordpress:simpleshare:[version] (by /u/[username])`. social.php sends: `web:social.php:1.0 (by /u/[username])`. This format is required for all API calls.

### Troubleshooting Reddit

| Error | Cause | Fix |
|-------|-------|-----|
| 403 on all requests | Generic User-Agent | Plugin handles this; check version |
| 200 but post disappears | AutoModerator removal | Check subreddit mod log; disable flair requirements |
| SUBMIT_VALIDATION_FLAIR_REQUIRED | Subreddit requires flair | Disable flair requirement in mod settings |
| 429 Too Many Requests | Rate limit (100 req/min) | Plugin spaces requests; don't call the API manually in parallel |
| Auth fails | API access not approved | Check approval status at reddit.com/prefs/apps |

---

## X (Twitter)

### Pricing

X API posting costs **$0.01 per post** on the pay-per-use model. You must purchase a minimum of **$5 in credits** under Billing > Credits before any posts will go through. There is no monthly subscription required (credits are consumed as you post).

Legacy tiers (Essential, Elevated, Free, Basic) have been deprecated in favor of the pay-per-use system. If you previously had a legacy account, your old apps may not appear in the new console at `console.x.com` and may need to be recreated.

### 1. Create a developer account

- Go to [console.x.com](https://console.x.com) and sign in with your X account
- If this is your first time, accept the Developer Agreement and describe your use case (e.g. "Auto-posting blog articles to my own account")

### 2. Create an app

- Click **Create App**, give it a name and description, and click **Create**
- Your app will be created under a project automatically (this is required for posting to work)

### 3. Set app permissions

This is the most commonly missed step. New apps default to **Read-only**, which silently prevents all posting.

- In your app, find **User Authentication Settings** and click **Set Up** (or **Edit**)
- Set **App permissions** to **Read and Write**
- Set **Type of App** to **Web App, Automated App or Bot**
- Enter your website URL in both the **Callback URI** and **Website URL** fields
- Save

**Critical:** After saving Read and Write permissions, you must regenerate your Access Token and Access Token Secret. Tokens generated before changing permissions are permanently read-only. Go back to your app, find the OAuth 1.0 Keys section, and click **Regenerate** next to Access Token.

### 4. Get your keys

- Click **Apps** in the left menu, then click your app to reveal its keys
- Under **OAuth 1.0 Keys** (not OAuth 2.0), copy the **Consumer Key** and **Consumer Secret** (these are your API Key and API Secret)
- Copy the **Access Token** and **Access Token Secret** from the same section

### 5. Connect

**Plugin**: Enter all four values in the X settings panel and click **Connect X**.

**social.php**:
```php
$x_api_key       = '[X-API-KEY]';
$x_api_secret    = '[X-API-SECRET]';
$x_access_token  = '[X-ACCESS-TOKEN]';
$x_access_secret = '[X-ACCESS-TOKEN-SECRET]';
```

No OAuth redirect flow is needed. X uses OAuth 1.0a with pre-generated tokens that never expire.

### Troubleshooting X

| Error | Cause | Fix |
|-------|-------|-----|
| 403 Forbidden | Read-only app permissions | Change to Read and Write, then regenerate tokens |
| 403 Duplicate content | Same text posted twice recently | Append unique content or test with different articles |
| 401 Could not authenticate | Clock drift or wrong credentials | Check server time sync; verify all four credentials are correct |
| 403 Not attached to project | App created outside a project | Create a new app inside a project |

X returns 403 for duplicate content even when the post technically succeeds, always check the log response body, not just the status code.

---

## Testing

### SimpleShare plugin

1. Enable logging on the **Auto-Post** tab
2. Publish a test post (or use **Reset Last Posted** on the Log tab to re-trigger the most recent post)
3. Wait 1–2 minutes (the plugin defers posting by 1 minute after publish)
4. Check the **Log** tab for results

To test without waiting, use the broadcast field on the **Auto-Post** tab to send a custom message immediately to all connected platforms.

X will return 403 for duplicate content if you test the same post URL twice. Use a different test post or check the log response body rather than the status code alone (a 403 from X on duplicate content still means the post was received).

### social.php

Once all platforms are configured:

1. Enable logging by setting `define( 'SOCIAL_LOG', true );` in social.php
2. Delete `social.txt` in the script directory if it exists (this resets the timestamp)
3. Visit the page that includes or triggers social.php
4. Check `social.log` for results

To re-test, delete `social.txt` again. Note: on first run (no `social.txt`), the script posts the most recent RSS item regardless of its publish date, then exits. Subsequent runs post only items newer than the last run. For X duplicate content testing, use different article URLs.

To send a custom message to all connected platforms at any time, visit `https://yoursite.com/social.php?key=[SOCIAL-KEY-HERE]` (this shows a broadcast form. The message is sent immediately on submit).

---

## Log reference

### SimpleShare plugin log format

```
[YYYY-MM-DD HH:MM:SS] Platform: HTTP_STATUS response_body
```

### social.php log format

Same format, written to `social.log` in the script directory.

### Common log entries

| Entry | Meaning |
|-------|---------|
| `X: 201` | Success |
| `X: 403 {"detail":"You are not allowed to create a Tweet with duplicate content."}` | Duplicate post (normal during testing) |
| `X: 403 {"title":"Forbidden"}` | Read-only app permissions (regenerate tokens after setting Read and Write) |
| `Facebook: 200` | Success |
| `Facebook: 190` | Token expired or revoked (re-run auth flow) |
| `Facebook: 200 {"id":"..."}` | Success with post ID |
| `Instagram: 201` | Success |
| `Instagram: 0` | Network error or image URL not accessible |
| `LinkedIn (person): 201` | Success |
| `LinkedIn (person): 403` | Token expired, wrong scope, or deprecated API version |
| `LinkedIn (org): 403` | Community Management API not approved or token lacks w_organization_social |
| `Pinterest: 201` | Success |
| `Pinterest: 401` | Token expired (re-authenticate) |
| `Reddit: 200` | Success (but check subreddit mod log if post doesn't appear) |
| `Reddit: 403` | Access denied (check API approval and User-Agent) |

---

## Troubleshooting: social.php file permissions

`social.txt` and `social.log` must be writable by the web server user (typically `www-data`). `social.txt` stores the last-posted timestamp and is always created on first run. `social.log` is only created if `SOCIAL_LOG` is set to `true`.

Check ownership:
```bash
ls -la /path/to/social.txt
ls -la /path/to/social.log
```

Fix ownership:
```bash
chown www-data:www-data /path/to/social.txt
chown www-data:www-data /path/to/social.log
```

---

## Token expiry summary

| Platform | Token expiry | Renewable? |
|----------|-------------|------------|
| X | Never | N/A |
| Facebook (page token) | Never (normally) | Re-run auth if invalidated |
| Instagram | Shares Facebook page token | Same as Facebook |
| LinkedIn | 60 days | No (re-authenticate manually) |
| Pinterest (access) | 30 days | Yes, automatically via refresh token |
| Pinterest (refresh) | 60 days | Yes, as long as at least one post goes out every 60 days |
| Reddit (access) | 1 hour | Yes, automatically via refresh token |
| Reddit (refresh) | Long-lived | Until revoked |