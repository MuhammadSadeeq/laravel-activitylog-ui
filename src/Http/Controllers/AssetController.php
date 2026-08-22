<?php

namespace MuhammadSadeeq\ActivitylogUi\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the package's stylesheet.
 *
 * Shipped rather than published, so a host never has to run vendor:publish to
 * get a working page and can never end up with a stylesheet from a different
 * version than the markup. It is also deliberately outside the authenticated
 * route group: an unauthenticated visitor is redirected to the host's login
 * page, and a 401 on the stylesheet would leave that page unstyled.
 */
class AssetController extends Controller
{
    /**
     * A short hash of the stylesheet's contents, for the URL.
     *
     * Serving it immutable for a year from a fixed URL meant an upgraded
     * package rendered new markup against a year-old stylesheet, and no
     * revalidation request was ever made to notice. The hash goes in the path
     * so a changed file is a different URL.
     */
    public static function version(): string
    {
        static $version;

        return $version ??= substr(md5_file(static::path()) ?: 'dev', 0, 12);
    }

    protected static function path(): string
    {
        return __DIR__ . '/../../resources/css/activitylog-ui.css';
    }

    public function stylesheet(Request $request): Response
    {
        $path = static::path();

        if (!is_file($path)) {
            abort(404);
        }

        // Keyed on the file's own content, so a package upgrade busts the cache
        // without anyone having to think about it.
        $etag = '"' . substr(md5_file($path), 0, 16) . '"';

        $candidates = array_map(
            fn ($value) => ltrim(trim($value), 'W/'),
            explode(',', (string) $request->headers->get('If-None-Match'))
        );

        if (in_array('*', $candidates, true) || in_array($etag, $candidates, true)) {
            return response('', 304)->withHeaders([
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
        ]);
    }
}
