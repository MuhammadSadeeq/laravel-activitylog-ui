# ActivityLog UI Assets

This directory contains the logo and favicon assets for the ActivityLog UI package.

## Files

- `logo.svg` - Main logo for the application header
- `favicon.svg` - SVG favicon for modern browsers
- `favicon-generator.html` - Tool to generate different favicon sizes
- `README.md` - This file

## Usage

### Publishing Assets

To publish these assets to your Laravel application, run:

```bash
php artisan vendor:publish --tag=activitylog-ui-assets
```

This will copy the assets to `public/vendor/activitylog-ui/images/`

### Logo Customization

You can override the default logo by setting the logo path in your config:

```php
// config/activitylog-ui.php
'ui' => [
    'logo' => '/path/to/your/custom/logo.svg',
    // ...
]
```

### Favicon Generation

1. Open `favicon-generator.html` in your browser
2. Right-click on each icon size and save as PNG
3. Use an online favicon generator (like favicon.io) to create favicon.ico
4. Or use ImageMagick: `convert favicon-32.png favicon.ico`

## Design Details

- **Colors**: Blue to Purple gradient (#3B82F6 to #8B5CF6)
- **Icon**: Document with activity dots and lines
- **Style**: Modern, clean, professional
- **Format**: SVG for scalability

## Browser Support

- SVG favicons: Modern browsers (Chrome 80+, Firefox 41+, Safari 9+)
- ICO fallback: All browsers including older versions 
