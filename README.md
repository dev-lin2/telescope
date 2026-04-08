# Laravel Telescope (Fork)

Forked from [laravel/telescope](https://github.com/laravel/telescope) v4.9.6. This is **not** intended to be merged back upstream.

**Base version:** v4.9.6 (PHP 7.3+ / Laravel 8.x / 9.x)
**Branch:** `dev-master`

## What's Added

### Date Range Filtering

All index/search screens now have **From** and **To** date pickers alongside the existing tag search. Filter any watcher (requests, queries, exceptions, jobs, etc.) by date range.

**Files changed:**
- `src/Storage/EntryQueryOptions.php` — `afterDate` / `beforeDate` properties
- `src/Storage/EntryModel.php` — `whereAfterDate` / `whereBeforeDate` scopes
- `resources/js/components/IndexScreen.vue` — date picker inputs

### Curl / Raw HTTP Client Logging

The built-in `ClientRequestWatcher` only captures Laravel HTTP Client (Guzzle) calls. This fork adds helper functions to record **raw curl calls** into the same HTTP Client watcher screen.

**Drop-in replacement:**
```php
// Before
$response = curl_exec($ch);

// After
$response = telescope_curl_exec($ch);
```

Records: URL, HTTP method, status code, response body (truncated), duration, curl errors. Tagged with `curl` and the target domain.

**Manual recording** for edge cases:
```php
telescope_record_http('POST', $url, $payload, $response, $durationMs, $statusCode, $headers);
```

**Files added:**
- `src/helpers.php` — `telescope_curl_exec()` and `telescope_record_http()`

### Layout Customizations

- Static asset paths (`/public/vendor/telescope/app.css` and `app.js`)
- Removed asset version mismatch warning

## Installation

In your project's `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/dev-lin2/telescope.git"
    }
],
"require": {
    "laravel/telescope": "dev-dev-master"
}
```

Then:
```bash
composer update laravel/telescope
php artisan telescope:publish
```

## License

MIT (same as upstream).
