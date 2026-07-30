![](/images/app-icon-256x256.png)

# Under Construction

A simple, practically single-file **"UNDER CONSTRUCTION"** page with an animated SVG scene (night sky, construction site). The whole site lives in `index.php` — HTML, CSS and animations together, no dependencies.

## Features

- Animated "under construction" SVG scene rendered with pure CSS/SVG, responsive to both window width and height.
- A "notify me on launch" form — submitted e-mails are stored in `notify-emails.json` (with validation and deduplication).
- `.htaccess` blocks downloading `notify-emails.json` over the web (Apache; on nginx an equivalent deny rule is needed).

## Deployment

Just upload the repository contents to a PHP-capable host (PHP is only needed for storing the e-mails), create the e-mail storage file from the sample and make sure the web server can write to it:

```bash
cp notify-emails.sample.json notify-emails.json
chmod 666 notify-emails.json   # or make it owned by the web server user
```

`notify-emails.json` is git-ignored so the collected e-mails never end up in the repository.

No build step, no dependencies.

## Files

| File | Description |
|---|---|
| `index.php` | The whole page — form handling, HTML, CSS and the animated SVG scene |
| `notify-emails.sample.json` | Sample for `notify-emails.json` — copy it on deployment |
| `notify-emails.json` | Stored e-mails for the launch notification (git-ignored, created from the sample) |
| `.htaccess` | Prevents the e-mail list from being downloaded over the web |

## License

[GNU GPL v3](LICENSE)
