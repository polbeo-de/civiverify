<?php

declare(strict_types=1);

use CRM_CiviVerify_ExtensionUtil as E;

final class CRM_CiviVerify_Page_Confirm extends CRM_Core_Page {

  private const CACHE_PREFIX = 'civiverify_confirm_';
  private const STATE_TTL = 600;

  public function run(): void {
    $this->setSecurityHeaders();
    if (!Civi::service('civiverify.rate_limiter')->consume()) {
      $this->assignResult('invalid');
      parent::run();
      return;
    }
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
      $this->handlePost();
    }
    else {
      $this->handleGet();
    }
    parent::run();
  }

  private function handleGet(): void {
    $token = (string) ($_GET['token'] ?? '');
    if ((bool) Civi::settings()->get('civiverify_direct_get_confirmation')) {
      $ipHash = NULL;
      if ((bool) Civi::settings()->get('civiverify_ip_hashing_enabled')) {
        $ipHash = Civi::service('civiverify.ip_hasher')->persistentRequestHash();
      }
      $this->assignResult(Civi::service('civiverify.verifier')->verify($token, $ipHash)->result);
      return;
    }
    $preview = Civi::service('civiverify.verifier')->inspect($token);
    if ($preview->result !== 'pending') {
      $this->assignResult($preview->result);
      return;
    }
    $state = bin2hex(random_bytes(24));
    Civi::cache('long')->set(self::CACHE_PREFIX . $state, $token, self::STATE_TTL);
    $this->assign('civiverify_state', $state);
    $this->assign('civiverify_result', 'confirm');
    $this->assign('civiverify_title', E::ts('Confirm request'));
    $this->assign('civiverify_message', E::ts('Please confirm that you want to complete this verification.'));
  }

  private function handlePost(): void {
    $state = (string) ($_POST['state'] ?? '');
    if (!preg_match('/^[a-f0-9]{48}$/', $state)) {
      $this->assignResult('invalid');
      return;
    }
    $cacheKey = self::CACHE_PREFIX . $state;
    $token = Civi::cache('long')->get($cacheKey);
    Civi::cache('long')->delete($cacheKey);
    if (!is_string($token)) {
      $this->assignResult('invalid');
      return;
    }
    $ipHash = NULL;
    if ((bool) Civi::settings()->get('civiverify_ip_hashing_enabled')) {
      $ipHash = Civi::service('civiverify.ip_hasher')->requestHash();
    }
    $this->assignResult(Civi::service('civiverify.verifier')->verify($token, $ipHash)->result);
  }

  private function assignResult(string $result): void {
    $messages = [
      'verified' => [E::ts('Confirmation successful'), E::ts('Thank you. This request has been confirmed.')],
      'already_used' => [E::ts('Already confirmed'), E::ts('This request has already been confirmed.')],
      'revoked' => [E::ts('Link unavailable'), E::ts('This verification link is invalid or no longer available.')],
      'expired' => [E::ts('Link unavailable'), E::ts('This verification link is invalid or no longer available.')],
      'invalid' => [E::ts('Link unavailable'), E::ts('This verification link is invalid or no longer available.')],
    ];
    [$title, $message] = $messages[$result] ?? [E::ts('Technical error'), E::ts('The request could not be processed.')];
    $setting = $result === 'verified' ? 'civiverify_success_message' : 'civiverify_failure_message';
    $configuredMessage = trim((string) Civi::settings()->get($setting));
    if ($configuredMessage !== '') {
      $message = $configuredMessage;
    }
    $this->assign('civiverify_result', $result);
    $this->assign('civiverify_title', $title);
    $this->assign('civiverify_message', $message);
  }

  private function setSecurityHeaders(): void {
    CRM_Utils_System::setHttpHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    CRM_Utils_System::setHttpHeader('Pragma', 'no-cache');
    CRM_Utils_System::setHttpHeader('Referrer-Policy', 'no-referrer');
    CRM_Utils_System::setHttpHeader('X-Content-Type-Options', 'nosniff');
    CRM_Utils_System::setHttpHeader('X-Frame-Options', 'DENY');
  }

}
