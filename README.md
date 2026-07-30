# Under Construction

A simple, practically single-file **"UNDER CONSTRUCTION"** page with an animated SVG scene (night sky, construction site). The whole site lives in `index.php` — HTML, CSS and animations together, no dependencies.

## Features

- Animated "under construction" SVG scene rendered with pure CSS/SVG, responsive to both window width and height.
- A "notify me on launch" form — submitted e-mails are stored in `notify-emails.json` (with validation and deduplication).
- `.htaccess` blocks downloading `notify-emails.json` over the web (Apache; on nginx an equivalent deny rule is needed).

## Deployment

Just upload the repository contents to a PHP-capable host (PHP is only needed for storing the e-mails) and make sure the web server can write to `notify-emails.json`:

```bash
chmod 666 notify-emails.json   # or make it owned by the web server user
```

No build step, no dependencies.

## Files

| File | Description |
|---|---|
| `index.php` | The whole page — form handling, HTML, CSS and the animated SVG scene |
| `notify-emails.json` | Stored e-mails for the launch notification |
| `.htaccess` | Prevents the e-mail list from being downloaded over the web |

## License

[GNU GPL v3](LICENSE)
