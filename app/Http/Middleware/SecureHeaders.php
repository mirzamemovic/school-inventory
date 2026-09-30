<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers every response carries, and plain HTTP turned away in production.
 *
 * The Content-Security-Policy matters most. Report descriptions are typed by
 * anonymous strangers and read on the director's screen; Blade escapes them,
 * and this is the second wall behind that one: the browser refuses to run any
 * script that is not a file from this site, so text that ever slipped past
 * escaping still could not act as the director. There are no inline scripts
 * or event-handler attributes anywhere in the views (a test keeps it so), and
 * htmx runs with eval switched off.
 *
 * Styles allow inline attributes: the label-sheet picker sets its grid inline,
 * and with images and connections locked to this site a style cannot send
 * anything anywhere.
 */
class SecureHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->mustUpgrade($request)) {
            // 308 keeps the method and body, so nothing is silently dropped.
            return redirect()->to('https://'.$request->getHttpHost().$request->getRequestUri(), 308);
        }

        $response = $next($request);

        $headers = $response->headers;

        $headers->set('Content-Security-Policy', $this->policy());
        // Nothing here is meant to sit inside someone else's page: framed, the
        // Retire and Not-a-problem buttons could be clicked by trickery.
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        // Links carry references and signed class links; they never leave
        // with a click to another site.
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), payment=(), usb=()');

        if (app()->environment('production') && $this->isSecure($request)) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }

    private function mustUpgrade(Request $request): bool
    {
        return app()->environment('production')
            && config('school.force_https')
            && ! $this->isSecure($request);
    }

    /**
     * Some shared hosts end TLS at a proxy in front of PHP. Trusting the
     * forwarded header here only decides whether to redirect — at worst a
     * client that lies about it is served over the connection it chose — so
     * it cannot be abused the way trusting it for IP addresses could.
     */
    private function isSecure(Request $request): bool
    {
        return $request->secure() || strtolower((string) $request->header('X-Forwarded-Proto')) === 'https';
    }

    private function policy(): string
    {
        $dev = $this->viteDevServer();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", ...$dev],
            'style-src' => ["'self'", "'unsafe-inline'", ...$dev],
            'img-src' => ["'self'", 'data:'],
            'font-src' => ["'self'"],
            'connect-src' => ["'self'", ...$dev, ...array_map(fn ($origin) => preg_replace('#^http#', 'ws', $origin), $dev)],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $name) => $name.' '.implode(' ', array_unique($sources)))
            ->implode('; ');
    }

    /**
     * While `npm run dev` is running on a developer's machine the assets come
     * from the Vite server. Never the case in production, where assets are
     * built and uploaded.
     *
     * @return array<int, string>
     */
    private function viteDevServer(): array
    {
        $hot = public_path('hot');

        if (app()->environment('production') || ! is_file($hot)) {
            return [];
        }

        $url = rtrim((string) file_get_contents($hot));

        return $url === '' ? [] : [$url];
    }
}
