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
    public function stylesheet(Request $request): Response
    {
        $path = __DIR__ . '/../../resources/css/activitylog-ui.css';

        if (!is_file($path)) {
            abort(404);
        }

        // Keyed on the file's own content, so a package upgrade busts the cache
        // without anyone having to think about it.
        $etag = '"' . substr(md5_file($path), 0, 16) . '"';

        if (trim((string) $request->headers->get('If-None-Match'), 'W/') === $etag) {
            return response('', 304)->withHeaders(['ETag' => $etag, 'Cache-Control' => 'public, max-age=31536000']);
        }

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
        ]);
    }
}
