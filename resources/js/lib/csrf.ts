/**
 * Laravel's VerifyCsrfToken middleware sets this cookie on every
 * response (independent of Sanctum/Inertia) — reading it back lets a
 * plain fetch() call satisfy CSRF checks the same way axios's automatic
 * XSRF handling would, without pulling axios into the project just for
 * this one call.
 */
export function getXsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : null;
}
