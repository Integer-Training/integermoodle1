# Site Backup Plugin — Known Issues

## Google Drive OAuth Token Exchange Failure

**Status:** Open
**Priority:** Medium (local download works as workaround)
**Affected:** Production

### Symptoms

After completing Google OAuth consent flow, the token exchange step fails with:

```
Invalid grant_type:
```

The error message shows an empty value after the colon, suggesting the `grant_type` parameter is either not being received by Google or is malformed.

### What Has Been Tried

1. **Raw PHP `curl_*` functions** with `http_build_query()` POST body — same error
2. **Moodle's `\curl` class** (`lib/filelib.php`) — same error
3. **`file_get_contents` with stream context** + manual `rawurlencode` body construction + explicit `Content-Length` header + `grant_type` first in body — same error

### Configuration

- Google Cloud Console: OAuth 2.0 Web Application
- Redirect URI: `https://YOUR_DOMAIN/local/sitebackup/authorize.php`
- OAuth consent screen: Testing mode (test user added)
- Scope: `https://www.googleapis.com/auth/drive.file`
- Token endpoint: `https://oauth2.googleapis.com/token`

### Possible Root Causes to Investigate

1. **Hosting WAF/firewall** stripping or modifying POST body parameters before they reach Google
2. **Proxy or CDN** (e.g., Cloudflare) interfering with outbound POST requests
3. **PHP configuration** — `allow_url_fopen` or cURL extension issues on the production host
4. **URL encoding issue** — the `grant_type=authorization_code` value may be double-encoded
5. **Google Cloud project** — try creating a fresh OAuth client ID and secret

### Suggested Next Steps

1. SSH into production and test token exchange directly with cURL CLI:
   ```bash
   curl -X POST https://oauth2.googleapis.com/token \
     -d "code=AUTH_CODE_HERE" \
     -d "client_id=YOUR_CLIENT_ID" \
     -d "client_secret=YOUR_SECRET" \
     -d "redirect_uri=https://YOUR_DOMAIN/local/sitebackup/authorize.php" \
     -d "grant_type=authorization_code"
   ```
2. If CLI cURL works, the issue is in PHP HTTP handling on the host
3. Check if Hostinger (hosting provider) has any outbound request restrictions
4. Try adding verbose logging to `google_drive::token_request()` to capture the full request/response

---

## Database Error on First Backup Run (Production)

**Status:** Partially Fixed
**Priority:** Low (upgrade.php added to create table if missing)

### Symptoms

Clicking "Run Backup Now" showed "Error writing to database / Error reading from database" on the production site.

### Likely Cause

The `local_sitebackup_logs` table may not have been created during initial plugin install on production.

### Fix Applied

Added `db/upgrade.php` (version 2026020102) that creates the table if it doesn't exist. User needs to run the Moodle upgrade process (`/admin/index.php`) after uploading the new plugin files.

### Verification Needed

Confirm the table exists on production after upgrade and that "Run Backup Now" works.
