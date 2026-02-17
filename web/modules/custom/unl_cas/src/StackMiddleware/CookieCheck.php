<?php

namespace Drupal\unl_cas\StackMiddleware;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Checks for a unl_sso cookie before the main kernel takes over the request.
 */
class CookieCheck implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Constructs a CookieCheck object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   */
  public function __construct(HttpKernelInterface $http_kernel) {
    $this->httpKernel = $http_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    $session = FALSE;

    // The user isn't available yet at this stage in the middleware. So look for
    // the Drupal session cookie to indicate that there is an authenticated user.
    $cookies = $request->cookies->keys();
    foreach ($cookies as $cookie) {
      if (strpos($cookie, 'SSESS', 0) !== FALSE) {
        $session = TRUE;
        break;
      }
    }

    // If:
    //   1) a cas path is NOT being requested,
    //   2) there is NOT a user session,
    //   3) there IS a unl_sso cookie present that was set by shib.unl.edu on another site,
    //   4) and domain is not a high-traffic domain (@TODO turn this into a setting),
    // then attempt to log in.
    if (!str_starts_with($request->getPathInfo(), '/cas')
      && !$session
      && $request->cookies->get('unl_sso')
      && !in_array($request->getHost(), ['www.unl.edu', 'its.unl.edu'])
    ) {
      // Have ot manually construct the login URL rather than use Drupal's
      // Url object because not everything it uses is available yet.
      $login_url = $request->getBasePath() . '/cas?destination=' . urlencode($request->getRequestUri());

      $response = new RedirectResponse(
        $login_url,
        302,
        ['Cache-Control' => 'no-cache'],
      );
      $response->send();
      exit;
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

}
